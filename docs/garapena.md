# Garapena, konfigurazioa eta deployment-a

## Deployment-a (git → Plesk)

Webgunea **git bidez** deployatzen da:

```
Garapena (lokala)          GitHub                    Hostalia / Plesk
─────────────────          ──────                    ────────────────
git commit + push  ───►  OierElor/AramaixoPorra  ───►  Plesk pull hook
                                                        (httpdocs/ eguneratu)
```

- Errepositorioaren erroa = zerbitzariko `httpdocs/`.
- `git push` egin ondoren, **Plesk-en pull egin behar da** (Git atala → "Actualizar"),
  aldaketak zerbitzarian ager daitezen.
- **Datu-basea EZ da git bidez kudeatzen.** Aldaketak phpMyAdmin edo admin panelaren
  bidez egiten dira zuzenean. `db/`-ko SQL fitxategiak erreferentzia hutsak dira.

## Konfigurazioa eta kredentzialak

**Kredentzialak EZ daude dokumentazioan, ezta git-en ere.** Non dauden:

| Non | Zer | Git-en? |
|---|---|---|
| `admin/config.php` | MySQL + admin auth kredentzialak (`return [...]`) | ❌ (.gitignore) |
| `api/q.php` | Irakurketa-erabiltzailearen MySQL kredentzialak (kodean) | ✔ (irakurketa soilik) |
| `api/proposal.php` | Ez du DB-rik; email helbidea eta log-bidea besterik ez | ✔ |

`admin/config.php` **zerbitzarian eskuz sortu behar da** (git-ek ez du igotzen).
Eredua: `admin/config.example.php`. Behar diren balioak: MySQL host/user/pass/name +
`auth_user`/`auth_pass`.

> **Segurtasuna:** `q.php`-ko erabiltzaileak SELECT-soilik baimena izan beharko luke.
> Ezein kredentzial ez publikatu errepositorio publiko batean.

## PHP eskakizunak

- PHP 7.4+ luzapenekin: `mysqli` (admin), `PDO_mysql` (`q.php`), `mbstring`.
- `intl` (Normalizer) **aukerakoa** — gabe ere badabil (azentu-mapa propioa `lib.php`-n).
- `mail()` funtzioa (zuzenketen jakinarazpenetarako).

## Probak lokalean — `./proba.sh` ⭐

**Webgune osoa zure ordenagailuan, DATU ERREALEKIN, ezer publiko egin gabe.**

```bash
./proba.sh          # http://127.0.0.1:8777
./proba.sh 8080     # beste ataka bat
```

Nabigatzailean ireki eta aldaketak **ikusi** `git push` egin AURRETIK. Ctrl+C gelditzeko.

### Nola dabilen

MySQL-era **ezin da lokaletik konektatu** (urruneko sarbidea mugatua). Baina `api/q.php`
**endpoint publikoa** da eta SELECT soilik onartzen du. Beraz `dev/router.php`-k
datu-kontsultak **zuzeneko API publikora birbidaltzen ditu**: kodea lokala, datuak errealak.

| Bidea | Zer gertatzen den |
|---|---|
| `/api/q.php`, `/api/ezarpenak.php` | **Proxy** zuzenera (irakurketa hutsa) |
| `/admin/api/*` | **Proxy**, **GET soilik**; auth `admin/config.php`-tik |
| `/api/porra.php`, `/api/proposal.php` | **BLOKEATUTA** — emaila + log erreala idatziko lukete |
| Admin `POST`/`PUT`/`DELETE` | **BLOKEATUTA** (403 + toast argia) |
| HTML/JS/CSS | Zure fitxategi lokalak, **cache gabe** (`?v=` ukitu beharrik ez) |

Orri guztietan **🔧 PROBA** bereizgarri bat agertzen da behe-eskuinean: lokala eta produkzioa
ezin dira nahastu.

### Zer EZIN da lokalean probatu

- **Idazketa-fluxuak**: aurre-porra bidaltzea, zuzenketak, eta **admin idazketa guztiak**
  (sortu/editatu/ezabatu/inportatu/kalkulatu). Horiek zerbitzarian probatu behar dira, lehen bezala.
- **`api/q.php`-ren beraren logika** (zuzenekoa erabiltzen da) eta **migrazioak** (phpMyAdmin).
- Grafikoek eta admin letra-tipoek **internet** behar dute (Chart.js / Google Fonts CDNtik).

### Segurtasuna

`127.0.0.1`-era lotuta soilik (ez sarera). `dev/router.php`-k `cli-server` SAPI-a bakarrik
onartzen du eta `dev/.htaccess`-ek webetik sarbidea ukatzen du → zerbitzarira igoz gero **inerte**.
Kredentzialak lehendik dagoen `admin/config.php`-tik irakurtzen dira; ez dira inoiz logeatzen.

### Lan-fluxua (aldaketak publiko egin gabe)

```
git switch -c aldaketa-berria   →   editatu   →   ./proba.sh (ikusi)
      →   gustura?   →   main-era merge   →   push   →   Plesk pull
```

### Beste egiaztapenak

- **PHP sintaxia:** `php -l api/q.php`, `php -l admin/lib.php`, `php -l admin/api.php`.
- **JS:** giltza-balantzea egiaztatu (node ez badago instalatuta) edo `./proba.sh`-rekin probatu.
- **Inportazio-parser-ak:** benetako Excel-datuen aurka baliozkotu daitezke (adib. Python-era eramanda).

