"""6. fasea: erdiko izenengatik sortutako bikoiztuak bateratu.

00_dedup-ek match_key (token multzo zehatza) erabiltzen du, eta horregatik ez ditu
harrapatzen izen-formatu osoa duten bikoiztuak ("Buitrago Santiago" vs
"Santiago BUITRAGO SANCHEZ"). Hemen TOKEN-AZPIMULTZOA erabiltzen da: izen baten
tokenak beste baten azpimultzo zorrotza badira (gehienez 2 token gehiago), pertsona
bera dira.

Segurtasuna:
  - ANBIGUOAK baztertu: azpimultzo bera supermultzo bati baino gehiagori lotzen
    bazaio (adib. "Jefferson Cepeda" -> Alexander ETA Alveiro), ez batu.
  - Kanonikoa = erreferentzia gehien dituen ID-a; bestea harengana batu.

Erabilera:
  python3 06_dedup_subset.py            # lehor (plana erakutsi, rollback)
  python3 06_dedup_subset.py --apply     # benetan aplikatu (babeskopia eginda)
"""

import os
import shutil
import sqlite3
import sys

import ods_porra as o

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, "data", "AramaixoPorra.db")

FK_REFS = [
    ("TxapelketaSailkapenaTxirrindulariak", "Txirrindularia_ID"),
    ("TxapelketaEmaitzaTxirrindulariak", "Txirrindularia_ID"),
    ("KarreraSailkapena", "Txirrindularia_ID"),
    ("TxirrindulariakTxapleketanParteHartzea", "TxirrindulariaID"),
    ("PorraApustuak", "Txirrindularia_ID"),
]

MAX_EXTRA = 2  # supermultzoak gehienez token gehigarri kopurua

# Eskuzko merge gehigarriak (azpimultzo-erregelak harrapatzen ez dituenak:
# token gehiegi edo idazkera ezberdina). (kanon_id, dup_id).
EXTRA_MERGES = [
    (170, 605),   # Bilbao Pello <- Pello BILBAO LOPEZ DE ARMENTIA (+3 token)
    (222, 622),   # Aranburu Alex <- Alexander ARANBURU DEVA (alex != alexander)
    # 05 berrabiarazteak izen osoekin birsortutako bikoiztuak (922-929):
    (222, 922), (121, 923), (176, 924), (170, 925),
    (226, 926), (218, 927), (732, 928), (19, 929),
]


def refcount(cur, rid):
    return sum(cur.execute("SELECT COUNT(*) FROM %s WHERE %s=?" % (t, c), (rid,)).fetchone()[0]
              for t, c in FK_REFS)


def main():
    apply = "--apply" in sys.argv
    con = sqlite3.connect(DB)
    con.execute("PRAGMA foreign_keys=OFF")
    cur = con.cursor()

    riders = [(cid, izena, frozenset(o.match_key(izena)))
              for cid, izena in cur.execute("SELECT Txirrindularia_ID, Izena FROM Txirrindulariak")]

    # azpimultzo-bikoteak: (sub_id, sup_id)
    raw = []
    for sub in riders:
        if len(sub[2]) < 2:
            continue
        for sup in riders:
            if sub[0] != sup[0] and sub[2] < sup[2] and 1 <= len(sup[2] - sub[2]) <= MAX_EXTRA:
                raw.append((sub, sup))

    # ANBIGUOAK baztertu: id bera bikote anitzetan badago bi aldetan, kendu id hori
    from collections import defaultdict
    sub_to_sups = defaultdict(set)
    sup_to_subs = defaultdict(set)
    for sub, sup in raw:
        sub_to_sups[sub[0]].add(sup[0])
        sup_to_subs[sup[0]].add(sub[0])
    ambiguous = {i for i, s in sub_to_sups.items() if len(s) > 1}
    ambiguous |= {i for i, s in sup_to_subs.items() if len(s) > 1}

    pairs = [(sub, sup) for sub, sup in raw
             if sub[0] not in ambiguous and sup[0] not in ambiguous]

    # kanonikoa = erreferentzia gehiago; bestea batu
    merges = []  # (canon_id, canon_name, dup_id, dup_name)
    for sub, sup in pairs:
        ra, rb = refcount(cur, sub[0]), refcount(cur, sup[0])
        if ra >= rb:
            canon, dup = sub, sup
        else:
            canon, dup = sup, sub
        merges.append((canon[0], canon[1], dup[0], dup[1]))

    # eskuzko merge gehigarriak
    name_of = {cid: izena for cid, izena, _ in riders}
    done = {(c, d) for c, _, d, _ in merges}
    for canon, dup in EXTRA_MERGES:
        if canon in name_of and dup in name_of and (canon, dup) not in done:
            merges.append((canon, name_of[canon], dup, name_of[dup]))

    print("Anbiguoak (baztertuak):", sorted(ambiguous))
    print("Batu beharreko bikoteak:", len(merges))
    for cid, cn, did, dn in sorted(merges):
        print("  kanon %4d:%-30s <- %4d:%s" % (cid, cn, did, dn))

    moved = dropped = 0
    for canon, _, dup, _ in merges:
        for table, col in FK_REFS:
            before = cur.execute("SELECT COUNT(*) FROM %s WHERE %s=?" % (table, col), (dup,)).fetchone()[0]
            cur.execute("UPDATE OR IGNORE %s SET %s=? WHERE %s=?" % (table, col, col), (canon, dup))
            rem = cur.execute("SELECT COUNT(*) FROM %s WHERE %s=?" % (table, col), (dup,)).fetchone()[0]
            cur.execute("DELETE FROM %s WHERE %s=?" % (table, col), (dup,))
            moved += before - rem
            dropped += rem
        cur.execute("DELETE FROM Txirrindulariak WHERE Txirrindularia_ID=?", (dup,))

    n = cur.execute("SELECT COUNT(*) FROM Txirrindulariak").fetchone()[0]
    print("\nErref. mugituak: %d | baztertuak(talka): %d | Txirrindulariak orain: %d" % (moved, dropped, n))

    if apply:
        shutil.copy2(DB, DB + ".prededup2.bak")
        con.commit()
        print("Aplikatuta. Babeskopia:", DB + ".prededup2.bak")
    else:
        con.rollback()
        print("(LEHOR moduan, rollback. Aplikatzeko: --apply)")
    con.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
