# Datuak Inportatzeko Gida (bateratua) — Itzuliak eta Klasikoak

Gida honek admin paneleko **📥 Datuak inportatu** atala azaltzen du. Sistema **bakar** honek bi Excel mota onartzen ditu:

- **Itzulia** — Tour / Giro / Vuelta (egitura bera). Apustuak "Porra denak" orrian, **15 txirrindulari** porra bakoitzeko.
- **Klasikoak** — klasiko baten denboraldia. Apustuak "Porrak sartzea" orrian, **25 txirrindulari** porra bakoitzeko.

> **Gako-printzipioa:** apustuak **dortsalez** lotzen dira txirrindulariekin (ez izenez). Horregatik, txapelketa bakoitzean **startlist-a (dortsala→txirrindularia) APUSTUEN AURRETIK inportatu behar da**. Bi formatuek dortsala dute; sistemak berdin tratatzen ditu.

Atalaren goialdean bi kontrol daude, beti ezarri behar direnak:
1. **Txapelketa** — zein txapelketari dagozkion datuak.
2. **Mota** — `Itzulia` edo `Klasikoak`. Honek parser egokia hautatzen du.

---

## Inportazio-ordena (GARRANTZITSUA)

Datuek elkarren mendekotasuna dute. Ordena hau jarraitu:

| # | Blokea | Zertarako |
|---|--------|-----------|
| 1 | **Karrerak** | Lasterketak/etapak sortu (DB-n egon behar dute emaitzak sartzeko) |
| 2 | **Startlist** | Dortsala→txirrindularia mapa (apustuek behar dute) |
| 3 | **Apustuak** | Porra bakoitzaren txirrindulariak (dortsalez) |
| 4 | **Karrera emaitzak** | Lasterketaz lasterketa, txirrindularien puntuak |
| — | *Sailkapenak kalkulatu* | (beste atal bat) porralarien puntuak kalkulatu |
| 5 | **Sailkapen finalak** | (aukerakoa) porralarien azken sailkapena |

---

## 1 · Karrerak (lasterketa-zerrenda)

Lasterketak/etapak sortzen ditu txapelketa honetan.

- **Itzulia:** lerro bat etapa bakoitzeko, **etapa-izena** soilik. Kategoria automatikoki `Etapa` jartzen da.
  ```
  Barcelona - Barcelona
  Tarragona - Barcelona
  Granollers - Les Angles
  ```
- **Klasikoak:** lerro bat lasterketa bakoitzeko: `izena` **eta** UCI kategoria, tab/koma bidez bereizita. Kategoriak: `Pro`, `S3`, `S4`, `S5`, `T1`.
  ```
  Omloop Nieuwsblad	T1
  Strade Bianche	Pro
  Milano-Sanremo	Pro
  ```

Sakatu **⬆ Sortu karrerak**. Lehendik dauden izenak (izen+urte berdina) baztertu egiten dira.

> Kategoria hutsik ez utzi: webguneak `Kategoria` duten lasterketak bakarrik zenbatzen ditu puntuaziorako.

## 2 · Startlist — dortsalak + izenak

Txirrindulari bakoitzari dortsala esleitzen dio txapelketa honetan (`TxirrindulariakTxapleketanParteHartzea`), eta falta diren txirrindulariak sortzen ditu.

- **Itzulia:** "Txirrindulariak" orritik **`Dorsalak`** eta **`Izena`** zutabeak **soilik** kopiatu:
  - LibreOffice-n: `Dorsalak` zutabe-goiburua klik → `Ctrl` sakatuta `Izena` zutabe-goiburua klik → `Ctrl+C` → itsatsi.
  - Talde-izenen lerroak (dortsalik gabe) automatikoki baztertzen dira.
- **Klasikoak:** "TX zerrenda" orria **oso-osorik** kopiatu (`Ctrl+A`). Hiru zutabe-bloke ditu (Dortsala/Izena) alboz albo; 4-digituko dortsalak bakarrik hartzen dira, talde-goiburuak (2-digitu) baztertuz.

