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

Sarrera bisuala: azalak/karrusela (`data/Portadak/`). Hemendik menuaren bidez nabigatzen da.

## Lasterketen atalak

### Itzuli handiak — `tour/`, `giro/`, `vuelta/`

Hiru itzuliek **egitura bera** dute, **txantiloi partekatu** baten bidez. HTML orriak
**stub uniformeak** dira (denak berdinak); edukia bidetik eta konfiguraziotik sortzen da:

| Fitxategia | Zertarako |
|---|---|
| `js/itzuliak.js` | **Konfigurazio bakarra**: kirol-izenak + urteko `{ id, arauak, dortsalak, porrak, profilaDir, profilaIrudia, etapak }` |
| `js/itzulia-orria.js` | **Urte-orria** marrazten du (`/tour/2026/`): nabigazioa, izenburua, PDFak, ibilbide-irudia, etapa-menua eta sailkapen-taulak |
| `js/etapa-orria.js` | **Etapa-orria** marrazten du (`/giro/2026/etapa3`): nabigazioa, etaparen profila, etapako emaitzak |

- Urte-orriak: **Porra sailkapena** (`loadPorra`), **Txirrindulariak** (`loadCyclists`),
  **Etapaz etapa** (`loadStages`).
- Etapa-orriak: **Etapako emaitzak** (`loadStageByNumber`).
- **Taulak soilik erakusten dira** txapelketa DBan existitzen bada **eta karrerarik badu**;
  bestela "ez dago daturik oraindik" oharra (adib. oraindik hasi gabeko urteak).
- PDFak, ibilbide-irudia eta etapa-menua konfigurazioan daudenean bakarrik agertzen dira.

**Urte berri bat gehitzeko:** `js/itzuliak.js`-en sarrera bat gehitu eta stub-ak kopiatu
(`index.html` + `etapa1..21.html`). Ez dago HTML markaketarik bikoiztu beharrik.

Estaldura: `tour/` eta `giro/` → 2023-2026 · `vuelta/` → 2020-2026 · etapa-orriak: 2026.

### Klasikoak — `klasikak/`

Klasikoak lasterketa independenteak dira (egun bakarrekoak). Urte bakoitzeko karpetan
**lasterketa bakoitzak bere HTML orria** du (adib. `klasikak/2025/amstel-gold-race.html`):

- **Lasterketaren emaitzak** — `dbLoader.loadKlasikaResults(...)` (2024/2025) edo
  `dbLoader.loadKlasikaRace(...)` (2026, zutabe gehiagorekin).

Urteko lasterketa kopurua: 2024 → 21 · 2025 → 27 · 2026 → 27.

## Datuak kargatzeko motorra — `js/db-loader.js`

`DBLoader` klaseak `/api/q.php`-ra bidaltzen ditu SELECT kontsultak eta emaitzak
taulatan errendatzen ditu. Metodo publiko nagusiak:

| Metodoa | Zertarako |
|---|---|
| `query(sql, params)` | SELECT kontsulta orokorra (JSON itzultzen du) |
| `loadPorra(txapId, tableId)` | Porralarien sailkapen ofiziala erakutsi |
| `loadCyclists(...)` | Txirrindularien sailkapena erakutsi |
| `loadStages(...)` / `loadStageByNumber(...)` | Etapen emaitzak (itzuliak) |
| `loadKlasikaResults(...)` / `loadKlasikaRace(...)` | Klasika baten emaitzak |

Emaitza ofizialik ez badago, `_porraFallback` / `_cyclistFallback`-ek azken kalkulatutako
sailkapena erakusten dute (`TxapelketaSailkapena*` tauletatik).

## Tresnak — `tresnak/`

Analisi-tresna interaktiboak. Guztiek `tresna-komuna.js`-ko **`Tresna`** objektua
erabiltzen dute (kontsultak, autocomplete, Chart.js grafikoak). Xehetasunak:
[tresnak.md](tresnak.md).

## Zuzenketak — `tresnak/zuzenketak/`

Porralariek akatsak jakinarazteko formulario publikoa. `POST /api/proposal.php` bidez,
proposamena emailera eta `admin/zuzenketak.log`-era iristen da. Ez du datu-basea ukitzen.
Ikus [apiak.md](apiak.md) eta [admin-panela.md](admin-panela.md#zuzenketak).
