# Webgune publikoa

Guneak framework-ik gabeko HTML/CSS/JS erabiltzen du. Orri bakoitza estatikoa da, baina
datuak exekuzio-garaian kargatzen ditu `/api/q.php`-ren bidez.

## Orri komunak: `js/layout.js`

Orri guztiek `layout.js` kargatzen dute. Honek DOM-ean **goiburua** eta **oina**
injektatzen ditu:

- **Goiburua:** logoa (`Aramaixo Porra` → `/`) + nabigazio-menua:
  `Tour · Giro · Vuelta · Klasikak · Tresnak`. Uneko atala nabarmentzen da.
- **Oina:** harremanetarako emaila (`harremana@aramaixoporra.eus`) + `⚙` esteka
  diskretua (opazitate baxua) `admin/`-erako.

Estilo guztiak `css/styles.css`-etik datoz (kolore-aldagaiak `:root`-en; diseinu
responsivea; taula-estiloak).

## Hasiera-orria — `index.html`

Sarrera bisuala: azalak/karrusela (`data/portadak/`). Hemendik menuaren bidez nabigatzen da.

## Lasterketen atalak — `tour/`, `giro/`, `vuelta/`, `klasikak/`

**Lau txapelketa-motek egitura BERA dute**, **txantiloi partekatu** baten bidez. HTML
orriak **stub uniformeak** dira (denak berdin-berdinak); edukia bidetik eta
konfiguraziotik sortzen da:

| Fitxategia | Zertarako |
|---|---|
| `js/txapelketak.js` | **Konfigurazio bakarra**: txapelketa-izenak + urteko `{ id, arauak, dortsalak, porrak, profilaDir, profilaIrudia, irudiak }` |
| `js/txapelketa-orria.js` | **Urte-orria** marrazten du (`/tour/2026/`, `/klasikak/2026/`): nabigazioa, izenburua, PDFak, ibilbide-irudia eta sailkapen-taulak |

Urte-orri bakoitzak hiru taula ditu: **Porra sailkapena** (`loadPorra`),
**Txirrindulariak** (`loadCyclists`) eta karreren **akordeoia** (`loadKarrerak`).

**Karreren emaitzak erakusteko sistema BAKARRA dago**: urte-orriko **akordeoia**
(itzulietan "ETAPAZ ETAPA", klasikoetan "LASTERKETAK"). Karrera baten panela zabaltzean
bere **profil/ibilbide irudia** (baldin badago) eta emaitza-taula erakusten dira.
**Ez dago karrera bakoitzeko orririk.**

- Akordeoiko **zutabeak datuen arabera** egokitzen dira karreraz karrera:
  Pos · Zbk · Txirrindularia · Puntuak · Zenbatek?
- Klasikoetan **UCI kategoria** txartel koloredun gisa agertzen da goiburuan
  (itzulietako `Kategoria = 'Etapa'` ez da erakusten).
- **Taulak soilik erakusten dira** txapelketa DBan existitzen bada **eta karrerarik badu**;
  bestela "ez dago daturik oraindik" oharra (adib. oraindik hasi gabeko urteak).
- **Datu-basea da egia-iturria**: akordeoian `Karrerak` taulako karrerak bakarrik
  agertzen dira. Egutegiko lasterketa bat oraindik sartu gabe badago, ez da ikusiko;
  admin paneleko **Karrerak** inportatzailearekin gehitu daiteke.
- **`Emaitzarik_Ez = 1` markatutakoak EZ dira agertzen**: inoiz emaitzarik izango ez badute
  (adib. bertan behera utzitako etapa), panel huts iraunkorra litzateke. Admin panelean
  markatzen da (*Karrera motak* atala). Grafikoetan ere ez dira zenbatzen.
- **Emaitzak dituen karrera bat EZ da bestela inoiz ezkutatzen**: iragazkia «Kategoria beteta
  **EDO** emaitzak baditu» da. Segurtasun-sarea da, Kategoriarik gabeko karrera bat
  (eskuz sortua, adib.) isilean gal ez dadin. `js/tresna-komuna.js`-k iragazki bera
  darabil, tresnetako puntuak akordeoiarekin eta sailkapenarekin bat etor daitezen.
- PDFak eta ibilbide-irudia konfigurazioan daudenean bakarrik agertzen dira.

