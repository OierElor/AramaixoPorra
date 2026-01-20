# CSV Sistema Erabiltzeko Gida

## Zer da hau?

Sistema honek zure Excel fitxategitik datuak automatikoki kargatzea ahalbidetzen du webgunean. HTML-a editatu beharrik gabe, Excel fitxategia bakarrik aldatu behar duzu.

---

## 📋 Egunero nola erabili

### 1. Pausua: Excel-en datuak eguneratu

Ireki zure Excel fitxategia eta eguneratu sailkapena:

| Posizioa | Porreoa | Gaur | Guztira |
|----------|---------|------|---------|
| 1 | Androni | 395 | 1434 |
| 2 | Pastrana | 435 | 1433 |
| ... | ... | ... | ... |

**Garrantzitsua:** Lehen errenkada (goiburuak) ez aldatu: `Posizioa,Porreoa,Gaur,Guztira`

### 2. Pausua: CSV gisa gorde

1. Excel-en: **File → Save As**
2. Aukeratu **CSV (Comma delimited) (*.csv)**
3. Gorde hemen: `/home/oier/AramaixoPorra/src/data/vuelta/2025_etapa_XX.csv`
   - Ordeztu `XX` etapa zenbakiarekin (adib: `2025_etapa_01.csv`)

### 3. Pausua: HTML-a eguneratu (etapa berria bada)

Etapa berri bat hasi baduzu, editatu HTML fitxategia:

**Fitxategia:** `/home/oier/AramaixoPorra/src/html/vuelta/2025.html`

Aldatu script-aren azken zatia:

```javascript
const csvPath = '../../data/vuelta/2025_etapa_23.csv';  // ← Aldatu etapa zenbakia
const metadata = {
    etapa: '23',        // ← Aldatu etapa zenbakia
    tokia: 'Madrid',    // ← Aldatu tokia
    data: '25/09/14'    // ← Aldatu data
};
```

### 4. Pausua: Webgunea ikusi

Ireki nabigatzailean: `file:///home/oier/AramaixoPorra/src/html/vuelta/2025.html`

Datuak automatikoki kargatuko dira!

---

## 📁 Fitxategi egitura

```
src/
├── data/
│   ├── vuelta/
│   │   ├── 2025_etapa_01.csv
│   │   ├── 2025_etapa_02.csv
│   │   └── 2025_etapa_23.csv
│   ├── tour/
│   │   └── 2025_etapa_01.csv
│   └── giro/
│       └── 2025_etapa_01.csv
├── js/
│   └── csv-loader.js
└── html/
    └── vuelta/
        └── 2025.html
```

---

## ⚠️ Ohiko erroreak

### Errorea: "Error kargatzean"

**Arrazoia:** CSV fitxategia ez da aurkitu edo izen okerra du.

**Konponketa:**
1. Egiaztatu CSV fitxategiaren izena zuzena dela
2. Egiaztatu kokapena zuzena dela: `src/data/vuelta/`
3. Egiaztatu HTML-ko `csvPath` zuzena dela

### Errorea: Datuak ez dira agertzen

**Arrazoia:** CSV formatua okerra da.

**Konponketa:**
1. Egiaztatu lehen errenkada: `Posizioa,Porreoa,Gaur,Guztira`
2. Egiaztatu koma (`,`) erabiltzen dela bereizle gisa
3. Ez utzi errenkada hutsik

---

## 🎯 Adibide osoa

### Excel fitxategia:

```
Posizioa,Porreoa,Gaur,Guztira
1,Androni,395,1434
2,Pastrana,435,1433
3,Globulos Verdes,465,1404
```

### Gorde hemen:

`/home/oier/AramaixoPorra/src/data/vuelta/2025_etapa_23.csv`

### Emaitza:

Webgunean automatikoki agertuko da taula datuak kargatuta!

---

## 💡 Aholkuak

1. **Backup-a egin:** Gorde Excel fitxategiaren kopia bat aldaketak egin aurretik
2. **Probatu:** Aldaketa txikiak egin eta probatu webgunea
3. **Goiburuak:** Ez aldatu inoiz lehen errenkadako goiburuak
4. **Formatua:** Ziurtatu zenbakiak zenbaki gisa gordetzea (ez testua)

---

## 🚀 Beste lasterbideak

### Tour-erako:
- CSV hemen gorde: `src/data/tour/2025_etapa_XX.csv`
- HTML: `src/html/tour/2025.html`

### Giro-rako:
- CSV hemen gorde: `src/data/giro/2025_etapa_XX.csv`
- HTML: `src/html/giro/2025.html`

---

Zalantzarik baduzu, begiratu adibide fitxategiari: `src/data/vuelta/2025_etapa_23.csv`
