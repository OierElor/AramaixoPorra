"""
CSV datuak SQLite datu-basean sartzeko scripta.
Exekutatu: python inportatu_csv.py
"""

import sqlite3
import csv
import os

# ── Konfigurazioa ─────────────────────────────────────────────────────────────
DB_FITXATEGIA = "porra.db"          # ← aldatu zure .db fitxategiaren bidea
CSV_KARPETA   = "."                  # ← CSV fitxategiak dauden karpeta

TXAPELKETAK = [
    {
        "izena": "Giro d'Italia",
        "urtea": 2023,
        "txirrindulariak_csv": "txirrindulari_sailkapena_giro_23.csv",
        "porralariak_csv":     "porra_sailkapena_giro_23.csv",
        "dortsala_zutabea": False,   # 2023: '' zutabea=izena, 'Txirrindularia'=dortsala
    },
    {
        "izena": "Giro d'Italia",
        "urtea": 2024,
        "txirrindulariak_csv": "Txirrindulari_sailkapena_giro_24.csv",
        "porralariak_csv":     "porra_sailkapena_giro_24.csv",
        "dortsala_zutabea": True,    # 2024: 'dortsala' eta 'Txirrindularia' zutabeak
    },
    {
        "izena": "Giro d'Italia",
        "urtea": 2025,
        "txirrindulariak_csv": "Txirrindulari_Sailkapena_Giro_2025.csv",
        "porralariak_csv":     "Porralari_Sailkapena_Giro_2025.csv",
        "dortsala_zutabea": False,
    },
]

# ── Laguntzaileak ──────────────────────────────────────────────────────────────

def ireki_csv(fitxategia):
    bide = os.path.join(CSV_KARPETA, fitxategia)
    return open(bide, newline="", encoding="utf-8-sig")

def txapelketa_edo_sortu(cur, izena, urtea):
    cur.execute(
        "INSERT OR IGNORE INTO Txapelketak (Izena, Urtea) VALUES (?, ?)",
        (izena, urtea)
    )
    cur.execute(
        "SELECT Txapelketa_ID FROM Txapelketak WHERE Izena=? AND Urtea=?",
        (izena, urtea)
    )
    return cur.fetchone()[0]

def karrera_edo_sortu(cur, txapelketa_id, izena, urtea):
    cur.execute(
        "INSERT OR IGNORE INTO Karrerak (Txapelketa_ID, Izena, Urtea) VALUES (?, ?, ?)",
        (txapelketa_id, izena, urtea)
    )
    cur.execute(
        "SELECT Karrerak_ID FROM Karrerak WHERE Izena=? AND Urtea=?",
        (izena, urtea)
    )
    return cur.fetchone()[0]

def txirrindularia_edo_sortu(cur, izena):
    izena = izena.strip()
    cur.execute(
        "INSERT OR IGNORE INTO Txirrindulariak (Izena) VALUES (?)",
        (izena,)
    )
    cur.execute(
        "SELECT Txirrindularia_ID FROM Txirrindulariak WHERE Izena=?",
        (izena,)
    )
    return cur.fetchone()[0]

def porralaria_edo_sortu(cur, izena):
    cur.execute(
        "INSERT OR IGNORE INTO Porralariak (Izena) VALUES (?)",
        (izena,)
    )
    cur.execute(
        "SELECT Porralaria_ID FROM Porralariak WHERE Izena=?",
        (izena,)
    )
    return cur.fetchone()[0]

# ── Inportazioa ────────────────────────────────────────────────────────────────

