# Admin panela

Datu-basea kudeatzeko panela: `aramaixoporra.eus/admin/`. HTTP Basic Auth-ez babestua.
SPA bat da (JavaScript hutsa) PHP backend batekin. Ez du urruneko MySQL sarbiderik behar:
PHP-a Hostaliaren barruan exekutatzen da.

## Backend fitxategiak

| Fitxategia | Zertarako |
|---|---|
| `index.html` | Interfaze osoa (SPA): atal guztiak + JS logika (≈165 KB) |
| `index.php` | Auth txekea egin eta `index.html` zerbitzatu |
| `api.php` | Sarrera-puntua: autentifikazioa (`hash_equals`) + route-ak (`_path`) |
| `lib.php` | **Logika guztia**: CRUD, inportazioa, fusioa, sailkapenak, osasuna… (≈73 KB) |
| `db.php` | MySQL konexioa (mysqli) + `db_rows/db_one/db_exec/quote_ident`… |
| `config.php` | **Kredentzialak** (MySQL + auth). Git-etik kanpo (.gitignore) |
| `.htaccess` | `api/*` → `api.php` bideratu + `config.php`/`*.log` babestu |
| `zuzenketak.log` | Zuzenketa-proposamenak (runtime; git-etik kanpo) |
| `README.md` | Instalazio-oharrak zerbitzarian |
| `INPORTATU-GIDA.md` | Datuak inportatzeko gida osoa (itzuliak + klasikoak) |

Kodea `php -l`-rekin egiaztatzen da; funtzio osoak zerbitzarian probatzen dira (MySQL
lokaletik itxita dagoelako).

## Atalak

Nabigazio-menuak bi multzo ditu: **Ikus** (begiratu/editatu) eta **Sartu** (datuak sartu).

### 👁️ Ikus

| Atala | Zer egiten du |
|---|---|
| **Dashboard** | Laburpen-estatistikak, txapelketa baten porra-sailkapena, datu-kalitate txartela |
| **Porralariak** | Jokalarien zerrenda (bilaketa, izena inline editatu, porra-kop. kalkulatu) |
| **Txirrindulariak** | Txirrindulariak: izena, `Izen_Formatua` (I·A editorea), izen-ordena aldatu (⇒ I·A / ⇒ A·I), tokiz aldatu |
| **Txapelketak** | Txapelketak + karrerak (etapak). Txapelketa bakoitzean **Porra irekita** (aurre-porrak onartu), **Apustu kop.** (15/25) eta **Amaituta** (itxita/bukatuta marka) ezarri; karrerei **Kategoria** (derrigorrezkoa) eta **Ordena** ezarri, ezabatu, txapelketa esportatu. ⚠️ Kategoriarik gabeko karrera bat urte-orriko akordeoian eta tresnetan ezkuta daiteke. Migrazioa: `db/amaituta.sql` |
| **Karrera motak** | **Moten katalogoa** (`KarreraMotak`): sortu, berrizendatu, ordenatu, ezabatu — bakoitzaren **erabilera-kopurua** erakusten da. Karrera bakoitzari **mota**, **desnibela** (metroak) eta **«Emaitzarik ez»** marka esleitzen zaizkio (txapelketaz txapelketa).<br>⚠️ **Mota ≠ Kategoria**: Kategoriak akordeoiaren iragazkia eta puntuazioa gidatzen ditu; **mota** zuk definitzen duzun ezaugarri independentea da.<br>**«Emaitzarik ez»** = karrera horrek ez du **inoiz** emaitzarik izango (bertan behera utzia): urte-orriko **akordeoian ez da agertzen**, **grafikoetan ez da zenbatzen**, eta **Datu-osasunak ez du salatzen**.<br>Migrazioak: `db/karrera-motak.sql`, `db/desnibela.sql` |
| **Sailkapenak** | Txapelketa baten porralari emaitza ofizialak |
| **Karrera emaitzak** | Etapa/lasterketa baten sailkapena editatu (postua + puntuak) |
| **Datu-osasuna** | Egiaztapenak txapelketaz: emaitzarik gabeko etapak, 15/25 apustu ez dituzten porrak, dortsalik gabeko txirrindulariak, birkalkulatu behar, izen bikoiztuak, **Kategoriarik gabeko karrerak**. «Emaitzarik ez» markatutakoak **ez dira salatzen**. Esportazioa ere |
| **Zuzenketak** | Porralariek bidalitako zuzenketa-proposamenak (`zuzenketak.log`-etik). Menuan zain daudenen **badge**-a. Eskuz konpondu eta kendu |
| **Aurre-porrak** | Porralariek **aurretik bidalitako porrak** (`aurre-porrak.log`-etik). Menuan **badge**-a. **➡ Inportatu** botoiak `PorraApustuak`-en sartzen ditu (preview + berrespena); porralari berririk **ez da sortzen**, beraz ezizen ezezagunak *Ezizenak lotu* bidez lotu behar dira. Inportatu ondoren 🗑 kendu |
| **Taula guztiak** | DB taula gordinen editore generikoa: edozein errenkada editatu edo **ezabatu** (🗑). PK zutabeak irakur-bakarrak |