**Karrera-irudiak.** Itzulietan izena sistematikoa da (`Etapa{N}.jpg`, `.png`
ordezko gisa). **Lehentasuna:** (1) karreraren **`Profil_Irudia`** lotura esplizitua
(admin → Fitxategiak → profil-irudi bati karrera lotu); (2) klasikoen `irudiak` mapa;
(3) izen-konbentzioa `Etapa{Ordena}.jpg`, non **`{N}` = karreraren `Ordena`** (benetako
etapa-zenbakia, ez zerrendako posizioa: irudiak ez dira jarraituak, adib. `Etapa1, 3, 4, 9…`).
Irudirik ez badago, isilean ezkutatzen da. Klasikoetan izenak ez dira
sistematikoak (`paris roubaix.png`, `Braranconne.png`…), beraz konfigurazioan
`irudiak: { Karrerak_ID: 'fitxategia.png' }` mapa bat behar da.

**Urte berri bat gehitzeko:** `js/txapelketak.js`-en sarrera bat gehitu eta `index.html`
stub-a kopiatu. Ez dago HTML markaketarik bikoiztu beharrik.

Estaldura: `tour/` eta `giro/` → 2023-2026 · `vuelta/` → 2020-2026 · `klasikak/` → 2024-2026.

## Datuak kargatzeko motorra — `js/db-loader.js`

`DBLoader` klaseak `/api/q.php`-ra bidaltzen ditu SELECT kontsultak eta emaitzak
taulatan errendatzen ditu. Metodo publiko nagusiak:

| Metodoa | Zertarako |
|---|---|
| `query(sql, params)` | SELECT kontsulta orokorra (JSON itzultzen du) |
| `loadPorra(txapId, tableId)` | Porralarien sailkapen ofiziala erakutsi |
| `loadCyclists(...)` | Txirrindularien sailkapena erakutsi |
| `loadKarrerak(txapId, containerId, kolorea, irudiFn)` | Karreren akordeoia — **itzulietarako eta klasikoetarako berdina** |

Emaitza ofizialik ez badago, `_porraFallback` / `_cyclistFallback`-ek azken kalkulatutako
sailkapena erakusten dute (`TxapelketaSailkapena*` tauletatik).

## Tresnak — `tresnak/`

Analisi-tresna interaktiboak. Guztiek `tresna-komuna.js`-ko **`Tresna`** objektua
erabiltzen dute (kontsultak, autocomplete, Chart.js grafikoak). Xehetasunak:
[tresnak.md](tresnak.md).

## Aurre-porrak — `tresnak/porra-bidali/`

Porralariek beren porra **aurretik** bidal dezakete, baina **adminak ireki dituen
txapelketetan bakarrik** (`Txapelketak.Porra_Irekita = 1`).

- Txapelketa hautatu → bere **startlist**-etik 15 (edo 25) txirrindulari aukeratu
  (`Tresna.autocomplete()` + `.grafiko-chip` txipak). Kopurua osatu arte ezin da bidali.
- `POST /api/porra.php` → emaila + `admin/aurre-porrak.log`. **Ez du datu-basea idazten.**
  Balidazio guztia zerbitzarian errepikatzen da.
- **Anboto abisua** hiru lekutan agertzen da: formularioan, arrakasta-mezuan eta emailean.
  Aurre-porra ez da behin betikoa; presentzialki joan behar da beti.
- **Banner-a urte-orrian**: txapelketa irekita badago, `txapelketa-orria.js`-k ohar bat
  erakusten du esteka zuzenarekin (`/tresnak/porra-bidali/?txap=17`). Kontsulta **bereizia**
  da, txapelketak oraindik karrerarik ez badu ere banner-ak agertu behar duelako.

Adminak "Aurre-porrak" atalean berrikusten ditu eta handik inportatzen (ikus
[admin-panela.md](admin-panela.md)).

## Zuzenketak — `tresnak/zuzenketak/`

Porralariek akatsak jakinarazteko formulario publikoa. `POST /api/proposal.php` bidez,
proposamena emailera eta `admin/zuzenketak.log`-era iristen da. Ez du datu-basea ukitzen.
Ikus [apiak.md](apiak.md) eta [admin-panela.md](admin-panela.md#zuzenketak).
