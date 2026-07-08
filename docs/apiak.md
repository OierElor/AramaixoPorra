# API geruza

Hiru sarrera-puntu daude: bi publiko (`api/`) eta admin-ekoa (`admin/api.php`).

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

## 3 · `admin/api.php` — kudeaketa API (Basic Auth)

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
| POST | `insert`, `table-update`, `table-delete` | CRUD orokorra |
| POST | `import/startlist`, `import/apustuak`, `import/emaitzak`, … | Datuak inportatu |
| POST | `merge/execute`, `ezizen-lotu`, `porralari-split` | Fusioa / lotura / banaketa |
| POST | `calculate/porralari-sailkapena`, `calculate/txirri-sailkapena` | Sailkapenak kalkulatu |
| POST | `proposals/clear`, `proposals/delete` | Zuzenketak kudeatu |

Logika guztia `admin/lib.php`-n dago; konexioa `admin/db.php`-n (mysqli).
Xehetasunak: [admin-panela.md](admin-panela.md).

## Deployment-oharra

API publikoek (`q.php`, `proposal.php`) kredentzialak **kodean** dituzte (irakurketa-erabiltzailea).
Admin-ek `config.php` erabiltzen du (git-etik kanpo). Ikus [garapena.md](garapena.md).
