# Karrera Motak — Irizpideak

Admin paneleko Karrerak taulan `Mota` zutabea agertzen da. Fitxategi honek mota bakoitzaren irizpideak azaltzen ditu.

---

## Itzuli handien etapak (`Kategoria = 'Etapa'`)

Itzuli handietako etapa bakoitzak hauetako bat izango du:

| Mota | Ezaugarriak | Adibideak |
|---|---|---|
| **Lauzepa** | Perfil laua, sprint masiboarekin bukatzen da. Altimetria txikia. | Giro 1. etapa (launa), Tour sprint etapak |
| **Muinotua** | Muino txikiak edo harri-zorua, trantsizioa, taldeen jokoa. Ez sprint garbia, ez mendiko heltzea. | Cobblestone etapak, muinodun etapak |
| **Menditsua** | Mendiak baina ez gailur-heltzea. Ihesa posible. Azken 20-30km-tan desnibela. | Erdi-mendiko etapak, puntuazio-etapak |
| **Goi-menditsua** | Gailur-heltzea (summit finish). Eskalatzaileak nagusi. | Alpe d'Huez, Stelvio, Col du Tourmalet |
| **Denbora-proba** | Banakako kronometrikoa (ITT). Txirrindulari bakoitza bakarrik. | Giro ITT, Tour CRI |
| **Talde denbora-proba** | Taldeko kronometrikoa (TTT). Taldeak batera. | Giro TTT, Vuelta TTT |

### Erabaki-arbola (itzuli etapak)

```
Kronometrikoa da?
  ├─ Bai, taldekoa → Talde denbora-proba
  ├─ Bai, banakoa → Denbora-proba
  └─ Ez → Gailur-heltzea du?
      ├─ Bai → Goi-menditsua
      └─ Ez → Mendiak ditu baina ez gailurra?
          ├─ Bai → Menditsua
          └─ Ez → Cobble/muino txikiak?
              ├─ Bai → Muinotua
              └─ Ez → Lauzepa
```

---

## Klasikak (`Kategoria ≠ 'Etapa'`)

Klasiken kasuan profila ETA egutegi-garaia kontuan hartzen dira:

| Mota | Garaia | Profila | Adibideak |
|---|---|---|---|
| **Udaberri irekiera** | Feb–Mar | Anitza, denboraldi hasiera | Strade Bianche, Omloop Het Nieuwsblad |
| **Esprinta** | Edozein | Launa, sprint masiboarekin bukatzen da | Milan-San Remo, Scheldeprijs |
| **Flandriar** | Mar–Apr | Muino txiki zorrotzak + harri-zorua (Flandriako estiloa) | Tour of Flanders, E3 Saxo Bank, A través de Flandes, Gent-Wevelgem |
| **Harri-zorua** | Apiril | Pavé (harri-zoru frankofonia) nagusi | Paris-Roubaix |
| **Ardenak** | Apiril | Aldapa labur eta zorrotz (Ardennetako estiloa) | Amstel Gold Race, La Flèche Wallonne, Liège-Bastogne-Liège |
| **Udako klasika** | Uztail–Iraila | Anitza, urte erdi-bukaerako klasikak | Donostia Klasikoa, GP Quebec, GP Montreal |
| **Mendi-klasika** | Urria | Mendiko heltze luzeak, denboraldi amaiera | Il Lombardia, Giro dell'Emilia |

### Erabaki-arbola (klasikak)

```
Harri-zorua (pavé) nagusi da?
  ├─ Bai → Harri-zorua
  └─ Ez → Flandriako muino txikiak (Koppenberg, Oude Kwaremont...)?
      ├─ Bai → Flandriar
      └─ Ez → Ardennetako aldapa zorrotzak (Mur de Huy, La Redoute...)?
          ├─ Bai → Ardenak
          └─ Ez → Mendiko heltze luzea + urria?
              ├─ Bai → Mendi-klasika
              └─ Ez → Denboraldi hasiera (Feb–Mar)?
                  ├─ Bai → Udaberri irekiera
                  └─ Ez → Sprint garbia?
                      ├─ Bai → Esprinta
                      └─ Ez → Udako klasika
```

---

## Zalantza-kasuak

### Strade Bianche
→ **Udaberri irekiera** (ez Flandriar). Toskanako gravelda jokatzen da, ez Flandriako harri-zoruetan. Garaia ere Feb–Mar.

### Milan-San Remo
→ **Esprinta**. Profila altua du (Cipressa, Poggio) baina normalean esprintariek irabazten dute. Flandriar ez da.

### Kuurne-Bruxelles-Kuurne
→ **Flandriar**. Flandriako muinoak ditu, Omloop-en hurrengo egunean jokatzen da.

### Donostia Klasikoa
→ **Udako klasika**. Uztailean jokatzen da, profil aldapatsua du baina Ardenak ez da.

### A través de Flandes
→ **Flandriar**. Belgikan jokatzen da, Flandriako muinoak ditu.

### GP Quebec / GP Montreal
→ **Udako klasika**. Irailean Kanadan jokatzen dira.

---

## Admin paneleko erabileraren oharra

Karrera baten Mota admin paneleko **Txapelketak** atalean dagoen **Karrerak** taulatik aldatzen da:
- `Kategoria = 'Etapa'` duten karrerek itzuli-moten zerrenda erakusten dute
- Beste Kategoria guztiek klasika-moten zerrenda erakusten dute
- Aldaketa automatikoki gordetzen da dropdown-a aldatzean
