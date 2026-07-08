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

## Git errepositorioa

- **Remote:** `https://github.com/OierElor/AramaixoPorra.git`, `main` adarra.
- **Baztertuta** (.gitignore): `admin/config.php`, `admin/zuzenketak.log`.
- **Commit-mezuak:** euskaraz, laburrak.

## Cruft / garbiketa-oharrak

- **`$file`** (erroan): fitxategi zahar galdua (`csv-loader.js` desagertua aipatzen du).
  Ezaba daiteke arriskurik gabe.
- **`Excelak ikusteko/`**: administratzailearen jatorrizko Excelak. Erreferentzia gisa
  balio dute; ez dira webgunearen funtzionamendurako beharrezkoak.
- **`db/Datuak 260707.sql`**: dump handi bat (≈1,3 MB). Historikoa; ez da deployment-erako behar.
- **CSV-inportazio zaharreko JS** (`admin/index.html`-en): kode hila da, inaktibo
  (ez du UI-rik); nahi izanez gero garbi daiteke.
