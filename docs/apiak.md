# API geruza

Lau sarrera-puntu daude: hiru publiko (`api/`) eta admin-ekoa (`admin/api.php`).
`api/db-read.php` ez da endpoint-a: **konexio partekatua** da (ikus behean).

## 0 · `api/db-read.php` — konexio partekatua (ez da endpoint-a)

`api_pdo()` eta `api_select($sql, $params)` eskaintzen ditu: SELECT-soilik PDO konexioa,
`ANSI_QUOTES` moduan. `q.php`-k eta `porra.php`-k hemendik hartzen dituzte kredentzialak,
**leku bakarrean** egon daitezen. Zuzeneko HTTP sarbidea `api/.htaccess`-ek blokeatzen du.

## 1 · `api/q.php` — kontsulta publikoa (SELECT soilik)

Webgune publikoak (db-loader.js, tresna-komuna.js) datuak irakurtzeko erabiltzen duen API-a.

- **Metodoa:** `POST` JSON-arekin: `{ "sql": "SELECT …", "params": [...] }`.
- **Segurtasuna** (funtsezkoa, publikoa baita):
  - `SELECT`/`WITH` kontsultak **soilik**; `INSERT/UPDATE/DELETE/DROP/…` debekatuta (regex bidez).
  - Kontsulta **bakarra** (`;` debekatua).
  - **Prestatutako adierazpenak** (parametroak lotuta, PDO).
  - `ANSI_QUOTES` moduan (komatxo bikoitzak = identifikadoreak, SQLite estiloa).
  - **Gomendioa:** SELECT-soilik baimena duen MySQL erabiltzaile bat (`config`-eko `q.php`).
- **Erantzuna:** emaitza-lerroak JSON array gisa.

## 2 · `api/proposal.php` — zuzenketa-proposamenak (publikoa, DB gabe)

Porralariek akatsak jakinarazteko (`tresnak/zuzenketak/`-etik). **Ez du SQL-ik erabiltzen.**

- **Metodoa:** `POST` JSON-arekin: `{ mota, testua, hp }`.
- **Prozesua:**
  1. `mota` zerrenda zurian egiaztatu; `testua` derrigorra eta ≤ 1000 karaktere.
  2. **Honeypot** (`hp`): beteta badago (bot-a) → isilik onartu, ez gorde.
  3. **Anti-spam:** IP beretik azken orduan > 10 sarrera → 429.
  4. **Emaila** bidali `harremana@aramaixoporra.eus`-era (`mail()`).
  5. Proposamena `admin/zuzenketak.log`-en erantsi (testu laua, `=====` bereizlearekin).
- **Motak:** `apustu okerra`, `izen aldaketa`, `porra-lotura`, `fusioa`, `bestelakoa`.
- Admin panelak fitxategi hori irakurtzen du ("Zuzenketak" atala).

## 3 · `api/porra.php` — aurre-porrak (publikoa, DBan idatzi GABE)

Porralariek beren porra aurretik bidaltzeko (`tresnak/porra-bidali/`-tik). Datu-basea
**irakurri** egiten du (balidatzeko), baina **ez du ezer idazten**.

- **Metodoa:** `POST` JSON-arekin:
  `{ txapelketa_id, ezizena, harremana?, oharrak?, dortsalak: [int], hp }`.
- **Balidazioa** (dena zerbitzarian; bezeroan bakarrik egitea ez litzateke nahikoa):
  1. **Honeypot** (`hp`): beteta badago → isilik onartu, ez gorde.
  2. **Anti-spam:** IP beretik azken orduan ≥ 10 sarrera → `429`.
  3. `Txapelketak.Porra_Irekita = 1` **derrigorra** → bestela `403`.
     Existitzen ez den txapelketak errore bera itzultzen du (ez da informaziorik iragazten).
  4. Dortsal kopurua = `Txapelketak.Apustu_Kopurua` (NULL bada, 15) → bestela `400`.
  5. Bikoizturik ez → `400`. Dortsal guztiak txapelketaren **startlist**-ean → bestela `400`.
- **Eremuak lerro bakarrera** murrizten dira (lerro-jauziak, kontrol-karaktereak eta
  ezizeneko `|` kenduta). Ezinbestekoa da: bestela ezizen batek log-eko goiburua,
  `DORTSALAK:` lerroa edo `\n=====\n` bereizlea faltsutu litzake.
