"""5. fasea: klasiketako PorraApustuak inportatu (itzuli handietako apustuen analogoa).

Klasiketako "Porrak" orriak porra bakoitzeko aukeratutako 25 txirrindulariak ditu,
TXizen1..TXizen25 zutabeetan IZENEZ. Hortik PorraApustuak betetzen da.

Oharrak:
  - Dortsalak EZ dira inportatzen: klasiketan lasterketa bakoitzak dortsal
    desberdina du (ez dago txapelketako dortsal bakarra).
  - Etapaz etapako puntuak (KarreraSailkapena) jada badaude klasiketan.
  - Izen-aldaerak MANUAL_MAP bidez lotzen dira; 7 txirrindulari berri sortzen dira.
  - 2026ko ezizen idazkera-aldaera batzuk EZIZEN_FIX bidez lotzen dira; lotu ezin
    diren porrak (zalantzazkoak) saltatu eta jakinarazi egiten dira.

Erabilera:
  python3 05_import_klasikak.py           # lehor (kontagailuak, rollback)
  python3 05_import_klasikak.py --apply     # benetan aplikatu (babeskopia eginda)
"""

import os
import re
import shutil
import sqlite3
import sys

import ods_porra as o

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, "data", "AramaixoPorra.db")

# Klasiketako ODS fitxategia -> (txapelketa izena, urtea, Txapelketa_ID)
KFILES = {
    "Klasikak/Klasikoen porra 2024.ods": ("Klasikak 2024", 2024, 13),
    "Klasikak/Klasikoen porra 2025 amaitua.ods": ("Klasikak 2025", 2025, 14),
    "Klasikak/Klasikoen porra 2026.ods": ("Klasikak 2026", 2026, 15),
}

# 2026ko ezizen idazkera-aldaerak: (tid, file_ezizena_norm) -> DB ezizena (zehatza)
EZIZEN_FIX = {
    (15, "altza felipe"): "Alta Felipe",
    (15, "leñas eriz"): "Leas Eriz",
    (15, "singanna 2"): "Singana 2",
}


def norm_ez(s):
    return " ".join(s.strip().lower().split())


def klasikak_canon(name):
    """Klasika-izena DB konbentziora: maiuskulazko tokenak (abizena) lehenengo."""
    toks = o.clean_name(name).split()
    sur = [t for t in toks if re.sub(r"[^A-Za-zÀ-ÿ]", "", t).isupper() and t]
    giv = [t for t in toks if t not in sur]
    if sur:
        return " ".join([t.capitalize() for t in sur] + giv)
    return o.canonical_name(name)


