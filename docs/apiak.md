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

Logika guztia `admin/lib.php`-n dago; konexioa `admin/db.php`-n (mysqli).
Xehetasunak: [admin-panela.md](admin-panela.md).

## Deployment-oharra

API publikoek kredentzialak **kodean** dituzte, `api/db-read.php`-n (irakurketa-erabiltzailea).
Admin-ek `config.php` erabiltzen du (git-etik kanpo). Ikus [garapena.md](garapena.md).