### ⬆️ Sartu

| Atala | Zer egiten du |
|---|---|
| **Eskuz sartu** | Erregistro bakarrak eskuz gehitu (txapelketa, karrera, txirrindularia, emaitzak…) |
| **Datuak inportatu** | Sistema bateratua (Itzuliak + Klasikoak): 6 bloke — karrerak, startlist (dortsalak), apustuak, emaitzak, sailkapen finalak (**porralariak** eta **txirrindulariak**). Sailkapen finaletako `Puntuak` = **TOTAL FINALA** (etapak + orokorra + mendia): grafikoetako amaiera-puntua hortik dator. Apustuak **dortsalez** lotzen dira. Ikus [INPORTATU-GIDA](../admin/INPORTATU-GIDA.md) |
| **Puntu finalak** | **Itzuli handien amaiera**. Txapelketa hautatu eta **txirrindulariko** bonus-puntuak itsatsi bi koadrotan: **🏆 Orokorra** eta **⛰️ Mendia** (`Dortsala · Izena · [Zenbatek] · Puntuak`; dortsalez lotzen dira startlist-etik). Tresnak: (1) txirri bakoitzaren **totala** (etapak + orokorra + mendia, `KarreraSailkapena`-tik); (2) porralari bakoitzaren **bonusa** bere txirrindularien (apustuen) baturaz (`Generala = Σ orokorra`, `Mendikoa = Σ mendia`), totala = etapak + generala + mendia; postuak esleituz. **Aurreikusi**: porralari- eta txirri-taulak + dortsal ezezagunak. **Gorde eta txapelketa itxi**: `TxapelketaEmaitzaTxirrindulariak` (Puntuak/Sailkapen_Nag/Mendian/Posizioa) eta `TxapelketaEmaitzaPorralariak` (Puntuak/Generala/Mendikoa/Posizioa) idatzi, eta txapelketa **itxi** (`Amaituta = 1`, `Porra_Irekita = 0`). Ez-suntsitzailea (berriz exekutatzea segurua). Itzuli handietan **block 5/5b eskuzkoak ordezkatzen ditu**. Migrazioa: `db/amaituta.sql` |
| **Fitxategiak** | `data/` karpetako fitxategiak kudeatu: nabigatu, **igo**, **ezabatu**, **izena aldatu**, **karpeta sortu**. Irudiek miniatura-aurrebista dute, PDFek esteka. Onartuak: jpg, png, gif, webp, pdf. Karpeta-mapa ere hemen editatzen da (**mota bakoitzaren karpeta**; webgune publikoak berehala jarraitzen du). **Profil-irudi bati karrera lotu** daiteke (🔗), `Etapa{N}.jpg` konbentzioaren ordez (`Karrerak.Profil_Irudia`). Itzulien etapa-profilak `profilak/<kirola>NN/EtapaN.jpg` izena behar dute; klasikoen irudiek `js/txapelketak.js`-eko `irudiak` mapa (eskuz). Ikus [apiak.md](apiak.md) eta [garapena.md](garapena.md) |
| **Porra editatu** | Porralari baten apustuak (aukeratutako txirrindulariak) ikusi eta zuzendu (gehitu/kendu) |
| **Sariak** | Txapelketa bakoitzeko sariak (posizioa → saria) |
| **Sailkapenak kalkulatu** | Apustuetatik + karrera-emaitzetatik porralarien/txirrindularien puntuak kalkulatu |
| **Fusionatu** | Bikoiztutako txirrindulari edo porralariak batu (bat mantendu, bestea desagertu) |
| **Ezizenak lotu** | Porra-ezizena bat porralari batekin lotu |
| **Banatu porralaria** | Porralari batean nahastutako bi pertsona bereizi |
| **Nola erabili** | Laguntza-atala: lan-fluxua eta atalen azalpena |

## Ezaugarri komunak

- **Undo/Redo** (CSV-inportazio zaharrean zegoen; egungo inportatzaileak idempotenteak dira).
- **Fuzzy izen-lotura** (`fuzzy_name_score`): azentuak, herrialde-kodeak eta ordena kudeatzen ditu.
- **Baieztapen-dialogo estilizatua** (`confirmDialog`) ekintza arriskutsuetan.
- **Idempotentzia:** inportazioak berriz exekutatzea segurua da (ez du bikoizten).

## Segurtasuna

- API mailan HTTP Basic Auth (`api.php`, `hash_equals`).
- `config.php` git-etik kanpo; `.htaccess`-ek `config.php` eta `*.log` web-sarbidetik babesten ditu.
- Gomendioa: Plesk-en `/admin` karpeta pasahitzez babestu (`index.html` bera ere estaltzeko).
