# Tresnak (analisi interaktiboa)

`tresnak/` ataleko tresna guztiek **`js/tresna-komuna.js`**-ko `Tresna` objektua
partekatzen dute, eta datuak `/api/q.php` bidez irakurtzen dituzte (SELECT soilik).

## `Tresna` objektua (`js/tresna-komuna.js`)

Metodo/laguntzaile nagusiak:

| Metodoa | Zertarako |
|---|---|
| `Tresna.q(sql, params)` | SELECT kontsulta (db-loader-en gainean) |
| `Tresna.txapelketak()` | Txapelketa guztiak, lasterketa-motaz taldekatuta + urteka ordenatuta |
| `Tresna.karrerak(tid)` | Txapelketa baten karrerak (etapak/lasterketak) |
| `Tresna.porrak(tid)` | Txapelketako porra guztiak, puntu/postuekin |
| `Tresna.taldea(tid, ezId)` | Porra baten txirrindulariak + puntuak |
| `Tresna.eboluzioa(...)` | Puntu metatuak karreraz karrera |
| `Tresna.autocomplete(input, items, onSelect)` | Bilaketa-koadro pertsonalizatua (mugikorretan ere) |
| `Tresna.lineChart(...)` | Chart.js lerro-grafikoen konfigurazio komuna |

`COLORS` array bat ere badu (serieen koloreak) eta karrera-etiketentzako laguntzaileak.

## Tresna bakoitza

| Tresna | Bidea | Zer egiten du |
|---|---|---|
| **Eboluzio grafikoak** | `tresnak/grafikoak/` | Lasterketa batean zehar txirrindulari edo porralarien eboluzioa (puntuak edo postua) lerro-grafikoetan. Azken etapan mendiko/orokorreko bonusa gehitzen du. |
| **Porra fitxa** | `tresnak/porra-fitxa/` | Porra bat bilatu → bere talde osoa, puntuak, postua eta eboluzio-grafikoak. Erreferentzia-lerroa (N. postua) marrazten du. |
| **Porra konparatzailea** | `tresnak/konparatzailea/` | 2-4 porra alderatu: aukera komun/ezberdinak, puntuak eta eboluzioa. |
| **Aukeren bero-mapa** | `tresnak/bero-mapa/` | Matrize bisuala: porra bakoitzak zein txirrindulari aukeratu zituen. Iragazkiak: txirrindularia, top-N, nabarmentzailea. |
| **Porralari fitxa** | `tresnak/porralari-fitxa/` | Porralari batek bota dituen porrak, posturik onena, batez bestekoak (itzuli h. / klasikak), taldekideak. |
| **Txirrindulari fitxa** | `tresnak/txirrindulari-fitxa/` | Txirrindulari baten partaidetzak: dortsala, postua, puntuak eta zenbat porralarik aukeratu duten. |
| **Porra ideala** | `tresnak/porra-ideala/` | Posible zen porrarik onena vs benetan aukeratutakoa; zenbat puntu galdu ziren. |
| **Porralariak konparatzailea** | `tresnak/porralariak-konparatzailea/` | 2-4 porralari alderatu txapelketa GUZTIETAN: posizioak, garaipenak, podioak, aurrez-aurreko (head-to-head). |
| **Sarien banaketa** | `tresnak/sariak/` | Lasterketa bakoitzeko sariak eta irabazleak. **Oharra:** dagoeneko ez da tresnen menuan agertzen (sariak webgunetik kendu ziren), baina orria oraindik hor dago. |
| **Zuzenketak proposatu** | `tresnak/zuzenketak/` | Porralarien akats-jakinarazpenen formularioa (ikus [apiak.md](apiak.md)). |
| **Porra bidali** | `tresnak/porra-bidali/` | **Aurre-porrak**: adminak *irekitako* txapelketetan, porralariek beren 15 (edo 25) txirrindulari aukeratzen dituzte startlist-etik eta porra aurretik bidaltzen dute. `POST /api/porra.php` — **ez du datu-basea idazten**. Anboto tabernara joan behar da hala ere. |

## Eboluzio-geruza BATERATUA (grafiko-tresnak)

**Grafikoak**, **Porra fitxa** eta **Porra konparatzailea** lehen logika **bera bikoiztuta**
zuten. Orain `js/tresna-komuna.js`-ko geruza **bakarra** darabilte:

| Funtzioa | Zertarako |
|---|---|
| `Tresna.karrerakEmaitzekin(tid)` | **Emaitzak dituzten karrerak SOILIK** (ordenan) |
| `Tresna.eboluzioaKargatu(tid, mota)` | Datu-geruza osoa: `pts`, `cum`, `pos`, `diff`, `total`, `bonusa` |
| `Tresna.eboluzioGrafikoa(canvas, datu, ents, metrika, opts)` | Errendatzaile partekatua |
| `Tresna.lineChart(canvas, opts)` | Chart.js oinarri komuna (**4 tresnek** darabilte) |

Bi jokabide garrantzitsu:

- **Emaitzarik gabeko karrerak EZ dira erakusten.** Lehen lerro lau luze bat marrazten zuten
  (adib. Tour 2026: 21 karrera baina **8** emaitzekin). Orain 8 puntu erakusten dira.
- **Amaiera-puntua** (**itzuli handietan bakarrik**): azken karreraren ondoren puntu bat,
  **sailkapen ofizialeko puntuekin**, zeinek **sailkapen orokorreko eta mendiko puntuak jada
  barne baititu**. `bonusa = ofiziala − etapetan metatua` (adib. Tour 2025 «Peeny Lane»:
  1113 → **1558**, +445).
  - `Puntuak_Generala`/`Puntuak_Mendikoa` zutabeak **EZ dira erabiltzen**: ia hutsik daude.
  - Klasikoetan **ez da erakusten** (han ofiziala ez da «etapak + bonusa»).
  - Bonusik ez badago (emaitza ofizialak bonusik gabe sartuta), ez da erakusten: puntu lau
    erredundante bat besterik ez litzateke.

**Porralariak konparatzailea**-k datu-logika propioa du (ardatza = **txapelketak**, ez
karrerak), baina **`Tresna.lineChart` partekatua** darabil errendatzeko.

## Patroi komuna

Tresna gehienek egitura bera dute:
1. Txapelketa `<select>` bat (batzuek porralaria/txirrindulari bilatzailea ere).
2. `Tresna.q(...)` bidez datuak kargatu.
3. Taula edo `Tresna.lineChart` grafikoetan errendatu.

Datuak **irakurketa hutsa** dira: tresnek ez dute inoiz idazten (`q.php` SELECT soilik da).
