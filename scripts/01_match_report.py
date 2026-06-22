"""1. fasea: berrikusketa-txostena (datubasea UKITU GABE).

ODS guztiak irakurri eta sortu scripts/reports/ barruan:
  - cyclists_unmatched.csv: DB-n bat ez datozen txirrindulariak (eskuz berrikusteko).
  - ezizen_unmatched.csv:   PorraEzizenak-en aurkitu ez diren porra-ezizenak.
  - summary.txt:            txapelketa bakoitzeko laburpena.
"""

import csv
import os
import sqlite3
import sys

import ods_porra as o

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB = os.path.join(ROOT, "data", "AramaixoPorra.db")
REPORTS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "reports")


def norm_ezizen(s):
    return " ".join(s.strip().lower().split())


def main():
    os.makedirs(REPORTS, exist_ok=True)
    con = sqlite3.connect(DB)

    # DB txirrindulariak: match_key -> [(id, izena), ...] (kolisioak detektatzeko)
    db_riders = {}
    for cid, izena in con.execute("SELECT Txirrindularia_ID, Izena FROM Txirrindulariak"):
        db_riders.setdefault(o.match_key(izena), []).append((cid, izena))

    # Txapelketak: (izena, urtea) -> id
    txap = {(n, u): i for i, n, u in
            con.execute("SELECT Txapelketa_ID, Izena, Urtea FROM Txapelketak")}

    cyclist_rows = []   # txosten lerroak
    ezizen_rows = []
    summary = []
    seen_unmatched = {}  # normalize -> txosten lerro bakarra (txapelketa anitzetan errepika ez dadin)

    for path, izena, urtea, rel in o.all_files():
        tid = txap.get((izena, urtea))
        blocks = o.real_blocks(path)  # benetako porrak soilik (Partaideak)

        # txapelketa honetako ezizenak DB-n
        if tid is not None:
            db_ez = {norm_ezizen(e): e for (e,) in
                     con.execute("SELECT Ezizena FROM PorraEzizenak WHERE Txapelketa_ID=?", (tid,))}
        else:
            db_ez = {}

        ez_missing = 0
        riders_total = 0
        riders_new = 0

        for ezizena, riders in blocks:
            if tid is not None and norm_ezizen(ezizena) not in db_ez:
                ez_missing += 1
                ezizen_rows.append([rel, izena, ezizena])
            for dorsal, name in riders:
                riders_total += 1
                key = o.match_key(name)
                hits = db_riders.get(key)
                if hits and len(hits) == 1:
                    continue  # bat-etortze argia, ez txostenean
                if hits and len(hits) > 1:  # DB-n kolisioa: eskuz erabaki
                    if key not in seen_unmatched:
                        seen_unmatched[key] = [name, o.clean_name(name),
                                               " / ".join("%d:%s" % h for h in hits),
                                               izena, "ANBIGUOA", ""]
                        cyclist_rows.append(seen_unmatched[key])
                    continue
                riders_new += 1
                if key not in seen_unmatched:
                    seen_unmatched[key] = [name, o.clean_name(name), o.canonical_name(name),
                                           izena, "BERRIA", ""]
                    cyclist_rows.append(seen_unmatched[key])

        summary.append((rel, izena, tid if tid is not None else "BERRIA(sortu)",
                        len(blocks), ez_missing, riders_total, riders_new))

    # idatzi CSVak
    with open(os.path.join(REPORTS, "cyclists_unmatched.csv"), "w", newline="") as f:
        w = csv.writer(f)
        w.writerow(["ODS_izena", "garbitua", "proposatua_kanonikoa",
                    "lehen_txapelketa", "egoera", "Txirrindularia_ID_eskuz"])
        w.writerows(sorted(cyclist_rows, key=lambda r: r[2].lower()))

    with open(os.path.join(REPORTS, "ezizen_unmatched.csv"), "w", newline="") as f:
        w = csv.writer(f)
        w.writerow(["fitxategia", "txapelketa", "ezizena"])
        w.writerows(ezizen_rows)

    lines = []
    lines.append("%-42s %-26s %-12s %6s %7s %7s %6s" %
                 ("fitxategia", "txapelketa", "tid", "porra", "ez_falt", "txirr", "berri"))
    for rel, izena, tid, nb, ezm, rt, rn in summary:
        lines.append("%-42s %-26s %-12s %6d %7d %7d %6d" %
                     (rel[:42], izena[:26], str(tid), nb, ezm, rt, rn))
    lines.append("")
    lines.append("Txirrindulari berri bakarrak (DB-n ez daudenak): %d" % len(cyclist_rows))
    lines.append("Ezizen bat-ez-datozenak guztira: %d" % len(ezizen_rows))
    txt = "\n".join(lines)
    with open(os.path.join(REPORTS, "summary.txt"), "w") as f:
        f.write(txt + "\n")

    print(txt)
    print("\nTxostenak: %s" % REPORTS)
    con.close()


if __name__ == "__main__":
    sys.exit(main())
