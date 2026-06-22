"""ODS porra fitxategiak irakurtzeko modulu komuna.

Funtzio nagusiak:
  - sheet_rows(path, sheet): orri bateko gelaxken matrizea (errepikapenak zabalduta).
  - load_blocks(path): Porralariak orriko porra-blokeak -> [(ezizena, [(dortsala, izena), ... x15])].
  - load_roster(path): Txirrindulariak orriko txirrindulari-zerrenda osoa -> [(dortsala, izena)].
  - normalize(name): izenak bat-etortzeko forma kanonikoa (azentu/maiuskula gabe).
  - clean_name(name): "(Herr)" herrialdea kendu eta izena garbitu.
  - FILE_TXAPELKETA: ODS fitxategi bakoitza zein txapelketari dagokion.
"""

import functools
import os
import re
import unicodedata

from odf.opendocument import load
from odf.table import Table, TableRow, TableCell
from odf.text import P

DATA_DIR = os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
    "data", "Porren datuak sartzeko excelak",
)

# ODS fitxategia -> (txapelketa izena, urtea). Izenak Txapelketak taulako "Izena"-rekin bat.
FILE_TXAPELKETA = {
    "2020/Vuelta a España 2020.ods": ("Vuelta a España 2020", 2020),
    "2021/Vuelta a España 2021.ods": ("Vuelta a España 2021", 2021),
    "2022/Vuelta a España 2022.ods": ("Vuelta a España 2022", 2022),
    "2023/Giro 23/Giro 2023.ods": ("Giro D'Italia 2023", 2023),
    "2023/Tour 2023/Tour 2023.ods": ("Tour De France 2023", 2023),
    "2023/Vuelta 23/Porra vuelta 2023 pro.ods": ("Vuelta a España 2023", 2023),
    "2024/Giro 2024/Porra Giro 2024.ods": ("Giro D'Italia 2024", 2024),
    "2024/Tour 2024/tour 2024.ods": ("Tour De France 2024", 2024),
    "2024/Vuelta 24/vuelta 2024.ods": ("Vuelta a España 2024", 2024),
    "2025/Giro 2025/Porra Giro 2025.ods": ("Giro D'Italia 2025", 2025),
    "2025/Tour 2025/Porra Tour 2025.ods": ("Tour De France 2025", 2025),
    "2025/Vuelta 25/Porra Vuelta 2025.ods": ("Vuelta a España 2025", 2025),
    "2026/Giro 26/Porra giro 2026.ods": ("Giro D'Italia 2026", 2026),
}

PORRA_BLOCK_SIZE = 15


def _cell_text(cell):
    return " ".join(str(p) for p in cell.getElementsByType(P)).strip()


def _repeat(cell):
    try:
        return int(cell.getAttribute("numbercolumnsrepeated") or 1)
    except (TypeError, ValueError):
        return 1


@functools.lru_cache(maxsize=None)
def _load_doc(path):
    return load(path)


@functools.lru_cache(maxsize=None)
def sheet_rows(path, sheet_name):
    """Orri baten lerroak gelaxka-testu zerrenda gisa itzuli (errepikapenak zabalduta)."""
    doc = _load_doc(path)
    for t in doc.spreadsheet.getElementsByType(Table):
        if t.getAttribute("name") != sheet_name:
            continue
        rows = []
        for r in t.getElementsByType(TableRow):
            cells = []
            for c in r.childNodes:
                if getattr(c, "qname", (None, None))[1] != "table-cell":
                    continue
                rep = min(_repeat(c), 256)  # ez puztu memoria amaierako errepikapen hutsekin
                cells.extend([_cell_text(c)] * rep)
            # kendu amaierako gelaxka hutsak
            while cells and cells[-1] == "":
                cells.pop()
            rows.append(cells)
        return rows
    raise KeyError("Orria ez da aurkitu: %r (%s)" % (sheet_name, path))


def _col(row, i):
    return row[i].strip() if i < len(row) else ""