- **Emaitza:** emaila + `admin/aurre-porrak.log`-en bloke bat:

```
[data] TXAP: 17 | Tour De France 2026 | EZIZENA: xxx | IP: 1.2.3.4
HARREMANA: ...            (aukerakoa)
DORTSALAK: 1,21,31,...    ← adminak hemendik berreraikitzen du apustua
   1  Tadej Pogačar
OHARRAK: ...              (aukerakoa)
=====
```

- Erantzunak **Anboto abisua** darama (`abisua`), formularioan erakusteko.
- Admin panelak fitxategia irakurtzen du ("Aurre-porrak" atala) eta handik inporta daiteke.

## `api/ezarpenak.php` — karpeta-mapa + tresna publikoen ikusgaitasuna (publikoa, irakurketa soilik)

Bi ezarpen mota itzultzen ditu:

```json
{
  "karpetak": { "arauak": "arauak", "dortsalak": "dortsalak",
                "porrak": "porrak", "profilak": "profilak" },
  "tresnak": [
    { "id": "bero-mapa", "ikonoa": "🟩", "izena": "Aukeren bero-mapa",
      "azalpena": "…", "bidea": "/tresnak/bero-mapa/", "ikusgai": true }
  ]
}
```

**`karpetak`** — fitxategi-mota bakoitza zein karpetatan dagoen:
- `js/txapelketak.js`-ek mapa hau erabiltzen du URLak eraikitzeko
  (`TXAPELKETAK.url(mota, …)` → `/data/<karpeta>/<fitxategia>`); `txapelketa-orria.js`-k
  **errendatu aurretik** kargatzen du (`karpetakKargatu()`).
- Adminak karpeta bat aldatzen badu (panela → **Fitxategiak → Mota bakoitzaren karpeta**),
  gunea **berehala** moldatzen da; koderik ez da ukitu behar.
- Karpeta-izenak balidatuta itzultzen dira (letrak/zenbakiak/zuriuneak/`-`/`_`): bezeroak
  URL bat eraikitzen du haiekin, beraz `../` gisako bide-zeharkatzea ukatzen da.

**`tresnak`** — tresna publikoen KATALOGO OSOA (`api/tresna-katalogoa.php`, iturri
bakarra) + `ikusgai` ebatzita:
- `tresnak/index.html`-ek hemendik marrazten du txartel-sareta (ez-ikusgai dagoena
  desagertu egiten da). `js/txapelketak.js`-eko `karpetakKargatu()`-k ere kargatzen du
  (`TXAPELKETAK.tresnaIkusgai(id)`), urte-orriko «PORRA BIDALI» bannerra bezalako
  barne-estekak ezkutatzeko.
- Adminak tresna bat itzaltzen badu (panela → **Webgunea**), gunea **berehala** moldatzen
  da.
- ⚠️ **Esteka bakarrik ezkutatzen da**: URL zuzenak funtzionatzen jarraitzen du. EZ da
  benetako sarbide-blokeoa (webgune osoa fitxategi estatikoak dira, ikus
  [egitura.md](egitura.md)); ez dago zerbitzari-mailako gaitasunik orri bat blokeatzeko.
- Katalogoko `oharra` (adminak zergatik itzali zuen gogoratzeko nota) **EZ da inoiz
  itzultzen hemendik** — endpoint hau auth GABEA da eta nota pribatua da. Adminak
  `admin/api.php` → `ezarpenak` (GET, auth-ekin) bidez ikusten du.
- ⚠️ `sariak` (`tresnak/sariak/`) NAHITA dago katalogotik KANPO: ez da adminetik
  kudeagarria, ez eta pizteko aukera gisa agertzen.

Bi ezarpen motek iturri komuna dute: `admin/ezarpenak.json` (zerbitzari-jabetzakoa,
git-etik kanpo). Fitxategirik ez badago edo hondatuta badago, **lehenetsiak** itzultzen
dira (karpeta lehenetsiak + tresna GUZTIAK ikusgai) → **gunea ez da inoiz hausten**.

> **portadak** (azalak + favicon) **EZ da konfiguragarria**: `<head>`-etako esteka
> estatikoak dira (35 orritan) eta ezin dira exekuzio-garaian ebatzi. Bide finkoa:
> `/data/portadak/`.

## Fitxategi-kudeatzailea (`admin/api.php` → `files*`)

