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

## Probak lokalean

MySQL-era **ezin da lokaletik konektatu** (urruneko sarbidea mugatua). Beraz:

- **PHP sintaxia:** `php -l api/q.php`, `php -l admin/lib.php`, `php -l admin/api.php`.
- **JS:** giltza-balantzea egiaztatu (node ez badago instalatuta) edo nabigatzailean probatu.
- **Funtzio osoak:** zerbitzarian probatu (Plesk pull ondoren) edo staging batean.
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
`css/styles.css` aldatzean, **`?v=` eguneratu 18 stub-etan** (data bat nahikoa da).
Bestela erabiltzaile batzuek kode zaharra ikusiko dute.

> Aukera iraunkorragoa: erroko `.htaccess` batean `Cache-Control: no-cache`
> ezartzea `.js`/`.css`-entzat (ETag bidezko 304 merkeak). Hostalia/Plesk-en
> egiaztatu behar da fitxategi estatikoak Apache-tik pasatzen diren.

## Git errepositorioa

- **Remote:** `https://github.com/OierElor/AramaixoPorra.git`, `main` adarra.
- **Baztertuta** (.gitignore): `admin/config.php`, `admin/*.log`, **`data/`**.
- **Commit-mezuak:** euskaraz, laburrak.

## `data/` — zerbitzari-jabetzakoa (⚠ ez git-en)

Lehen `data/` osoa git-en zegoen; orain **git-etik kanpo** dago (`.gitignore`), admin
paneleko **Fitxategiak** atalak zuzenean zerbitzarian kudeatzen baititu (igo/ezabatu/
berrizendatu). Zerbitzaria da **iturri bakarra**; igoerak ez daude git-eko babeskopian.

- **`data/.htaccess`** git-etik kanpo geratzen da; **kodeak sortzen du** (`_ensure_data_guard()`,
  `admin/lib.php`) edozein fitxategi-eragiketatan. PHP/script exekuzioa itzaltzen du.
- **Igoera-mugak**: Plesk-eko PHP settings-etan `upload_max_filesize` eta `post_max_size`
  handitu (adib. 32M), etapa-mapa/PDF handiak igotzeko. Muga gaindituz gero, panelak
  «zerbitzariaren muga gaindituta» erakusten du.

### ⚠️ Migrazioa: `data/` git-etik ateratzea (BACKUP-LEHEN, hurrenkera zorrotza)

`data/` git-etik kentzen duen commit-a Plesk-ek pull egitean, **zerbitzariko `data/`
EZABATU egingo luke** aurretik babeskopiarik gabe. Hurrenkera hau **derrigorrezkoa** da:

1. **Backup**: Plesk File Manager-en `httpdocs/data` → `httpdocs/data_backup` kopiatu
   (edo zip deskargatu). ⚠️ Ez saltatu.
2. **Push** + **Plesk pull** → zerbitzariko `data/` ezabatuko da.
3. **Restore**: `data_backup` → `data` berrizendatu Plesk-en.
4. **Egiaztatu** irudiak/PDFak berriz agertzen direla. `data/.htaccess` sortuko da lehen
   fitxategi-eragiketan (Fitxategiak atala irekitzean) edo eskuz sor daiteke.

## Cruft / garbiketa-oharrak

- **`$file`** (erroan): fitxategi zahar galdua (`csv-loader.js` desagertua aipatzen du).
  Ezaba daiteke arriskurik gabe.
- **`Excelak ikusteko/`**: administratzailearen jatorrizko Excelak. Erreferentzia gisa
  balio dute; ez dira webgunearen funtzionamendurako beharrezkoak.
- **`db/Datuak 260707.sql`**: dump handi bat (≈1,3 MB). Historikoa; ez da deployment-erako behar.
- **CSV-inportazio zaharreko JS** (`admin/index.html`-en): kode hila da, inaktibo
  (ez du UI-rik); nahi izanez gero garbi daiteke.
