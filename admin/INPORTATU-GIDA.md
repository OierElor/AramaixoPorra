# Datuak Inportatzeko Gida — `Tour2026Excel.ods` → Web datu-basea

Gida honek Excel-eko (`Tour2026Excel.ods`) datuak admin panelaren bidez web datu-basera nola eraman azaltzen du.

Bi tresna daude:
- **🚴 Tour Excel** — Excel honen egiturako blokeentzat berariaz egina (apustuak eta dortsalak).
- **📂 CSV inportatu** — bloke sinpleagoentzat (etapa emaitzak eta sailkapen finalak).

> **Oharra orokorra:** LibreOffice-tik kopiatzean, itsatsi zuzenean testu-koadroan. Mugatzailea (tab, `;`, `,`) automatikoki antzematen da. Berriz inportatzea **segurua** da: apustuak eta dortsalak ez dira bikoizten.

---

## Inportatzeko ORDENA gomendatua

Datuek elkarren mendekotasuna dute. Ordena hau jarraitu:

1. **Txapelketa** sortu (Eskuz sartu → "Txapelketa berria", adib. `Tour de France, 2026`).
2. **Etapak (Karrerak)** sortu txapelketa horretarako (Eskuz sartu edo CSV).
3. **Apustuak** inportatu (Tour Excel → 1. blokea). Honek porralariak, ezizenak, apustuak **eta** dortsalak sortzen ditu.
4. **Dortsalak** osatu (Tour Excel → 2. blokea) — inork aukeratu ez dituen txirrindularien dortsalak ere sartzeko.
5. Etapaz etapa: **etapa emaitzak** inportatu (CSV inportatu).
6. **Sailkapenak kalkulatu** (admin → Sailkapenak kalkulatu).
7. Bukaeran: **sailkapen finalak** inportatu nahi izanez gero (CSV inportatu).

---

## 1 · Apustuak — "Porra denak" orria  🚴 Tour Excel

Excel-eko **"Porra denak"** orriak porra bakoitzaren blokea du: porrolariaren izena + aukeratutako 15 txirrindulariak (dortsalarekin).

**Urratsak:**
1. LibreOffice-n **"Porra denak"** orria ireki.
2. Orri OSOA hautatu: `Ctrl+A` (edo goiko ezkerreko izkinako laukitxoa klikatu) eta `Ctrl+C`.
3. Admin → **🚴 Tour Excel** → **1 · Apustuak**.
4. **Txapelketa** aukeratu.
5. Testu-koadroan itsatsi (`Ctrl+V`).
6. **🔍 Aurreikusi** — zenbat porra, apustu, porralaria berri eta txirrindulari berri sortuko diren erakusten du. Txirrindulari berrien zerrenda eta izen antzekoak agertzen dira (idazketa-akatsak detektatzeko).
7. Ondo badago, **⬆ Inportatu**.

**Zer sortzen du:**
- `Porralariak` — porrolaria berriak (izenaz bat datozenak berrerabiltzen dira).
- `PorraEzizenak` — porra-ezizena txapelketa honetan.
- `PorralariTaldeenEzizenak` — ezizena ↔ porrolaria lotura.
- `PorraApustuak` — porra bakoitzeko 15 apustuak.
- `TxirrindulariakTxapleketanParteHartzea` — ikusitako dortsalak (bide batez).

> Sareta osoa (5 bloke zabaleran, pilatuta) automatikoki zatitzen da. Porra batek 15 txirrindulari ez baditu, abisu bat agertuko da aurreikuspenean.

---

## 2 · Dortsalak — "Txirrindulariak" orria  🚴 Tour Excel

Apustuek ikusi ez dituzten txirrindularien dortsalak osatzeko (edo dortsal guztiak ziurtatzeko).

**Urratsak:**
1. LibreOffice-n **"Txirrindulariak"** orria ireki.
2. **`Dorsalak`** (A zutabea) eta **`Izena`** (D zutabea) zutabeak **soilik** hautatu:
   - A zutabearen goiburua klikatu → `Ctrl` sakatuta D zutabearen goiburua klikatu → `Ctrl+C`.
3. Admin → **🚴 Tour Excel** → **2 · Dortsalak**.
4. **Txapelketa** aukeratu eta itsatsi.
5. **🔍 Aurreikusi** → **⬆ Inportatu**.

