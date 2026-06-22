"""3. fasea: etapaz etapako datuak inportatu (hiru handiak).

Klasikak 2026 (txap 15) ereduari jarraituz, grand tour bakoitzeko etapa bakoitza
"Karrera" gisa modelatzen da:

  1. Karrerak: 21 etapa txapelketako (Izena = "<txap> - N. etapa (<helmuga>)").
  2. KarreraSailkapena: etapa bakoitzean puntuatu duen txirrindulari bakoitza
     (Puntuak = etapako puntuak, Sailkapena = postua puntuen arabera).
     -> Eskaera #1: txirrindularien etapaz etapako puntuak.
  3. TxapelketaSailkapenaPorralariak: porra bakoitzaren puntu metatuak etapaz
     etapa (Puntuak_Totalean = metatua, Puntuak_Azken_Karrera = etapako diferentzia).
     -> Eskaera #2: porren sailkapen-bilakaera.

Puntuatu duten baina DB-an falta diren txirrindulariak sortzen dira
(8 Giro 2026; aldaerak MANUAL_MAP bidez lotuta).

Erabilera:
  python3 03_import_etapak.py          # lehor (rollback, kontagailuak)
  python3 03_import_etapak.py --apply   # benetan aplikatu
"""

import os
import shutil
import sqlite3
import sys

import ods_porra as o

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, "data", "AramaixoPorra.db")


def norm_ez(s):
    return " ".join(s.strip().lower().split())


def main():
    apply = "--apply" in sys.argv
    con = sqlite3.connect(DB)
    con.execute("PRAGMA foreign_keys=OFF")
    cur = con.cursor()

    # --- txirrindulari ebazlea (02_import-eko logika berbera) ---
    db_by_key = {}
    for cid, izena in cur.execute("SELECT Txirrindularia_ID, Izena FROM Txirrindulariak"):
        db_by_key[o.match_key(izena)] = cid
    manual_map = o.manual_map_by_key()
    new_cache = {}
    created_riders = []

    def resolve_rider(name, create):
        key = o.match_key(name)
        if key in db_by_key:
            return db_by_key[key]
        if key in manual_map:
            return manual_map[key]
        if key in new_cache:
            return new_cache[key]
        if not create:
            return None
        cn = o.canonical_name(name)
        cur.execute("INSERT INTO Txirrindulariak (Izena) VALUES (?)", (cn,))
        nid = cur.lastrowid
        new_cache[key] = nid
        db_by_key[key] = nid
        created_riders.append((nid, cn))
        return nid

    txap = {(n, u): i for i, n, u in
            cur.execute("SELECT Txapelketa_ID, Izena, Urtea FROM Txapelketak")}

    stats = {"karrerak": 0, "karrera_sailk": 0, "porra_sailk": 0}
    ez_unmatched = []

    for path, izena, urtea, rel in o.all_files():
        tid = txap.get((izena, urtea))
        if tid is None:
            print("OHARRA: txapelketa falta da, saltatzen: %s" % izena)
            continue

        # --- 1) KARRERAK (21 etapa) ---
        meta = {n: (d, t) for n, d, t in o.stage_meta(path)}
        stage_kid = {}
        for n in range(1, o.NUM_STAGES + 1):
            d, town = meta.get(n, ("", ""))
            name = "%s - %d. etapa" % (izena, n)
            if town:
                name += " (%s)" % town
            cur.execute(
                "INSERT OR IGNORE INTO Karrerak (Txapelketa_ID, Izena, Urtea, Kategoria) "
                "VALUES (?, ?, ?, 'Etapa')", (tid, name, urtea))
            stats["karrerak"] += cur.rowcount
            kid = cur.execute(
                "SELECT Karrerak_ID FROM Karrerak WHERE Izena=? AND Urtea=?",
                (name, urtea)).fetchone()[0]
            stage_kid[n] = kid

        # --- 2) KARRERASAILKAPENA: etapako txirrindulari-puntuak ---
        # etapa -> {rider_id: puntuak}
        stage_pts = {n: {} for n in range(1, o.NUM_STAGES + 1)}
        for dorsal, name, pts in o.rider_stage_points(path):
            if not any(pts):  # puntuatu ez dutenak (domestikoak, talde-izenburuak) baztertu
                continue
            rid = resolve_rider(name, create=True)
            for i, p in enumerate(pts):
                if p:
                    stage_pts[i + 1][rid] = stage_pts[i + 1].get(rid, 0) + p
        for n in range(1, o.NUM_STAGES + 1):
            ranking = sorted(stage_pts[n].items(), key=lambda kv: -kv[1])
            for pos, (rid, p) in enumerate(ranking, start=1):
                cur.execute(
                    "INSERT OR REPLACE INTO KarreraSailkapena "
                    "(Karrera_ID, Txirrindularia_ID, Puntuak, Sailkapena) VALUES (?, ?, ?, ?)",
                    (stage_kid[n], rid, p, pos))
                stats["karrera_sailk"] += 1

        # --- 3) TXAPELKETASAILKAPENAPORRALARIAK: porren bilakaera metatua ---
        ez_by_norm = {norm_ez(e): i for i, e in cur.execute(
            "SELECT Ezizen_ID, Ezizena FROM PorraEzizenak WHERE Txapelketa_ID=?", (tid,))}
        for ezizena, cums in o.porra_cumulative(path):
            ez_id = ez_by_norm.get(norm_ez(ezizena))
            if ez_id is None:
                ez_unmatched.append((izena, ezizena))
                continue
            prev = 0
            for i, total in enumerate(cums):
                n = i + 1
                cur.execute(
                    "INSERT OR REPLACE INTO TxapelketaSailkapenaPorralariak "
                    "(Txapelketa_ID, Ezizen_ID, Azken_Karrera_ID, Puntuak_Totalean, "
                    " Puntuak_Azken_Karrera, Puntuazio_Finala) VALUES (?, ?, ?, ?, ?, 0)",
                    (tid, ez_id, stage_kid[n], total, total - prev))
                prev = total
                stats["porra_sailk"] += 1

    print("Karrerak (etapak) sortuak:    ", stats["karrerak"])
    print("KarreraSailkapena lerroak:    ", stats["karrera_sailk"])
    print("Porren bilakaera lerroak:     ", stats["porra_sailk"])
    print("Txirrindulari berri sortuak:  ", len(created_riders))
    for nid, cn in created_riders:
        print("      +%d %s" % (nid, cn))
    if ez_unmatched:
        print("ABISUA: bat ez datozen ezizenak (%d):" % len(ez_unmatched))
        for t, e in ez_unmatched[:20]:
            print("      [%s] %r" % (t, e))

    if apply:
        bak = DB + ".preetapak.bak"
        shutil.copy2(DB, bak)
        con.commit()
        print("\nAplikatuta. Babeskopia:", bak)
    else:
        con.rollback()
        print("\n(LEHOR moduan, rollback eginda. Aplikatzeko: --apply)")
    con.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
