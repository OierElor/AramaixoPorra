"""4. fasea: idazkera-aldaeragatik 00_dedup-ek harrapatu ez zituen bikoiztuak
bateratu, eta horrela dortsal "falta" (zatituak) konpondu.

Pertsona bera bi ID-rekin zegoen (emaitzetan bata, startlist/apustuetan bestea),
match_key-ek bat egiten ez zuelako (Wout/Wouter, Izagirre/Izaguirre, ...). Datuak
ID kanonikora (emaitzak dituena) birbideratzen dira, bikoiztua ezabatu, eta
benetan falta diren 3 dortsal gehitu.

Erabilera:
  python3 04_fix_dortsalak.py           # lehor (talkak detektatu, rollback)
  python3 04_fix_dortsalak.py --apply     # benetan aplikatu (babeskopia eginda)
"""

import os
import shutil
import sqlite3
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, "data", "AramaixoPorra.db")

# (kanon_id, dup_id) — kanona = emaitzak dituena; dup harengana batuko da
MERGES = [
    (184, 864),   # Poels Wout            = Poels Wouter
    (185, 660),   # Izagirre Ion          = Ion Izaguirre Insausti
    (249, 859),   # Barta Will            = Barta William
    (82, 427),    # Narváez Jhonatan      = Jhonatan Manuel Narvaez Prado
    (92, 560),    # Molano Juan Sebastián = Juan Sebastian Molano Benavides
    (68, 371),    # Ghebreigzabhier Amanuel = Gebreigzabhier Amanuel
    (79, 365),    # Sánchez Pelayo        = Sanchez Mayo Pelayo
    (319, 369),   # Hamilton Christopher  = Hamilton Chris
]

# Izen kanonikoa hobetu (laburtuegiak)
RENAMES = {
    184: "Poels Wouter",
    249: "Barta William",
}

# Benetan falta diren dortsalak (bikoizketarik gabeko puntudunak)
MISSING_DORTSALS = [
    (8, 314, 142),   # Christian Odd (Eiking)
    (8, 323, 222),   # Lloyd Joseph (Dombrowski)
    (11, 398, 35),   # Lecerf Junior
]

# (taula, Txirrindularia_ID zutabea, gako-zutabeak PK talkak detektatzeko)
FK_REFS = [
    ("TxapelketaSailkapenaTxirrindulariak", "Txirrindularia_ID", ["Txapelketa_ID", "Azken_Karrera_ID"]),
    ("TxapelketaEmaitzaTxirrindulariak", "Txirrindularia_ID", ["Txapelketa_ID"]),
    ("KarreraSailkapena", "Txirrindularia_ID", ["Karrera_ID"]),
    ("TxirrindulariakTxapleketanParteHartzea", "TxirrindulariaID", ["TxapelketaID"]),
    ("PorraApustuak", "Txirrindularia_ID", ["Txapelketa_ID", "Ezizen_ID"]),
]


def main():
    apply = "--apply" in sys.argv
    con = sqlite3.connect(DB)
    con.execute("PRAGMA foreign_keys=OFF")
    cur = con.cursor()

    # --- talkak detektatu (gako bera kanonak eta dupek partekatzen dutenean) ---
    conflicts = []
    for canon, dup in MERGES:
        for table, col, keys in FK_REFS:
            kcols = ", ".join(keys)
            q = ("SELECT %s FROM %s WHERE %s=? INTERSECT SELECT %s FROM %s WHERE %s=?"
                 % (kcols, table, col, kcols, table, col))
            for row in cur.execute(q, (canon, dup)).fetchall():
                conflicts.append((table, canon, dup, keys, row))

    if conflicts:
        print("PK TALKAK (kanonak eta dupek gako bera dute):")
        for table, canon, dup, keys, row in conflicts:
            keyval = ", ".join("%s=%s" % (k, v) for k, v in zip(keys, row))
            print("  %-38s kanon=%d dup=%d  {%s}" % (table, canon, dup, keyval))
    else:
        print("PK talkarik EZ: birbideratze garbia.")

    # --- batu ---
    moved = dropped = 0
    for canon, dup in MERGES:
        for table, col, keys in FK_REFS:
            before = cur.execute("SELECT COUNT(*) FROM %s WHERE %s=?" % (table, col), (dup,)).fetchone()[0]
            cur.execute("UPDATE OR IGNORE %s SET %s=? WHERE %s=?" % (table, col, col), (canon, dup))
            rem = cur.execute("SELECT COUNT(*) FROM %s WHERE %s=?" % (table, col), (dup,)).fetchone()[0]
            cur.execute("DELETE FROM %s WHERE %s=?" % (table, col), (dup,))
            moved += before - rem
            dropped += rem
        cur.execute("DELETE FROM Txirrindulariak WHERE Txirrindularia_ID=?", (dup,))

    for cid, name in RENAMES.items():
        cur.execute("UPDATE Txirrindulariak SET Izena=? WHERE Txirrindularia_ID=?", (name, cid))

    added = 0
    for tid, rid, dorsal in MISSING_DORTSALS:
        cur.execute("INSERT OR IGNORE INTO TxirrindulariakTxapleketanParteHartzea "
                    "(TxapelketaID, TxirrindulariaID, Dortsala) VALUES (?, ?, ?)", (tid, rid, dorsal))
        added += cur.rowcount

    n_txirr = cur.execute("SELECT COUNT(*) FROM Txirrindulariak").fetchone()[0]
    print("\nBatutako bikoteak: %d | erref. mugituak: %d | baztertuak(talka): %d"
          % (len(MERGES), moved, dropped))
    print("Izen-hobekuntzak: %d | dortsal berriak: %d | Txirrindulariak orain: %d"
          % (len(RENAMES), added, n_txirr))

    if apply:
        shutil.copy2(DB, DB + ".predortsalfix.bak")
        con.commit()
        print("\nAplikatuta. Babeskopia:", DB + ".predortsalfix.bak")
    else:
        con.rollback()
        print("\n(LEHOR moduan, rollback. Aplikatzeko: --apply)")
    con.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
