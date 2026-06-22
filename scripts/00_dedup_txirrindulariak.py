"""0. fasea: Txirrindulariak taulako bikoiztuak bateratu.

Txirrindulari bera hainbat formatutan dago ("Roglič Primož", "ROGLIČ Primož",
"Primož Roglič"). Talde bakoitzeko ID kanoniko bat (txikiena = jatorrizko
forma title-case) hautatu, gainerako ID-ak harengana birbideratu erreferentzia-
taula guztietan, eta soberako lerroak ezabatu.

Erabilera:
  python3 00_dedup_txirrindulariak.py            # lehor (zer egingo lukeen erakutsi)
  python3 00_dedup_txirrindulariak.py --apply     # benetan aplikatu (babeskopia eginda)
"""

import os
import shutil
import sqlite3
import sys

import ods_porra as o

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, "data", "AramaixoPorra.db")

# (taula, Txirrindularia_ID zutabea) erreferentzia guztiak
FK_REFS = [
    ("TxapelketaSailkapenaTxirrindulariak", "Txirrindularia_ID"),
    ("TxapelketaEmaitzaTxirrindulariak", "Txirrindularia_ID"),
    ("KarreraSailkapena", "Txirrindularia_ID"),
    ("TxirrindulariakTxapleketanParteHartzea", "TxirrindulariaID"),
    ("PorraApustuak", "Txirrindularia_ID"),
]


def build_groups(con):
    groups = {}
    for cid, izena in con.execute("SELECT Txirrindularia_ID, Izena FROM Txirrindulariak"):
        groups.setdefault(o.match_key(izena), []).append((cid, izena))
    return {k: sorted(v) for k, v in groups.items() if len(v) > 1}


def main():
    apply = "--apply" in sys.argv
    con = sqlite3.connect(DB)
    con.execute("PRAGMA foreign_keys=OFF")
    groups = build_groups(con)

    # dup_id -> canon_id (kanonikoa = ID txikiena)
    mapping = {}
    for v in groups.values():
        ids = [c for c, _ in v]
        canon = ids[0]
        for c in ids[1:]:
            mapping[c] = canon

    print("Bikoiztutako talde kopurua:", len(groups))
    print("Birbideratu beharreko ID kopurua:", len(mapping))
    print("\nAdibideak:")
    for v in list(groups.values())[:8]:
        print("  kanon=%d:%r  <-  %s" % (v[0][0], v[0][1],
              ", ".join("%d:%r" % (c, n) for c, n in v[1:])))

    if not apply:
        print("\n(LEHOR moduan. Aplikatzeko: --apply)")
        return 0

    bak = DB + ".bak"
    shutil.copy2(DB, bak)
    print("\nBabeskopia:", bak)

    cur = con.cursor()
    moved = {t: 0 for t, _ in FK_REFS}
    dropped = {t: 0 for t, _ in FK_REFS}
    for dup, canon in mapping.items():
        for table, col in FK_REFS:
            before = cur.execute(
                "SELECT COUNT(*) FROM %s WHERE %s=?" % (table, col), (dup,)).fetchone()[0]
            if not before:
                continue
            # PK talkarik gabe birbideratu; talka dagoenean (kanonak jada badu lerroa) baztertu
            cur.execute("UPDATE OR IGNORE %s SET %s=? WHERE %s=?" % (table, col, col),
                        (canon, dup))
            remaining = cur.execute(
                "SELECT COUNT(*) FROM %s WHERE %s=?" % (table, col), (dup,)).fetchone()[0]
            cur.execute("DELETE FROM %s WHERE %s=?" % (table, col), (dup,))
            moved[table] += before - remaining
            dropped[table] += remaining
        cur.execute("DELETE FROM Txirrindulariak WHERE Txirrindularia_ID=?", (dup,))

    con.commit()
    print("\nErreferentziak birbideratuta / baztertuta (talka):")
    for table, _ in FK_REFS:
        print("  %-42s mugituak=%4d  baztertuak=%3d" % (table, moved[table], dropped[table]))
    total = con.execute("SELECT COUNT(*) FROM Txirrindulariak").fetchone()[0]
    print("\nTxirrindulariak orain:", total)
    con.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
