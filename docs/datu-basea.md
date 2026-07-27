# Datu-basea

MySQL datu-basea Hostalian (`PMYSQL104.dns-servicio.com`, `6437239_aramaixoporra`).
**14 taula.** Eskema phpMyAdmin bidez kudeatzen da; `db/Datuak 260707.sql` dump-a
erreferentzia gisa dago (git-en). Kredentzialak EZ daude dokumentu honetan (ikus [garapena.md](garapena.md)).

## Kontzeptu-eredua

- **Txapelketa** bat = Tour/Giro/Vuelta/Klasikoak urte batekoa.
- **Karrera** bat = txapelketa baten etapa (itzuliak) edo lasterketa (klasikoak).
- **Txirrindularia** = txirrindulari bat; txapelketa bakoitzean **dortsal** bat du.
- **Porralaria** = jokalaria. Txapelketa bakoitzean **ezizen** batekin parte hartzen du,
  eta txirrindulari-talde bat aukeratzen du (**apustuak**).
- Karrera bakoitzean txirrindulariek **puntuak** irabazten dituzte (postuaren arabera).
  Porralari baten puntuak = bere txirrindulariek irabazitakoen batura.

## Taulak

### Oinarrizko entitateak

| Taula | Zutabe nagusiak | Azalpena |
|---|---|---|
| **Txapelketak** | `Txapelketa_ID` (PK), `Izena`, `Urtea`, `Porra_Irekita`, `Apustu_Kopurua`, `Amaituta` | Txapelketak (adib. "Tour de France", 2026). `Porra_Irekita` = 1 bada, **aurre-porrak** onartzen dira (`api/porra.php`). `Apustu_Kopurua` = porra bakoitzeko txirrindulari kopurua (itzuliak **15**, klasikak **25**; NULL → 15). `Amaituta` = 1 bada, txapelketa **itxita/bukatuta** (admin-en marka; *Puntu finalak* tresnak jartzen du, `Porra_Irekita`-rekin batera). Migrazioak: `db/aurre-porrak.sql`, `db/amaituta.sql` |
| **Karrerak** | `Karrerak_ID` (PK), `Txapelketa_ID`→, `Izena`, `Urtea`, `Kategoria`, `Ordena`, `Profil_Irudia`, `Mota_ID`→ | Etapak/lasterketak. `Kategoria` = 'Etapa' (itzuliak) edo UCI kodea (klasikoak). Hutsik = akordeoian/tresnetan ezkuta daiteke. `Ordena` = etapa-zenbakia (profil-irudia ere hortik: `Etapa{Ordena}.jpg`). `Profil_Irudia` = profil-lotura esplizitua (bidea `profilak`-etik; NULL bada konbentzioa). `Mota_ID` → `KarreraMotak` (desnibela/lurraldea; **`Kategoria`-tik independentea**). Migrazioak: `db/ordena.sql`, `db/profil-irudia.sql`, `db/karrera-motak.sql` |
| **Txirrindulariak** | `Txirrindularia_ID` (PK), `Izena`, `Izen_Formatua` | Txirrindulariak. `Izen_Formatua` = "Izena"/"Abizena" tokenak (izen-ordena kudeatzeko) |
| **Porralariak** | `Porralaria_ID` (PK), `Izena`, `Zenbat_Porra` | Jokalariak. `Zenbat_Porra` = zenbat porra jokatu dituen (kalkulatua) |
| **KarreraMotak** | `Mota_ID` (PK), `Izena`, `Ordena` | **Karrera-moten katalogoa** (adib. `<2000 m`, `2000-3000 m`). Admin panelean kudeatzen da (*Karrera motak*). Mota bat berrizendatzean, karrera lotu guztiek automatikoki jarraitzen dute. Ezabatzean, karrerak `Mota_ID = NULL` geratzen dira (ez dira ezabatzen). Migrazioa: `db/karrera-motak.sql` |

### Lotura-tau, apustu eta emaitzak