## Nabigatzailearen cachea (⚠ garrantzitsua)

Zerbitzariak **ez du `Cache-Control` goibururik bidaltzen `.js` fitxategietan**, eta
`css/styles.css`-ek `max-age=86400` du (egun bat). Beraz nabigatzaileek bertsio
zaharrak berrerabil ditzakete deploy baten ondoren.

Hori bereziki arriskutsua da fitxategi **batzuk** izenez aldatzen direnean eta beste
batzuk ez: izen berriak freskoak ekartzen dira, izen zaharrak cachetik. Emaitza:
`db-loader.js` zaharra + `txapelketa-orria.js` berria → `loadKarrerak is not a
function`, eta karreren akordeoia **hutsik** agertzen da errore-mezurik gabe.

**Babesa:** urte-orrien stub-ek bertsio-parametroa daramate:

```html
<script src="/js/db-loader.js?v=20260710"></script>
<link rel="stylesheet" href="/css/styles.css?v=20260710">
```

`js/db-loader.js`, `js/txapelketak.js`, `js/txapelketa-orria.js`, `js/layout.js` edo
`css/styles.css` aldatzean, **`?v=` eguneratu behar da orri publiko GUZTIETAN** (data bat
nahikoa da). Bestela erabiltzaile batzuek kode zaharra ikusiko dute.

**Automatikoki — `./bump-version.sh`:** ez eguneratu eskuz. Script honek `?v=YYYYMMDD`
git-trackeatutako HTML/PHP guztietan aldatzen du komando bakarrean (35 fitxategi, 145 aipamen):

```bash
./bump-version.sh            # gaurko data (YYYYMMDD)
./bump-version.sh 20260801   # data zehatza
```

Ondoren `git diff --stat` begiratu, commit + push (gero Plesk pull). `admin/index.html`-ek
`?v=`-rik ez du (index.php-k zerbitzatzen du) → ez da ukitzen. **Deploy-fluxua orri publiko
bat aldatzean:** editatu → `./bump-version.sh` → commit → push → Plesk pull.

> Aukera iraunkorragoa: erroko `.htaccess` batean `Cache-Control: no-cache`
> ezartzea `.js`/`.css`-entzat (ETag bidezko 304 merkeak). Hostalia/Plesk-en
> egiaztatu behar da fitxategi estatikoak Apache-tik pasatzen diren.

## Git errepositorioa

- **Remote:** `https://github.com/OierElor/AramaixoPorra.git`, `main` adarra.
- **Baztertuta** (.gitignore): `admin/config.php`, `admin/*.log`, `admin/ezarpenak.json`, **`data/.htaccess`**.
- **Commit-mezuak:** euskaraz, laburrak.

## `data/` — git-en trackeatuta (segurua git-pull-arekin)

`data/` osoa **git-en dago** (irudiak, PDFak, etapa-profilak). Hori da eredu **segurua**
git-pull bidezko hedapenarekin: fitxategiak commit → push → Plesk pull bidez heltzen dira,
lokala eta zerbitzaria sinkronizatuta.

- **Admin paneleko Fitxategiak** atalak zerbitzariko `data/` zuzenean kudeatzen du
  (igo/ezabatu/berrizendatu). Igoera berriak zerbitzarian **untracked** geratzen dira;
  ez dute pull-talkarik sortzen `data/`-ra commit-ik egiten ez badugu. Babeskopiarako,
  eskuz `git add` daitezke.
- **`data/.htaccess`** git-etik kanpo dago (gitignore); **kodeak sortzen/mantentzen du**
  (`_ensure_data_guard()`, `admin/lib.php`) edozein fitxategi-eragiketatan.
  - ⚠️ **EZ `php_flag`**: zerbitzaria **PHP-FPM** da, eta `php_flag` `.htaccess`-ean
    **HTTP 500** eragiten du karpeta osorako. Babesa `<FilesMatch "\.(php…)$"> Require all
    denied </FilesMatch>` da (script-etarako sarbidea 403), `admin/.htaccess`-ek darabilena
    bezala. Auto-sendatzailea: edukia desberdina bada berridazten du.
- **Igoera-mugak**: Plesk-eko PHP settings-etan `upload_max_filesize` eta `post_max_size`
  handitu (adib. 32M). Muga gaindituz gero, panelak «zerbitzariaren muga gaindituta» dio.

> **Ikasgaia:** `data/` git-etik ateratzea (untrack) arriskutsua da git-pull hedapenarekin —
> pull-ak zerbitzariko fitxategiak ezaba ditzake. Mantendu `data/` git-en.

## Cruft / garbiketa-oharrak

- **`$file`** (erroan): fitxategi zahar galdua (`csv-loader.js` desagertua aipatzen du).
  Ezaba daiteke arriskurik gabe.
- **`Excelak ikusteko/`**: administratzailearen jatorrizko Excelak. Erreferentzia gisa
  balio dute; ez dira webgunearen funtzionamendurako beharrezkoak.
- **`db/Datuak 260707.sql`**: dump handi bat (≈1,3 MB). Historikoa; ez da deployment-erako behar.
- **CSV-inportazio zaharreko JS** (`admin/index.html`-en): kode hila da, inaktibo
  (ez du UI-rik); nahi izanez gero garbi daiteke.
