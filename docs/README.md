# Aramaixo Porra — Dokumentazioa

Webgune honen egitura eta atal bakoitzaren azalpen osoa. Karpeta hau (`docs/`) da
proiektuaren dokumentazio guztiaren biltegia.

## Zer da Aramaixo Porra?

Txirrindularitza-porren webgune bat da (`aramaixoporra.eus`). Lagun-talde batek
txapelketetarako (Tour, Giro, Vuelta eta klasikoak) porrak jokatzen ditu: porralari
bakoitzak txirrindulari-talde bat aukeratzen du, eta lasterketen emaitzen arabera
puntuak metatzen dira. Webguneak sailkapenak, estatistikak eta analisi-tresnak erakusten ditu.

## Dokumentazioaren aurkibidea

| Fitxategia | Edukia |
|---|---|
| [egitura.md](egitura.md) | Karpeten egitura osoa, fitxategiz fitxategi |
| [webgune-publikoa.md](webgune-publikoa.md) | Webgune publikoa: hasiera, lasterketak, orriek datuak nola kargatzen dituzten |
| [tresnak.md](tresnak.md) | Analisi-tresna interaktiboak (10) banan-banan |
| [datu-basea.md](datu-basea.md) | Datu-basearen eskema (13 taula), erlazioak eta puntuazio-eredua |
| [apiak.md](apiak.md) | API geruza: `q.php`, `proposal.php` eta admin API |
| [admin-panela.md](admin-panela.md) | Kudeaketa-panela: atal guztiak eta backend-a |
| [garapena.md](garapena.md) | Garapena, konfigurazioa eta deployment-a (git → Plesk) |

## Arkitektura orokorra (laburpena)

Webgune **estatikoa** (HTML/CSS/JS) + **PHP API** arina + **MySQL** datu-basea.
Ez dago frontend framework-ik: JavaScript hutsa.

```
                    ┌─────────────────────────────────────────────┐
                    │            NABIGATZAILEA (bezeroa)           │
                    │  HTML orriak + JS (layout, db-loader, Tresna)│
                    └───────────────┬─────────────────────────────┘
                                    │ fetch (JSON)
          ┌─────────────────────────┼──────────────────────────────┐
          │                         │                              │
    /api/q.php               /api/proposal.php              /admin/api/*
   (SELECT soilik)        (zuzenketak: email+log)        (CRUD osoa, Basic Auth)
          │                         │                              │
          │                    email + fitxategia                  │
          └─────────────────────────┴──────────────────────────────┘
                                    │
                              ┌─────▼─────┐
                              │   MySQL   │  (Hostalia / Plesk)
                              └───────────┘
```

- **Irakurketa publikoa:** orriek `POST /api/q.php` erabiltzen dute (SELECT soilik, segurua).
- **Zuzenketak:** porralariek `POST /api/proposal.php` bidez akatsak jakinarazten dituzte
  (emaila + `admin/zuzenketak.log` fitxategia). Ez du DB-rik ukitzen.
- **Kudeaketa:** `admin/` panela (HTTP Basic Auth) datu-basea guztiz kudeatzeko.
- **Deployment:** `git push` → GitHub → Plesk-ek pull egiten du (ikus [garapena.md](garapena.md)).

## Teknologia-pila

| Geruza | Teknologia |
|---|---|
| Frontend | HTML5, CSS3 (`css/styles.css`), JavaScript (framework gabe) |
| Grafikoak | Chart.js (CDN bidez, tresna batzuetan) |
| API | PHP 7.4 (PDO `q.php`-n, mysqli admin-en) |
| DB | MySQL 5.x/8.x (Hostalia `PMYSQL104.dns-servicio.com`) |
| Hosting | Hostalia + Plesk (git integrazioarekin) |
| Domeinua | `aramaixoporra.eus` |

## Hizkuntza

Proiektu osoa **euskaraz** dago: interfazea, iruzkinak, taula-izenak eta
dokumentazioa. Datu-baseko izenak euskaraz daude (Txapelketak, Karrerak, Porralariak…).
