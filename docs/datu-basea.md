# Datu-basea

MySQL datu-basea Hostalian (`PMYSQL104.dns-servicio.com`, `6437239_aramaixoporra`).
**13 taula.** Eskema phpMyAdmin bidez kudeatzen da; `db/Datuak 260707.sql` dump-a
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
| **Txapelketak** | `Txapelketa_ID` (PK), `Izena`, `Urtea` | Txapelketak (adib. "Tour de France", 2026) |
| **Karrerak** | `Karrerak_ID` (PK), `Txapelketa_ID`→, `Izena`, `Urtea`, `Kategoria` | Etapak/lasterketak. `Kategoria` = 'Etapa' (itzuliak) edo UCI kodea (klasikoak). Hutsik = ez da puntuaziorako zenbatzen |
| **Txirrindulariak** | `Txirrindularia_ID` (PK), `Izena`, `Izen_Formatua` | Txirrindulariak. `Izen_Formatua` = "Izena"/"Abizena" tokenak (izen-ordena kudeatzeko) |
| **Porralariak** | `Porralaria_ID` (PK), `Izena`, `Zenbat_Porra` | Jokalariak. `Zenbat_Porra` = zenbat porra jokatu dituen (kalkulatua) |

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

Webguneak lehenik `TxapelketaEmaitza*` erakusten du; hori ezean, azken
`TxapelketaSailkapena*` (fallback-a `db-loader.js`-n).