**🔍 Aurreikusi** → zenbat dortsala eta zenbat txirrindulari berri. **⬆ Inportatu**.

> Klasikoen dortsala **4 digitu** da: lehen 2ak taldea (adib. `01`=Alpecin), azken 2ak txirrindularia. Adib. `0101` = VAN DER POEL. Startlist bat nahikoa da klasiko-denboraldi osorako.

## 3 · Apustuak (porrak)

Porra bakoitza (porralaria + aukeratutako txirrindulariak) sortzen du: `Porralariak`, `PorraEzizenak`, loturak eta `PorraApustuak`.

- **Itzulia:** "Porra denak" orri **osoa** kopiatu (`Ctrl+A`) eta itsatsi. Sareta (5 bloke zabaleran, pilatuta) automatikoki zatitzen da → porra bakoitzeko 15 dortsal.
- **Klasikoak:** "Porrak sartzea" orria kopiatu. "Porra izena" zutabetik aurrera porrero bakoitzaren 25 dortsalak irakurtzen dira.

**🔍 Aurreikusi** → porra kop., apustu kop., porralaria berriak, eta **dortsal ezezagunak**. Dortsal ezezagunak agertzen badira → **2. blokea (startlist) aurretik inportatu** eta berriz saiatu. **⬆ Inportatu** (idempotentea: ez du bikoizten).

## 4 · Karrera emaitzak (lasterketaz lasterketa)

Lasterketa baten txirrindularien emaitzak sartzen ditu (`KarreraSailkapena`), dortsalez lotuta.

1. **Karrera** (lasterketa) hautatu goiko zerrendan (1. blokean sortutakoak).
2. Emaitza-blokea itsatsi: `Posizioa · Dortsala · [Izena] · Puntuak`.
   - **Klasikoak:** "Sailkapenak erakutsi" orriko **txirrindulari-taula** kopiatu (Posizioa, Zbkia, Txirrindularia, Zenbatek, Puntuak). Kopiatu **bloke garbia**, ez orri osoa.
   - **Itzulia:** etapa baten emaitzak (Posizioa, Dortsala, Txirrindularia, Puntuak).
   ```
   1	1601	POGAČAR Tadej	39	3540
   2	1501	VAN AERT Wout	38	2165
   ```

**🔍 Aurreikusi** → zenbat emaitza eta ezezagunak. **⬆ Inportatu** (lasterketa honen emaitzak birjartzen dira).

Emaitzak sartu ondoren, joan **Sailkapenak kalkulatu** atalera eta kalkulatu porralarien puntuak.

## 5 · Sailkapen finalak (aukerakoa)

Porralarien azken sailkapena (`TxapelketaEmaitzaPorralariak`). Ezizenak lehendik egon behar dira (apustuetatik).

Itsatsi **bloke garbia**: `Posizioa · Porreroa · Puntuak`.
```
1	Keops	21209
2	La Diva	20457
```

---

## Ohar teknikoak eta akatsen konponketa

- **Dortsal ezezagunak apustuetan** → startlist-a (2. blokea) ez da oraindik inportatu txapelketa horretan, edo dortsal batzuk falta dira. Inportatu startlist osoa eta errepikatu apustuak.
- **Idempotentzia:** startlist eta apustuak berriz inportatzea segurua da (ez da bikoizten). Emaitzak birjarri egiten dira (DELETE + INSERT).
- **Dortsalak zenbaki gisa:** hasierako zeroak (`0721`) ez du axola: `0721` eta `721` berdin lotzen dira.
- **Izen bikoiztuak:** startlist-ak txirrindulari berriak sortzen ditu izenez. Bikoiztuak sortuz gero → **Datu-osasuna** panelean ikusi eta **Fusionatu** atalean batu.
- **Mota okerra:** apustuak/startlist gaizki irakurtzen badira, egiaztatu **Mota** (Itzulia/Klasikoak) ondo dagoela.
- **Klasikoen puntuak:** Excel-ak UCI kategoriaren arabera kalkulatzen ditu; DB-ra emaitza gisa (posizioa + puntuak) sartzen dira zuzenean 4. blokean.
