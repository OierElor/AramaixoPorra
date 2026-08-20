# Karpeten egitura

Proiektuaren erroa `aramaixoporra.eus`-en `httpdocs/` bihurtzen da (Plesk pull bidez).

```
AramaixoPorra/
├── index.html                  # Hasiera-orria (azalak / karrusela)
├── bump-version.sh             # Cache-bertsioa (?v=) orri publiko guztietan eguneratu
├── robots.txt                  # Bilatzaileentzako arauak
├── sitemap.xml                 # Guneko mapa (SEO)
│
├── css/
│   └── styles.css              # Estilo GUZTIAK (kolore-aldagaiak, responsive, taulak…)
│
├── js/
│   ├── layout.js               # Goiburua + nabigazioa + oina orri guztietan
│   ├── db-loader.js            # Lasterketa-emaitzak kargatzeko (q.php bidez)
│   ├── tresna-komuna.js        # `Tresna` objektua: tresnek partekatzen duten logika
│   ├── txapelketak.js          # Txapelketen konfigurazioa (id, PDFak, irudiak)
│   ├── txapelketa-orria.js     # Urte-orria marrazten du (txantiloi partekatua)
│   ├── porra-prestatu.js       # Listak + porra zirriborroak (localStorage)
│   └── porra-bidali.js         # Aurre-porren formularioa (txirrindulari-hautatzailea)
│
├── tour/  giro/  vuelta/       # Itzuli handiak, urteka (urte bakoitzeko index.html bat)
│   ├── 2023/index.html         # stub uniformea; edukia txapelketa-orria.js-k sortzen du
│   ├── 2024/index.html
│   └── …                       # tour/giro: 2023-2026 · vuelta: 2020-2026
│
├── klasikak/                   # Klasikoak, urteka — itzulien egitura BERA
│   ├── 2024/index.html         # stub uniformea (lasterketak akordeoian)
│   ├── 2025/index.html
│   └── 2026/index.html
│
├── tresnak/                    # Analisi-tresna interaktiboak
│   ├── index.html              # Tresnen sarrera (txartelak)
│   ├── grafikoak/              # Eboluzio-grafikoak
│   ├── porra-fitxa/            # Porra baten fitxa
│   ├── konparatzailea/         # Porra konparatzailea
│   ├── bero-mapa/              # Aukeren bero-mapa
│   ├── porralari-fitxa/        # Porralari baten fitxa
│   ├── txirrindulari-fitxa/    # Txirrindulari baten fitxa
│   ├── porra-ideala/           # Porra idealaren analisia
│   ├── porralariak-konparatzailea/  # Porralarien konparaketa historikoa
│   ├── sariak/                 # Sarien banaketa (dagoeneko ez da menuan agertzen)
│   ├── zuzenketak/             # Zuzenketa-proposamenen formularioa (publikoa)
│   ├── porra-prestatu/         # Listak + porra zirriborroak (localStorage, publikoa)
│   └── porra-bidali/           # Aurre-porrak bidaltzeko formularioa (publikoa)
│
├── api/                        # API publikoa (auth gabe)
│   ├── db-read.php             # SELECT-soilik PDO konexioa (KREDENTZIALAK; ez da endpoint-a)
│   ├── q.php                   # SELECT-soilik kontsulta-API segurua
│   ├── proposal.php            # Zuzenketa-proposamenak (email + log)
│   ├── porra.php               # Aurre-porrak (email + log; DBan ez du idazten)
│   ├── ezarpenak.php           # Karpeta-mapa publikoa (fitxategi-mota → karpeta)
│   └── .htaccess               # db-read.php-rako zuzeneko sarbidea blokeatu
│
├── admin/                      # Kudeaketa-panela (HTTP Basic Auth)
│   ├── index.html              # SPA interfazea (atal guztiak)
│   ├── index.php               # Auth txekea + index.html zerbitzatzea
│   ├── api.php                 # API routing + autentifikazioa
│   ├── lib.php                 # Logika guztia (CRUD, inportazioa, fusioa, sailkapenak…)
│   ├── db.php                  # MySQL konexioa (mysqli) + laguntzaileak
│   ├── config.php              # KREDENTZIALAK (git-etik kanpo, .gitignore)
│   ├── .htaccess               # api/* bideratzea + fitxategi sentikorren babesa
│   ├── .gitignore              # config.php + *.log baztertu
│   ├── zuzenketak.log          # Zuzenketa-proposamenak (runtime, git-etik kanpo)
│   ├── aurre-porrak.log        # Aurretik bidalitako porrak (runtime, git-etik kanpo)
│   ├── README.md               # Admin panelaren instalazio-oharrak
│   └── INPORTATU-GIDA.md       # Datuak inportatzeko gida osoa
│
├── db/
│   ├── Datuak 260707.sql       # Datu-basearen dump-a (erreferentzia)
│   ├── ordena.sql              # Migrazioa: Karrerak.Ordena
│   ├── aurre-porrak.sql        # Migrazioa: Txapelketak.Porra_Irekita + Apustu_Kopurua
│   ├── profil-irudia.sql       # Migrazioa: Karrerak.Profil_Irudia (profil-lotura)
│   ├── emaitzarik-ez.sql       # Migrazioa: Karrerak.Emaitzarik_Ez
│   ├── amaituta.sql            # Migrazioa: Txapelketak.Amaituta (txapelketa itxi)
│   └── ezabatu-motak-desnibela.sql  # Garbiketa eskuzkoa: karrera-motak + desnibela kentzea
│
├── data/                       # Baliabide estatikoak — GIT-EN trackeatuta, MOTAKA antolatuta
│   ├── .htaccess               # Script exekuzioa galarazi (gitignore; kodeak mantentzen du)
│   ├── arauak/                 # Arauen PDFak
│   ├── dortsalak/              # Txirrindulari-zerrenden PDFak
│   ├── porrak/                 # Porralarien zerrenden PDFak
│   ├── portadak/               # Azalak + favicon (bide FINKOA, <head>-etan)
│   └── profilak/               # Etapa/ibilbide irudiak: tour26/ giro26/ vuelta26/ klasikak26/
│   # Karpeta hauek admin-etik alda daitezke (api/ezarpenak.php); portadak izan ezik.
│
├── Excelak ikusteko/           # Iturri-Excelak (erreferentzia; ez da webgunearen parte)
│   ├── Porra tour 2026 betea.ods
│   └── Klasikoen porra 2026.ods
│
└── docs/                       # ← DOKUMENTAZIOA (karpeta hau)
```

## Oharrak

- **`$file`** (erroan): fitxategi zahar galdua da (`csv-loader.js` desagertua aipatzen du).
  Ez du ezertarako balio; segurtasunez ezaba daiteke.
- **`Excelak ikusteko/`**: administratzailearen jatorrizko Excelak dira, datuak nondik
  datozen ulertzeko erreferentzia gisa. Ikus [INPORTATU-GIDA](../admin/INPORTATU-GIDA.md).
- **Lau txapelketa-motek egitura BERA dute**: `tour`, `giro`, `vuelta` eta `klasikak`.
  Bakoitzak **urteko orri bakarra** du, eta karrera guztiak (etapak edo klasikoak)
  urte-orriko **akordeoian** agertzen dira. Ez dago karrera bakoitzeko orririk.