def inportatu_txirrindulariak(cur, txapelketa_id, karrera_id, fitxategia, dortsala_zutabea):
    """KarreraSailkapena eta TxapelketaSailkapenaTxirrindulariak beteko ditu."""
    with ireki_csv(fitxategia) as f:
        irakurlea = csv.DictReader(f)
        for errenkada in irakurlea:
            errenkada = {k.strip() if k else k: v.strip() for k, v in errenkada.items()}

            if dortsala_zutabea:
                # 2024: 'dortsala' eta 'Txirrindularia' zutabeak
                dortsala = int(errenkada.get("dortsala", 0) or 0)
                txirrindularia_izena = errenkada.get("Txirrindularia", "").strip()
            else:
                # 2023/2025: 'Txirrindularia'=dortsala, ''=izena
                dortsala = int(errenkada.get("Txirrindularia", 0) or 0)
                txirrindularia_izena = errenkada.get("", "").strip()

            if not txirrindularia_izena:
                continue

            puntuak = int(errenkada.get("Puntuak", 0) or 0)
            sailkapena = int(errenkada.get("Puntuatze Sailkapena", 0) or 0)

            txirrindularia_id = txirrindularia_edo_sortu(cur, txirrindularia_izena)

            # KarreraSailkapena
            cur.execute("""
                INSERT OR REPLACE INTO KarreraSailkapena
                    (Karrera_ID, Txirrindularia_ID, Puntuak, Dortsala)
                VALUES (?, ?, ?, ?)
            """, (karrera_id, txirrindularia_id, puntuak, dortsala))

            # TxapelketaSailkapenaTxirrindulariak (azken egoera)
            cur.execute("""
                INSERT OR REPLACE INTO TxapelketaSailkapenaTxirrindulariak
                    (Txapelketa_ID, Txirrindularia_ID, Azken_Karrera_ID,
                     Puntuak_Totalean, Puntuak_Azken_Karrera, Eboluzioa, Puntuazio_Finala)
                VALUES (?, ?, ?, ?, ?, 0, ?)
            """, (txapelketa_id, txirrindularia_id, karrera_id,
                  puntuak, puntuak, sailkapena))

    print(f"  ✓ Txirrindulariak sartuta: {fitxategia}")


def inportatu_porralariak(cur, txapelketa_id, karrera_id, fitxategia):
    """TxapelketaSailkapenaPorralariak beteko du."""
    with ireki_csv(fitxategia) as f:
        irakurlea = csv.DictReader(f)
        for errenkada in irakurlea:
            errenkada = {k.strip(): v.strip() for k, v in errenkada.items() if k}

            porralaria_izena = errenkada.get("Porralaria", "").strip()
            if not porralaria_izena:
                continue

            puntuak = int(errenkada.get("Puntuak", "0") or "0")
            posizioa = int(errenkada.get("Posizioa", 0) or 0)

            porralaria_id = porralaria_edo_sortu(cur, porralaria_izena)

            cur.execute("""
                INSERT OR REPLACE INTO TxapelketaSailkapenaPorralariak
                    (Txapelketa_ID, Porralaria_ID, Azken_Karrera_ID,
                     Puntuak_Totalean, Puntuak_Azken_Karrera, Puntuazio_Finala)
                VALUES (?, ?, ?, ?, ?, ?)
            """, (txapelketa_id, porralaria_id, karrera_id,
                  puntuak, puntuak, posizioa))

    print(f"  ✓ Porralariak sartuta: {fitxategia}")


def main():
    kon = sqlite3.connect(DB_FITXATEGIA)
    kon.execute("PRAGMA foreign_keys = ON")
    cur = kon.cursor()

    for txap in TXAPELKETAK:
        print(f"\n── {txap['izena']} {txap['urtea']} ──────────────────────")

        txapelketa_id = txapelketa_edo_sortu(cur, txap["izena"], txap["urtea"])
        karrera_id    = karrera_edo_sortu(cur, txapelketa_id,
                                          txap["izena"], txap["urtea"])

        inportatu_txirrindulariak(
            cur, txapelketa_id, karrera_id,
            txap["txirrindulariak_csv"],
            txap["dortsala_zutabea"]
        )
        inportatu_porralariak(
            cur, txapelketa_id, karrera_id,
            txap["porralariak_csv"]
        )

    kon.commit()
    kon.close()
    print("\n✅ Datuak ongi sartu dira!")


if __name__ == "__main__":
    main()