| Taula | Zutabe nagusiak | Azalpena |
|---|---|---|
| **TxirrindulariakTxapleketanParteHartzea** | `TxapelketaID`→, `TxirrindulariaID`→, `Dortsala` | Txirrindulari batek txapelketa batean duen **dortsala** (startlist). Apustuak dortsalez lotzeko funtsezkoa |
| **KarreraSailkapena** | `Karrera_ID`→, `Txirrindularia_ID`→, `Sailkapena`, `Puntuak` | Karrera baten **emaitza**: txirrindulari bakoitzaren postua eta puntuak |
| **PorraEzizenak** | `Ezizen_ID` (PK), `Txapelketa_ID`→, `Ezizena` | Porralari baten **ezizena/taldea** txapelketa batean (adib. "Keops") |
| **PorralariTaldeenEzizenak** | `Ezizen_ID`→, `Porralaria_ID`→ | Ezizena ↔ porralaria lotura (nor dagoen ezizen baten atzean) |
| **PorraApustuak** | `Txapelketa_ID`→, `Ezizen_ID`→, `Txirrindularia_ID`→ | **Apustuak**: ezizen batek aukeratutako txirrindulariak (15 itzulietan, 25 klasikoetan) |

### Emaitza ofizialak eta kalkulatuak

| Taula | Zutabe nagusiak | Azalpena |
|---|---|---|
| **TxapelketaEmaitzaPorralariak** | `Txapelketa_ID`→, `Ezizen_ID`→, `Posizioa`, `Puntuak`, `Puntuak_Mendikoa`, `Puntuak_Generala` | Porralarien **azken sailkapen ofiziala** (bonusak barne) |
| **TxapelketaEmaitzaTxirrindulariak** | `Txapelketa_ID`→, `Txirrindularia_ID`→, `Posizioa`, `Puntuak`, … | Txirrindularien azken sailkapen ofiziala |
| **TxapelketaSailkapenaPorralariak** | `Txapelketa_ID`→, `Ezizen_ID`→, `Azken_Karrera_ID`→, `Puntuak_Totalean`, `Puntuazio_Finala`… | Porralarien sailkapena **karreraz karrera** (eboluzioa). "Sailkapenak kalkulatu"-k sortzen du |
| **TxapelketaSailkapenaTxirrindulariak** | (antzekoa) | Txirrindularien sailkapena karreraz karrera |

## Erlazioak (laburpena)

```
Txapelketak 1───∞ Karrerak 1───∞ KarreraSailkapena ∞───1 Txirrindulariak
     │                                                        │
     │ 1                                                      │ ∞
     ∞                                                        │
 PorraEzizenak ─1──∞ PorralariTaldeenEzizenak ∞──1 Porralariak│
     │ 1                                                      │
     │ ∞                                                      │
 PorraApustuak ∞────────────────────────────────────────────1┘
     (ezizen batek aukeratutako txirrindulariak)

Txirrindulariak ∞──1 TxirrindulariakTxapleketanParteHartzea 1──∞ Txapelketak
                          (dortsala txapelketa bakoitzean)
```

## Puntuazio-eredua

1. Karrera bakoitzean, txirrindulariek postuaren arabera **puntuak** jasotzen dituzte
   (`KarreraSailkapena.Puntuak`). Klasikoetan UCI kategoriak zehazten du eskala.
2. Porralari baten puntuak = berak aukeratutako txirrindularien (`PorraApustuak`)
   puntuen **batura**, karreraz karrera.
3. Admin panelean **"Sailkapenak kalkulatu"**-k `TxapelketaSailkapena*` taulak
   (eboluzioa) berreraikitzen ditu apustuetatik eta karrera-emaitzetatik.
4. `TxapelketaEmaitza*` = azken emaitza ofizialak (zuzenean inporta daitezke, edo
   kalkuluak betetzen ditu).
5. **Itzuli handietan**, sailkapen **orokorreko** eta **mendiko** bonus-puntuak
   **txirrindulariko** ematen dira (azken sailkapenaren arabera). Porralari batek
   bere txirrindularien (apustuen) bonusak **metatzen** ditu:
   `porralari.Generala = Σ bere txirri.orokorra`, `porralari.Mendikoa = Σ bere txirri.mendia`.
   Azken puntuazioa (bai txirri, bai porralari) = **etapak + orokorra + mendia**. Admin paneleko
   **Puntu finalak** tresnak dortsalez lotu, dena kalkulatu, `TxapelketaEmaitza*` bietan idatzi
   (postuekin) eta txapelketa **ixten** du (`Amaituta = 1`, `Porra_Irekita = 0`).

Webguneak lehenik `TxapelketaEmaitza*` erakusten du; hori ezean, azken
`TxapelketaSailkapena*` (fallback-a `db-loader.js`-n).