Admin paneleko "Fitxategiak" atalak `data/`-ko fitxategiak kudeatzen ditu (`files_*`
funtzioak `admin/lib.php`-n). **Segurtasun-kritikoa** da: `data/` publikoki zerbitzatzen
da eta ez dago erroko `.htaccess`-ik, beraz `.php` bat igotzea kode-exekuzioa litzateke.

Hiru babes-geruza:
1. **Luzapen-zerrenda zuria** (`FILES_ALLOWED_EXT`): `jpg jpeg png gif webp pdf` soilik.
2. **`data/.htaccess`** (kodeak sortua/mantendua, `_ensure_data_guard()`): script-etarako
   sarbidea ukatuta — `Options -Indexes` + `<FilesMatch "\.(php…|htaccess)$"> Require all
   denied </FilesMatch>`.
   > ⚠️ **EZ `php_flag`**: zerbitzaria **PHP-FPM** da eta `php_flag` `.htaccess`-ean
   > **HTTP 500** eragiten du karpeta osorako. Guardia auto-sendatzailea da: edukia falta
   > bada edo desberdina bada, berridatzi egiten du.
3. **Bide-konfinamendua** (`_safe_data_path`): `realpath`-ez `data/` azpian dagoela
   egiaztatu; `..`, `/`, `\`, byte nuluak eta hasierako `.` ukatu (bide-zeharkatzea galarazi).

`data/.htaccess` bera ezin da ezabatu/berrizendatu (babestua). Igoerak multipart dira
(`$_FILES`); router-ak JSON gorputza hutsik uzten du multipart-ean, beraz `$_GET`/`$_FILES`
erabiltzen dira. `data/` **git-en trackeatuta** dago (ikus [garapena.md](garapena.md)).

## 4 · `admin/api.php` — kudeaketa API (Basic Auth)

Admin panelaren backend osoa. HTTP Basic Auth-ez babestua (`config.php`-ko
`auth_user`/`auth_pass`). `.htaccess`-ek `api/*` → `api.php?_path=…` bideratzen du.

Route-ak (adibide adierazgarriak; ez dira denak):

| Metodoa | Bidea | Funtzioa |
|---|---|---|
| GET | `porralariak`, `txirrindulariak`, `txapelketak`, `karrerak`, … | Zerrendak irakurri |
| GET | `table/{izena}` | Taula gordina irakurri (editore generikoa) |
| GET | `data-health`, `possible-dups` | Datu-osasuna |
| GET | `export?txapelketa_id=` | Txapelketa baten babeskopia (JSON) |
| GET | `proposals` | Zuzenketa-proposamenak (log-etik) |
| GET | `aurre-porrak` | Aurretik bidalitako porrak (log-etik, parseatuta) |
| POST | `insert`, `table-update`, `table-delete` | CRUD orokorra |
| POST | `import/startlist`, `import/apustuak`, `import/emaitzak`, … | Datuak inportatu |
| POST | `merge/execute`, `ezizen-lotu`, `porralari-split` | Fusioa / lotura / banaketa |
| POST | `calculate/porralari-sailkapena`, `calculate/txirri-sailkapena` | Sailkapenak kalkulatu |
| POST | `proposals/clear`, `proposals/delete` | Zuzenketak kudeatu |
| POST | `aurre-porrak/clear`, `aurre-porrak/delete` | Aurre-porrak kudeatu |
| GET | `files?dir=` | `data/` karpeta baten edukia (fitxategiak + azpikarpetak) |
| POST | `files/upload?dir=` | Fitxategiak igo (multipart, `$_FILES`) |
| POST | `files/delete`, `files/rename`, `files/mkdir` | Fitxategiak kudeatu |
| GET/POST | `ezarpenak` | Karpeta-mapa (+ tresna-ikusgaitasuna, `oharra` barne) irakurri / karpeta-mapa gorde (`admin/ezarpenak.json`) |
| POST | `ezarpenak/tresnak` | Tresna publikoen ikusgaitasuna + oharra gorde |

Logika guztia `admin/lib.php`-n dago; konexioa `admin/db.php`-n (mysqli).
Xehetasunak: [admin-panela.md](admin-panela.md).

## Deployment-oharra

API publikoek kredentzialak **kodean** dituzte, `api/db-read.php`-n (irakurketa-erabiltzailea).
Admin-ek `config.php` erabiltzen du (git-etik kanpo). Ikus [garapena.md](garapena.md).