def load_blocks(path):
    """Porralariak orriko porra-blokeak itzuli.

    Bloke bakoitza: (ezizena, [(dortsala, izena), ...]). col0 zenbaki osoa denean
    bloke berri bat hasten da (porra zenbakia); col1=ezizena, col2=dortsala, col3=izena.
    """
    rows = sheet_rows(path, "Porralariak")
    blocks = []
    for i, r in enumerate(rows):
        c0 = _col(r, 0)
        c1 = _col(r, 1)
        if not (c0.isdigit() and c1):
            continue
        riders = []
        for j in range(i, min(i + PORRA_BLOCK_SIZE, len(rows))):
            dorsal = _col(rows[j], 2)
            name = _col(rows[j], 3)
            if name:
                riders.append((dorsal, name))
        blocks.append((c1, riders))
    return blocks


def partaideak_count(path):
    """Porralariak orriko "Partaideak" goiburuko porra-kopurua (benetako porrak)."""
    rows = sheet_rows(path, "Porralariak")
    for r in rows:
        low = [c.strip().lower() for c in r]
        if "partaideak" in low:
            i = low.index("partaideak")
            for c in r[i + 1:]:
                if c.strip().isdigit():
                    return int(c.strip())
    return None


def real_blocks(path):
    """Benetako porra-blokeak soilik (lehen N = Partaideak); txantiloi hutsak baztertuta."""
    blocks = load_blocks(path)
    n = partaideak_count(path)
    return blocks[:n] if n is not None else blocks


def load_roster(path):
    """Txirrindulariak orriko zerrenda osoa itzuli: [(dortsala, izena)].

    Goiburua "Dorsalak" eta "Izena" etiketen bidez detektatzen da, urtez urte
    zutabeen kokapena aldatzen baita.
    """
    rows = sheet_rows(path, "Txirrindulariak")
    dorsal_col = name_col = None
    header_idx = None
    for i, r in enumerate(rows):
        low = [c.strip().lower() for c in r]
        if "dorsalak" in low and "izena" in low:
            dorsal_col = low.index("dorsalak")
            name_col = low.index("izena")
            header_idx = i
            break
    if header_idx is None:
        raise KeyError("Txirrindulariak goiburua ez da aurkitu: %s" % path)

    roster = []
    for r in rows[header_idx + 1:]:
        name = _col(r, name_col)
        dorsal = _col(r, dorsal_col)
        if not name:
            continue
        if not dorsal.isdigit():  # taldeen izenburuak edo lerro hutsak
            continue
        roster.append((dorsal, name))
    return roster


NUM_STAGES = 21  # Hiru handietako etapa kopurua


def stage_meta(path):
    """Etapak orritik etapa-metadatuak: [(n, data, helmuga)] (gehienez 21)."""
    rows = sheet_rows(path, "Etapak")
    nums = dates = towns = None
    for r in rows:
        head = _col(r, 0).lower()
        if head == "etapak":
            nums = r
        elif head == "data":
            dates = r
        elif head in ("helmuga", "helburua"):
            towns = r
    out = []
    if not nums:
        return out
    for i in range(1, len(nums)):
        n = nums[i].strip()
        if not n.isdigit():
            break
        d = dates[i].strip() if dates and i < len(dates) else ""
        t = towns[i].strip() if towns and i < len(towns) else ""
        out.append((int(n), d, t))
    return out


def _matrix_header(rows):
    for i, r in enumerate(rows):
        low = [c.strip().lower() for c in r]
        if "dorsalak" in low and "izena" in low:
            return i, low.index("dorsalak"), low.index("izena")
    raise KeyError("Txirrindulariak matrize-goiburua ez da aurkitu")


def rider_stage_points(path):
    """Txirrindulariak matrizetik: [(dortsala, izena, [21 puntu etapaka])].

    Taldeen izenburuak (dortsalik gabeak) baztertzen dira.
    """
    rows = sheet_rows(path, "Txirrindulariak")
    hi, dc, nc = _matrix_header(rows)
    out = []
    for r in rows[hi + 1:]:
        name = _col(r, nc)
        dorsal = _col(r, dc)
        if not name or not dorsal.isdigit():
            continue
        pts = []
        for ci in range(nc + 1, nc + 1 + NUM_STAGES):
            v = _col(r, ci)
            pts.append(int(v) if v.lstrip("-").isdigit() else 0)
        out.append((dorsal, name, pts))
    return out