def main():
    apply = "--apply" in sys.argv
    con = sqlite3.connect(DB)
    con.execute("PRAGMA foreign_keys=OFF")
    cur = con.cursor()

    db_by_key = {}
    tok_index = []  # (token_multzoa, id) azpimultzo-bat-etortzerako
    for cid, izena in cur.execute("SELECT Txirrindularia_ID, Izena FROM Txirrindulariak"):
        k = o.match_key(izena)
        db_by_key[k] = cid
        tok_index.append((frozenset(k), cid))
    manual_map = o.manual_map_by_key()
    new_cache = {}
    created = []

    def subset_match(key):
        """Izen-formatu ezberdinak (erdiko izenak) lotzeko: token-azpimultzoa.

        Hautagai bakarra badago (anbiguorik ez), haren ID-a itzuli; bestela None.
        """
        t = frozenset(key)
        if len(t) < 2:
            return None
        cands = {cid for s, cid in tok_index
                 if (s <= t or t <= s) and 1 <= len(s ^ t) <= 2}
        return next(iter(cands)) if len(cands) == 1 else None

    def resolve_rider(name, create=True):
        key = o.match_key(name)
        if key in db_by_key:
            return db_by_key[key]
        if key in manual_map:
            return manual_map[key]
        if key in new_cache:
            return new_cache[key]
        sm = subset_match(key)
        if sm is not None:
            new_cache[key] = sm  # cache-atu errepikapenetarako
            return sm
        if not create:
            return None
        cn = klasikak_canon(name)
        cur.execute("INSERT INTO Txirrindulariak (Izena) VALUES (?)", (cn,))
        nid = cur.lastrowid
        new_cache[key] = nid
        db_by_key[key] = nid
        created.append((nid, cn))
        return nid

    stats = {"apustuak": 0, "porra": 0, "dortsalak": 0}
    skipped_ez = []

    for rel, (izena, urtea, tid) in KFILES.items():
        path = os.path.join(o.DATA_DIR, rel)
        rows = o.sheet_rows(path, "Porrak")
        hi = next(i for i, r in enumerate(rows)
                  if any(c.strip().lower() == "porreroa" for c in r))
        izcols = [i for i, c in enumerate(rows[hi]) if c.strip().lower().startswith("txizen")]

        ez_by_norm = {norm_ez(e): i for i, e in cur.execute(
            "SELECT Ezizen_ID, Ezizena FROM PorralariEzizenak WHERE Txapelketa_ID=?", (tid,))}

        for r in rows[hi + 1:]:
            if not (len(r) > 1 and r[0].strip().isdigit() and r[1].strip()):
                continue
            ez = r[1].strip()
            picks = [r[i].strip() for i in izcols
                     if i < len(r) and r[i].strip() and r[i].strip() != "#E/E"]
            if not picks:  # txantiloi hutsa
                continue

            nz = norm_ez(ez)
            db_ez_name = EZIZEN_FIX.get((tid, nz))
            ez_id = ez_by_norm.get(norm_ez(db_ez_name) if db_ez_name else nz)
            if ez_id is None:
                # benetako porra berria (erabiltzaileak berretsita): sortu
                cur.execute("INSERT INTO PorralariEzizenak (Txapelketa_ID, Ezizena) "
                            "VALUES (?, ?)", (tid, ez))
                ez_id = cur.lastrowid
                ez_by_norm[nz] = ez_id
                skipped_ez.append((izena, ez, len(picks)))

            stats["porra"] += 1
            for nm in picks:
                rid = resolve_rider(nm)
                cur.execute(
                    "INSERT OR IGNORE INTO PorraApustuak "
                    "(Txapelketa_ID, Ezizen_ID, Txirrindularia_ID) VALUES (?, ?, ?)",
                    (tid, ez_id, rid))
                stats["apustuak"] += cur.rowcount

        # --- DORTSALAK: 'TX zerrenda'-ko Zbkia kodea (lehendik dauden txirrind.) ---
        for dorsal, nm in o.klasikak_dorsals(path):
            rid = resolve_rider(nm, create=False)
            if rid is None:
                continue
            cur.execute(
                "INSERT OR IGNORE INTO TxirrindulariakTxapleketanParteHartzea "
                "(TxapelketaID, TxirrindulariaID, Dortsala) VALUES (?, ?, ?)",
                (tid, rid, dorsal))
            stats["dortsalak"] += cur.rowcount

    print("Inportatutako porrak:", stats["porra"])
    print("PorraApustuak lerroak:", stats["apustuak"])
    print("Dortsalak (TX zerrenda):", stats["dortsalak"])
    print("Txirrindulari berri sortuak:", len(created))
    for nid, cn in created:
        print("      +%d %s" % (nid, cn))
    if skipped_ez:
        print("\nSORTUTAKO ezizen berriak (DB-n ez zeudenak):")
        for t, e, n in skipped_ez:
            print("      [%s] %r (%d pick)" % (t, e, n))

    if apply:
        bak = DB + ".preklasikak.bak"
        shutil.copy2(DB, bak)
        con.commit()
        print("\nAplikatuta. Babeskopia:", bak)
    else:
        con.rollback()
        print("\n(LEHOR moduan, rollback. Aplikatzeko: --apply)")
    con.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
