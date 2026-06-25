# Aramaixo Porra — Admin panela (PHP)

Datu-basea kudeatzeko admin panela, **PHP-z** idatzia, `aramaixoporra.eus`-en zerbitzaritik exekutatzeko.
Honela ez da urruneko MySQL sarbiderik (token/IP baimenik) behar: PHP-a Hostaliaren barruan
exekutatzen denez, MySQL-era zuzenean konektatzen da.

## Fitxategiak

| Fitxategia | Zertarako |
|---|---|
| `index.html` | Interfazea (fetch deiak `api/...` bide erlatiboekin) |
| `api.php` | Sarrera-puntua: saioa, autentifikazioa, routing |
| `lib.php` | Logika guztia (CRUD, CSV, fuzzy, merge, sailkapenak…) |
| `db.php` | MySQL konexioa (mysqli) eta laguntzaileak |
| `config.php` | **Kredentzialak** — git-etik kanpo (.gitignore). Zerbitzarian egon behar du |
| `config.example.php` | Adibidea, `config.php` sortzeko |
| `.htaccess` | `api/*` → `api.php` bideratzea + config.php babestea |

## Instalazioa zerbitzarian

1. **Kopiatu `admin/` karpeta** webgunearen errora (deploy errepositorioan), `aramaixoporra.eus/admin/` izan dadin.

2. **`config.php` sortu** zerbitzarian (ez da git-era igotzen):
   - `config.example.php` kopiatu `config.php` izenarekin
   - Bete: MySQL pasahitza (`pass`) eta admin paneleko pasahitza (`auth_pass`)
   - MySQL host-a Hostaliaren barnetik: `PMYSQL104.dns-servicio.com` (lehendik web orriak erabiltzen duena)

3. **PHP luzapenak**: `mysqli`, `mbstring` behar dira (Hostaliak lehenetsita ditu). `intl` (Normalizer)
   aukerakoa da — gabe ere badabil (azentu-mapa propioa du).

4. **Sarbidea babestu** (NAHITAEZKOA, publikoa baita):
   - `config.php`-ko `auth_user` / `auth_pass` bidez HTTP Basic Auth eskatzen da API-an.
   - Gomendio gehigarria: Plesk-en `/admin` karpeta pasahitzez babestu
     ("Directorios protegidos con contraseña"), index.html bera ere estaltzeko.

## Erabilera

Nabigatu **`https://aramaixoporra.eus/admin/`** (azken barrarekin), eta sartu `auth_user`/`auth_pass`.

## Garapena / probak lokalean

```bash
sudo apt install -y php-cli php-mbstring php-mysqli php-intl
php -l api.php && php -l lib.php   # sintaxia
```

Lokaletik MySQL-era ezin da konektatu (urruneko sarbidea mugatua), beraz funtzio osoak
zerbitzarian probatzen dira (edo HTTPS bidez behin igota).

## Oharra

`config.php` EZ igo errepositorio publiko batera. `.gitignore`-k baztertzen du.
