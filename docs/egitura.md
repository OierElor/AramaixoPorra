# Karpeten egitura

Proiektuaren erroa `aramaixoporra.eus`-en `httpdocs/` bihurtzen da (Plesk pull bidez).

```
AramaixoPorra/
├── index.html                  # Hasiera-orria (azalak / karrusela)
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
│   ├── itzuliak.js             # Itzulien konfigurazioa (id, PDFak, ibilbide-irudia)
│   └── itzulia-orria.js        # Itzulien urte-orria marrazten du (txantiloi partekatua)
│
├── tour/  giro/  vuelta/       # Itzuli handiak, urteka (urte bakoitzeko index.html bat)
│   ├── 2023/index.html         # stub uniformea; edukia itzulia-orria.js-k sortzen du
│   ├── 2024/index.html
│   └── …                       # tour/giro: 2023-2026 · vuelta: 2020-2026
│
├── klasikak/                   # Klasikoak, urteka; lasterketa bakoitzeko HTML bat
│   ├── 2024/  (21 lasterketa)
│   ├── 2025/  (27 lasterketa)
│   └── 2026/  (27 lasterketa)  # adib. amstel-gold-race.html, paris-roubaix.html…
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
│   └── zuzenketak/             # Zuzenketa-proposamenen formularioa (publikoa)
│
├── api/                        # API publikoa (auth gabe)
│   ├── q.php                   # SELECT-soilik kontsulta-API segurua
│   └── proposal.php            # Zuzenketa-proposamenak (email + log)
│
├── admin/                      # Kudeaketa-panela (HTTP Basic Auth)
│   ├── index.html              # SPA interfazea (atal guztiak)
│   ├── index.php               # Auth txekea + index.html zerbitzatzea
│   ├── api.php                 # API routing + autentifikazioa
│   ├── lib.php                 # Logika guztia (CRUD, inportazioa, fusioa, sailkapenak…)
│   ├── db.php                  # MySQL konexioa (mysqli) + laguntzaileak
│   ├── config.php              # KREDENTZIALAK (git-etik kanpo, .gitignore)
│   ├── .htaccess               # api/* bideratzea + fitxategi sentikorren babesa
│   ├── .gitignore              # config.php + zuzenketak.log baztertu
│   ├── zuzenketak.log          # Zuzenketa-proposamenak (runtime, git-etik kanpo)
│   ├── README.md               # Admin panelaren instalazio-oharrak
│   └── INPORTATU-GIDA.md       # Datuak inportatzeko gida osoa
│
├── db/
│   └── Datuak 260707.sql       # Datu-basearen dump-a (erreferentzia)
│
├── data/                       # Baliabide estatikoak
│   ├── Portadak/               # Azalak + favicon
│   └── …                       # PDFak (arauak, porralarien zerrendak), irudiak
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
- **Itzuli handiak vs klasikoak**: itzuliek (tour/giro/vuelta) **urteko orri bakarra**
  dute (etapa anitz barruan); klasikoek **lasterketa bakoitzeko orri bat**.
