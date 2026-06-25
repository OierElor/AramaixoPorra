# MySQL migrazioa — ezartzeko gida

Gunea SQLite (sql.js, nabigatzailean) → **MySQL + PHP API** pasatu da.
Orain bezeroak ez du DB osoa deskargatzen: `/api/q.php`-ri SELECT kontsultak
bidaltzen dizkio eta JSON jasotzen du.

## Pausoak zerbitzarian

### 1. Datu-basea sortu
- Hosting-eko panelean (cPanel / phpMyAdmin) **MySQL datu-base bat** sortu, adib. `aramaixo_porra`.
- Sortu **erabiltzaile bat** eta lotu datu-baseari.
  - **GOMENDIOA (segurtasuna)**: erabiltzaile honi **SELECT baimena soilik** eman
    (datuak publikoak dira, baina idazketa ezgaituz, API-a ezin da gehiegikeriaz erabili).
  - Datuak inportatzeko, aldi baterako baimen guztiak eman; gero SELECT-era mugatu.

### 2. Eskema eta datuak inportatu
phpMyAdmin-en datu-basea hautatu → **Import** fitxa → igo, ordena honetan:
1. `db/schema.sql`  (taulak sortzen ditu)
2. `db/data.sql`    (datuak sartzen ditu, ~1,1 MB)

Edo komando-lerrotik:
```
mysql -u ERABILTZAILEA -p DATUBASEA < db/schema.sql
mysql -u ERABILTZAILEA -p DATUBASEA < db/data.sql
```

### 3. API-a konfiguratu
`api/q.php` editatu eta goiko konfigurazioa bete:
```php
$DB_HOST = 'localhost';
$DB_NAME = 'aldatu_datubase_izena';
$DB_USER = 'aldatu_erabiltzailea';   // SELECT-soilik gomendatua
$DB_PASS = 'aldatu_pasahitza';
```
Igo `api/` karpeta osoa zerbitzarira (gunearen erroan: `/api/q.php`).

### 4. Probatu
- Ireki `https://aramaixoporra.eus/` eta nabigatu txapelketa/tresna batera.
- Arazorik bada, ireki nabigatzaileko kontsola (F12) edo zuzenean probatu API-a:
  ```
  curl -X POST https://aramaixoporra.eus/api/q.php \
    -H "Content-Type: application/json" \
    -d '{"sql":"SELECT Txapelketa_ID, Izena FROM Txapelketak ORDER BY Urtea DESC","params":[]}'
  ```
  JSON zerrenda bat itzuli behar du.

## Datuak eguneratzeko (Excel/ODS → MySQL)
- Taula sinpleak: Excel → CSV → phpMyAdmin **Import** (taula egokian).
- Porra-inportazio konplexua (izenak ID-etara lotzea, e.a.): lehengo logika
  MySQL-era idatziz egokitu beharko litzateke (script bat edo zure aplikazioa).

## Ezaugarri teknikoak
- `api/q.php`: SELECT/WITH soilik, kontsulta bakarra, prestatutako adierazpenak,
  `sql_mode='ANSI_QUOTES'` (komatxo bikoitzak identifikadore gisa).
- Bezeroa: `js/db-loader.js` → `fetch('/api/q.php')`. Orriek eta tresnek ez dute
  aldaketarik behar (dena `dbLoader.query()` / `Tresna.q()` bidez doa).
- `robots.txt`-ek `/api/` blokeatzen du crawler-entzat.
- Izen-aldaketak SQLite→MySQL: `Zenbatek?`→`Zenbatek`,
  `Puntuak_Sailkapen nagusia`→`Puntuak_Sailkapen_nagusia`, `Zenbat Porra`→`Zenbat_Porra`.

## Atzera bueltatzeko
Aldaketak baino lehen, `js/db-loader.js`-en SQLite/sql.js bertsioa git historian dago.