**Zer egiten du:** txirrindulari bakoitzari dortsala esleitzen dio txapelketa honetan (`TxirrindulariakTxapleketanParteHartzea`). Talde-izenen lerroak (dortsala `0`) eta Excel-erroreak (`#E/E`, `#DIV/0!`) automatikoki baztertzen dira. Txirrindularia existitzen ez bada, sortu egiten da.

> Zutabe osoa (orri zabal osoa) ere itsats daiteke, baina bi zutabe soilik kopiatzea garbiagoa da.

---

## 3 · Etapa emaitzak — "Laburpen taulak" / "Etapak"  📂 CSV inportatu

Etapa bakoitzeko sailkapena (`KarreraSailkapena`) sartzeko.

**Behar den formatua** (goiburuak barne):

```
Sailkapena   Dortsala   Txirrindularia              Puntuak
1            1          POGACAR Tadej               31
2            11         VINGEGAARD Jonas            23
3            41         CARAPAZ Richard            17
```

**Urratsak:**
1. "Laburpen taulak" orriko etapa-blokea kopiatu (edo etapa baten emaitzak bloke gisa prestatu).
2. Admin → **📂 CSV inportatu**.
3. **Datu mota**: `Txirrindulari emaitzak (karrera)`.
4. Itsatsi datuak.
5. **Karrera** (etapa) aukeratu testuinguruan.
6. Zutabeak mapatu (automatikoki lotzen dira: `Sailkapena`, `Txirrindularia`, `Puntuak`; `Dortsala` baztertu egiten da).
7. **🔍 Aurreikusi** → izen berrien kasuan fuzzy egiaztapena egin → **⬆ Inportatu**.

> `Sailkapena`, `Txirrindularia` eta `Puntuak` behar dira. Izenak datu-basekoekin lotzen dira (fuzzy matching); bat ez datozenak egiaztatu.

---

## 4 · Sailkapen finalak — "Sailkapen taula handiak"  📂 CSV inportatu

Txapelketa amaitutakoan, porrolarien azken sailkapena (`TxapelketaEmaitzaPorralariak`).

**Behar den formatua:**

```
Posizioa   Porreroa            Puntuak
1          Keops               184
2          Ra                  168
3          Damba               167
```

**Urratsak:**
1. "Sailkapen taula handiak" orritik **`Porreroa`** eta azken **puntu-zutabea** (+ posizioa) kopiatu.
2. Admin → **📂 CSV inportatu**.
3. **Datu mota**: `Porralari emaitzak (txapelketa)`.
4. Itsatsi datuak, **Txapelketa** aukeratu.
5. Zutabeak mapatu: `Posizioa` ← Posizioa/Sailkapena; `Ezizena` ← Porreroa; `Puntuak` ← puntuak.
6. **🔍 Aurreikusi** → **⬆ Inportatu**.

> `Porreroa` = porra-ezizena (`Ezizena`). "Porra denak" orriko `Izena` zutabea ere balio du, baina orduan zutabea eskuz mapatu behar da (`Izena` → `Ezizena`), `Izena` lehenetsita txirrindulari-izenari lotzen baitzaio.

---

## Ohar teknikoak

- **Izen-lotura (fuzzy matching):** txirrindulari-izenak `ABIZENA Izena` formatuan daude. Sistemak azentuak, herrialde-kodeak eta ordena aldaketak kudeatzen ditu. Bikoiztuak sortuz gero, admin → **🔀 Fusionatu** erabili.
- **Idempotentzia:** apustuak eta dortsalak berriz inportatzea segurua da. Etapa/sailkapen emaitzak, berriz, existitzen badira **saltatu** egiten dira (ez dira gainidazten) — aldatzeko, lehenik ezabatu edo **Taula guztiak** editorean editatu.
- **Atzera egin:** CSV inportazioek "Atzera/Aurrera" dute. Tour Excel inportazioek EZ dute atzera-egiterik (idempotenteak dira; berriro inportatu edo eskuz zuzendu).
- **Puntuazioa:** etapako puntuak (1.=31, 2.=23, 3.=17, 4.=13, 5.=9, 6.=7) `KarreraSailkapena`-n gordetzen dira; porrolarien puntuak "Sailkapenak kalkulatu"-k kalkulatzen ditu apustuetatik.
