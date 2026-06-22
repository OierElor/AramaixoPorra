"""2. fasea: apustuak eta dortsalak inportatu.

Egiten duena (transakzio bakarrean, babeskopia eginda):
  1. Giro D'Italia 2026 txapelketa sortu (falta bada).
  2. Apustuetan agertzen diren txirrindulari berriak sortu (56), 13 lehendik
     daudenetara lotu (MANUAL_MAP), gainerakoak match_key bidez lotu.
  3. Falta diren porra-ezizenak sortu (II/III aldaerak siblingaren idazkerara
     egokituta; antzekorik ez dutenak bere horretan).
  4. PorraApustuak bete (porra bakoitzeko 15 txirrindulari).
  5. TxirrindulariakTxapleketanParteHartzea bete dortsalekin, BAINA soilik
     lehendik dauden / sortutako txirrindularientzat (partziala).

Erabilera:
  python3 02_import.py            # lehor (rollback, kontagailuak erakutsi)
  python3 02_import.py --apply     # benetan aplikatu
"""

import os
import re
import shutil
import sqlite3
import sys

import ods_porra as o

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, "data", "AramaixoPorra.db")

# Apustu-izenak lehendik dauden txirrindularietara: ods_porra.MANUAL_MAP_NAMES
# (iturri partekatua, 03_import_etapak.py-k ere erabiltzen du).

ROMANS = {"I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X"}


def split_roman(ez):
    """Ezizena (oinarria, erromatar_zenbakia) bezala banatu, azkenekoa erromatarra bada."""
    toks = ez.strip().split()
    if len(toks) > 1 and toks[-1].upper() in ROMANS:
        return " ".join(toks[:-1]).strip(), toks[-1].upper()
    return ez.strip(), ""


def canon_ezizena(ods_ez, existing):
    """II/III aldaerak DB-ko siblingaren idazkerara egokitu; antzekorik ezean bere horretan."""
    base, num = split_roman(ods_ez)
    if num:
        for e in existing:
            eb, _ = split_roman(e)
            if eb.lower() == base.lower():
                return ("%s %s" % (eb, num)).strip()
    return ods_ez.strip()


def main():
    apply = "--apply" in sys.argv
    con = sqlite3.connect(DB)
    con.execute("PRAGMA foreign_keys=OFF")
    cur = con.cursor()

    # --- txirrindulari ebazlea ---
    db_by_key = {}
    for cid, izena in cur.execute("SELECT Txirrindularia_ID, Izena FROM Txirrindulariak"):
        db_by_key[o.match_key(izena)] = cid
    manual_map = o.manual_map_by_key()
    new_rider_cache = {}
    created_riders = []

    def resolve_rider(name, create):
        key = o.match_key(name)
        if key in db_by_key:
            return db_by_key[key]
        if key in manual_map:
            return manual_map[key]
        if key in new_rider_cache:
            return new_rider_cache[key]
        if not create:
            return None
        cn = o.canonical_name(name)
        cur.execute("INSERT INTO Txirrindulariak (Izena) VALUES (?)", (cn,))
        nid = cur.lastrowid
        new_rider_cache[key] = nid
        created_riders.append((nid, cn))
        return nid

    txap = {(n, u): i for i, n, u in
            cur.execute("SELECT Txapelketa_ID, Izena, Urtea FROM Txapelketak")}

    stats = {"apustuak": 0, "dortsalak": 0, "ezizen_berri": 0,
             "txap_berri": 0, "porra_inportatuak": 0}
    created_ezizen = []

    for path, izena, urtea, rel in o.all_files():
        tid = txap.get((izena, urtea))
        if tid is None:  # Giro 2026: txapelketa sortu
            cur.execute("INSERT INTO Txapelketak (Izena, Urtea) VALUES (?, ?)", (izena, urtea))
            tid = cur.lastrowid
            txap[(izena, urtea)] = tid
            stats["txap_berri"] += 1

        # ezizen mapa (norm -> id) eta DB-ko zerrenda (canon-erako)
        ez_rows = cur.execute(
            "SELECT Ezizen_ID, Ezizena FROM PorralariEzizenak WHERE Txapelketa_ID=?", (tid,)).fetchall()
        ez_by_norm = {" ".join(e.lower().split()): i for i, e in ez_rows}
        ez_existing = [e for _, e in ez_rows]

        # --- 1) APUSTUAK ---
        for ezizena, riders in o.real_blocks(path):
            nz = " ".join(ezizena.lower().split())
            ez_id = ez_by_norm.get(nz)
            if ez_id is None:  # falta den ezizena: sortu
                final = canon_ezizena(ezizena, ez_existing)
                cur.execute(
                    "INSERT INTO PorralariEzizenak (Txapelketa_ID, Ezizena) VALUES (?, ?)",
                    (tid, final))
                ez_id = cur.lastrowid
                ez_by_norm[nz] = ez_id
                ez_existing.append(final)
                created_ezizen.append((izena, ezizena, final))
                stats["ezizen_berri"] += 1
            stats["porra_inportatuak"] += 1
            for dorsal, name in riders:
                rid = resolve_rider(name, create=True)
                cur.execute(
                    "INSERT OR IGNORE INTO PorraApustuak "
                    "(Txapelketa_ID, Ezizen_ID, Txirrindularia_ID) VALUES (?, ?, ?)",
                    (tid, ez_id, rid))
                stats["apustuak"] += cur.rowcount

        # --- 2) DORTSALAK (partziala: lehendik dauden/sortutako txirrindulariak) ---
        for dorsal, name in o.load_roster(path):
            rid = resolve_rider(name, create=False)
            if rid is None:
                continue
            cur.execute(
                "INSERT OR IGNORE INTO TxirrindulariakTxapleketanParteHartzea "
                "(TxapelketaID, TxirrindulariaID, Dortsala) VALUES (?, ?, ?)",
                (tid, rid, int(dorsal)))
            stats["dortsalak"] += cur.rowcount

    print("Txirrindulari berri sortuak:", len(created_riders))
    print("Ezizen berri sortuak:", stats["ezizen_berri"])
    for t, o_, f in created_ezizen:
        print("    [%s] %r -> %r" % (t, o_, f))
    print("Txapelketa berriak:", stats["txap_berri"])
    print("Porra inportatuak:", stats["porra_inportatuak"])
    print("PorraApustuak lerroak:", stats["apustuak"])
    print("Dortsal lerroak (partziala):", stats["dortsalak"])

    if apply:
        bak = DB + ".preimport.bak"
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