def porra_cumulative(path):
    """Sailkapen taula handiak (lehen taula): [(ezizena, [puntu metatuak etapaka])].

    Txantiloi hutsak (#E/E) eta bigarren taula (sailkapen-postuak) baztertzen dira.
    """
    rows = sheet_rows(path, "Sailkapen taula handiak")
    hi = pcol = None
    for i, r in enumerate(rows):
        low = [c.strip().lower() for c in r]
        for lbl in ("porreroa", "porrero"):
            if lbl in low:
                hi, pcol = i, low.index(lbl)
                break
        if hi is not None:
            break
    if hi is None:
        return []
    # ezizena = "Porreroa" etiketaren (porra-indizea) hurrengo zutabea, finkoa.
    # Zutabe finkoa erabiltzen da: ezizen batzuk zenbaki hutsak dira (adib. "1312").
    name_col = pcol + 1
    cap = partaideak_count(path) or 10 ** 9  # benetako porra kopurua; txantiloiak/2. taula saihestu
    out = []
    for r in rows[hi + 1:]:
        nm = _col(r, name_col)
        if not nm:  # lerro hutsa = bereizlea
            if out:
                break
            continue
        cums = []
        tmpl = False
        for ci in range(name_col + 1, name_col + 1 + NUM_STAGES):
            v = _col(r, ci)
            if v.startswith("#"):  # #E/E txantiloi hutsa
                tmpl = True
                break
            cums.append(int(v) if v.lstrip("-").isdigit() else (cums[-1] if cums else 0))
        if tmpl:
            continue
        while len(cums) < NUM_STAGES:  # amaierako gelaxka hutsak: azkena eraman
            cums.append(cums[-1] if cums else 0)
        out.append((nm, cums))
        if len(out) >= cap:  # benetako porrak amaitu: gelditu (txantiloi/2. taula saihestuz)
            break
    return out


# Herrialdea era ezberdinetan ageri da urtez urte: amaierako "(Esl)" edo
# hasierako "[DEN]". Biak kendu.
_COUNTRY_RE = re.compile(r"\([^)]*\)|\[[^\]]*\]")


def clean_name(name):
    """'(Herr)' / '[Herr]' herrialdea kendu eta hutsuneak normalizatu."""
    return " ".join(_COUNTRY_RE.sub(" ", name).split())


def _ascii_lower(s):
    s = unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode()
    return re.sub(r"[^a-z ]", " ", s.lower())


def normalize(name):
    """Bat-etortzeko forma kanonikoa: herrialdea kendu, azentuak kendu, minuskula, soilik letrak."""
    return re.sub(r"\s", "", _ascii_lower(clean_name(name)))


def match_key(name):
    """Hitz-ordenarekiko independenteko gakoa.

    Izenaren ordena urtez urte aldatzen da ("Abizena Izena" vs "Izena Abizena"),
    beraz token multzoa ordenatuta erabiltzen da bat-etortzeko. Hitz bakarreko
    tokenak (inizialak, "f") baztertu egiten dira zaratarik ez sortzeko.
    """
    toks = [t for t in _ascii_lower(clean_name(name)).split() if len(t) > 1]
    return tuple(sorted(toks))


def canonical_name(name):
    """ODS izena DB konbentziora hurbildu: 'ABIZENA Izena' -> 'Abizena Izena'.

    Maiuskulazko tokenak abizenak dira (hitz anitzeko abizenak onartzen ditu).
    """
    s = clean_name(name)
    tokens = s.split()
    out = []
    for tok in tokens:
        letters = re.sub(r"[^A-Za-zÀ-ÿ]", "", tok)
        if letters and letters.isupper():
            out.append(tok.capitalize())
        else:
            out.append(tok)
    return " ".join(out)


