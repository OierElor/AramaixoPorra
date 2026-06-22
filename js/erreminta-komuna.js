/**
 * Erreminta komuna — Erremintak ataleko orriek partekatzen duten logika
 * (porra-fitxa, konparatzailea...). window.dbLoader behar du.
 */
const Erreminta = {
    COLORS: [
        '#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#42d4f4',
        '#f032e6', '#bfef45', '#fabed4', '#469990', '#9a6324', '#808000',
    ],

    async q(sql, params = []) {
        return window.dbLoader.query(sql, params);
    },

    /** Txapelketak (berrienak lehenik). */
    async txapelketak() {
        return this.q("SELECT Txapelketa_ID AS id, Izena AS izena, Urtea AS urtea " +
            "FROM Txapelketak ORDER BY Urtea DESC, Izena");
    },

    /** Txapelketako benetako karrerak ordenan (agregatu 'Azken Karrera' kanpo). */
    async karrerak(tid) {
        return this.q("SELECT Karrerak_ID AS kid, Izena AS izena FROM Karrerak " +
            "WHERE Txapelketa_ID = ? AND Kategoria IS NOT NULL AND Kategoria <> '' " +
            "ORDER BY Karrerak_ID", [tid]);
    },

    karreraLabel(izena) {
        const p = String(izena).split(' - ');
        return p.length > 1 ? p[p.length - 1] : izena;
    },

    /**
     * Txapelketako porra guztiak: id, izena, etapaTot (etapa-puntuen batura),
     * ofPos/ofPts (emaitza ofiziala, baldin badago). Postu ofizialaren arabera
     * ordenatuta (edo etapaTot-en arabera ofizialik ez bada). pos eremua gehitzen da.
     */
    async porrak(tid) {
        const rows = await this.q(
            "SELECT ez.Ezizen_ID AS id, ez.Ezizena AS izena, " +
            " (SELECT COALESCE(SUM(ks.Puntuak),0) FROM PorraApustuak pa " +
            "  JOIN KarreraSailkapena ks ON ks.Txirrindularia_ID = pa.Txirrindularia_ID " +
            "  JOIN Karrerak k ON k.Karrerak_ID = ks.Karrera_ID " +
            "  WHERE pa.Ezizen_ID = ez.Ezizen_ID AND k.Txapelketa_ID = ez.Txapelketa_ID " +
            "    AND k.Kategoria <> '') AS etapaTot, " +
            " e.Posizioa AS ofPos, e.Puntuak AS ofPts " +
            "FROM PorraEzizenak ez " +
            "LEFT JOIN TxapelketaEmaitzaPorralariak e " +
            "  ON e.Ezizen_ID = ez.Ezizen_ID AND e.Txapelketa_ID = ez.Txapelketa_ID " +
            "WHERE ez.Txapelketa_ID = ?", [tid]);
        const hasOfficial = rows.some(r => r.ofPos != null);
        rows.sort((a, b) => {
            if (hasOfficial) return (a.ofPos ?? 1e9) - (b.ofPos ?? 1e9);
            return b.etapaTot - a.etapaTot;
        });
        rows.forEach((r, i) => { r.pos = r.ofPos ?? (i + 1); r.total = r.ofPts ?? r.etapaTot; });
        return rows;
    },

    /** Porra baten taldea: txirrindulariak + etapa-puntuak (handienetik). */
    async taldea(tid, ezId) {
        return this.q(
            "SELECT t.Izena AS izena, h.Dortsala AS dortsala, " +
            " (SELECT COALESCE(SUM(ks.Puntuak),0) FROM KarreraSailkapena ks " +
            "  JOIN Karrerak k ON k.Karrerak_ID = ks.Karrera_ID " +
            "  WHERE ks.Txirrindularia_ID = pa.Txirrindularia_ID " +
            "    AND k.Txapelketa_ID = pa.Txapelketa_ID AND k.Kategoria <> '') AS pts " +
            "FROM PorraApustuak pa " +
            "JOIN Txirrindulariak t ON t.Txirrindularia_ID = pa.Txirrindularia_ID " +
            "LEFT JOIN TxirrindulariakTxapleketanParteHartzea h " +
            "  ON h.TxapelketaID = pa.Txapelketa_ID AND h.TxirrindulariaID = pa.Txirrindularia_ID " +
            "WHERE pa.Txapelketa_ID = ? AND pa.Ezizen_ID = ? " +
            "ORDER BY pts DESC, t.Izena", [tid, ezId]);
    },

    /**
     * Porra baten puntu metatuak karrera bakoitzean (karrera-ordenan lerrokatuta).
     * @returns {number[]} karrerak.length luzerako array metatua.
     */
    async eboluzioa(tid, ezId, karrerak) {
        const rows = await this.q(
            "SELECT k.Karrerak_ID AS kid, COALESCE(SUM(ks.Puntuak),0) AS pts " +
            "FROM Karrerak k " +
            "JOIN KarreraSailkapena ks ON ks.Karrera_ID = k.Karrerak_ID " +
            "JOIN PorraApustuak pa ON pa.Txirrindularia_ID = ks.Txirrindularia_ID " +
            "  AND pa.Txapelketa_ID = k.Txapelketa_ID " +
            "WHERE k.Txapelketa_ID = ? AND k.Kategoria <> '' AND pa.Ezizen_ID = ? " +
            "GROUP BY k.Karrerak_ID", [tid, ezId]);
        const byKid = new Map(rows.map(r => [r.kid, r.pts]));
        const cum = [];
        let s = 0;
        karrerak.forEach(k => { s += (byKid.get(k.kid) || 0); cum.push(s); });
        return cum;
    },

    /** Chart.js lerro-grafiko baten konfigurazio komuna (eboluziorako). */
    lineChart(canvas, karrerak, datasets, yTitle) {
        const fullNames = karrerak.map(k => Erreminta.karreraLabel(k.izena));
        return new Chart(canvas, {
            type: 'line',
            data: { labels: karrerak.map((_, i) => i + 1), datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { title: items => fullNames[items[0].dataIndex] } },
                },
                scales: {
                    x: { title: { display: true, text: 'Karrera' } },
                    y: { beginAtZero: true, title: { display: true, text: yTitle || 'Puntu metatuak' } },
                },
            },
        });
    },
};