def all_files():
    """(path_absolutua, txapelketa_izena, urtea, rel_path) zerrenda."""
    out = []
    for rel, (izena, urtea) in FILE_TXAPELKETA.items():
        out.append((os.path.join(DATA_DIR, rel), izena, urtea, rel))
    return out


# ODS apustu-/puntuatzaile-izenak lehendik dauden Txirrindularia_ID-etara.
# Erdiko izenak / herri-atzizkiak / idazkera ezberdina direnak (match_key-k
# bat egiten ez dituenak). Erabiltzaileak berretsia. Gakoa match_key bidez sortzen da.
MANUAL_MAP_NAMES = {
    "ALEX ARANBURU DEBA": 222,            # Aranburu Alex (Deba = herria)
    "EGAN ARLEY BERNAL": 122,             # Bernal Egan
    "GEE-WEST Derek": 4,                  # Gee Derek
    "HUGH JOHN CARTHY": 63,               # Carthy Hugh
    "IZAGUIRRE Ion": 185,                 # Izagirre Ion (660 batuta 185-era, 04. fasea)
    "JON ABERASTURI IZAGA": 325,          # Jon Aberasturi
    "LASTRA Jonathan": 460,               # Jonathan Lastra Martinez
    "Lopez Juan Pedro": 329,              # Juan Pedro Lopez Perez
    "MARTINEZ Daniel F.": 70,             # Martínez Daniel Felipe
    "Mathias Jørgensen": 444,             # Norsgaard Mathias Jørgensen
    "Molano Sebastian": 92,               # Molano Juan Sebastián
    "NIELSEN Magnus": 19,                 # Nielsen Magnus Cort
    "Skjelmose Jensen Mattias": 381,      # Skjelmose Mattias
    "ODD CHRISTIAN EIKING": 314,          # Christian Odd
    "JOSEPH LLOYD DOMBROWSKI": 323,       # Lloyd Joseph
    "LECERF William Junior": 398,         # Lecerf Junior
    # Klasiketako izen-formatu osoa ("Izena Erdikoa ABIZENA")
    "Adam Richard YATES": 146,            # Yates Adam
    "Andreas Lorentz KRON": 328,          # Andreas Kron
    "Isaac DEL TORO ROMERO": 119,         # Del Toro Isaac
    "Joao Pedro GONÇALVES ALMEIDA": 6,    # João Almeida
    "Jonas VINGEGAARD HANSEN": 167,       # Vingegaard Jonas
    "Juan AYUSO PESQUERA": 134,           # Ayuso Juan
    "Kaden Alexander GROVES": 8,          # Groves Kaden
    "Ion IZAGUIRRE INSAUSTI": 185,        # Izagirre Ion
    "Jhonatan Manuel NARVAEZ PRADO": 82,  # Narváez Jhonatan
    "Juan Sebastian MOLANO BENAVIDES": 92,  # Molano Juan Sebastián
    "Alexander ARANBURU DEVA": 222,       # Aranburu Alex (alex != alexander)
    "Pello BILBAO LOPEZ DE ARMENTIA": 170,  # Bilbao Pello (+3 token)
}


def manual_map_by_key():
    """MANUAL_MAP_NAMES match_key bidez indexatuta."""
    return {match_key(n): i for n, i in MANUAL_MAP_NAMES.items()}


def klasikak_dorsals(path):
    """Klasiketako 'TX zerrenda' orritik: [(dortsala_int, izena)].

    Orria 3 zutabe-paretan dago (Dortsala|Izena). Talde-lerroak (2 digitu)
    baztertu; txirrindulariek 4 digituko kodea dute (talde+txirrindulari),
    sailkapen nagusiko 'Zbkia' zutabe berbera.
    """
    rows = sheet_rows(path, "TX zerrenda")
    out = []
    for r in rows[1:]:
        for base in (0, 3, 6):
            d = _col(r, base)
            nm = _col(r, base + 1)
            if nm and d.isdigit() and len(d) >= 4:
                out.append((int(d), nm))
    return out
