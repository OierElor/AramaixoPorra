/**
 * Txapelketa guztien konfigurazioa — EGIA-ITURRI BAKARRA.
 * Itzuli handiak (Tour / Giro / Vuelta) eta Klasikak, denak egitura berarekin.
 *
 * Urte berri bat gehitzeko: sarrera bat gehitu `urteak`-en eta orri-stub bat kopiatu.
 *   { id, arauak, dortsalak, porrak, profilaDir, profilaIrudia, irudiak }
 *
 *  - id            : Txapelketa_ID. `null` bada (edo DBan karrerarik ez badu),
 *                    sailkapen-taulak EZ dira erakusten.
 *  - arauak/dortsalak/porrak : PDF fitxategi-izena (URL-kodetua). Ez badago, kendu.
 *  - profilaDir    : karrera-irudien karpeta (`irudiBase`-tik zintzilik).
 *  - profilaIrudia : ibilbide osoaren irudia, orriaren goialdean (itzuliak).
 *  - irudiak       : klasikoetan bakarrik. { Karrerak_ID: 'fitxategia.png' }.
 *                    Ez badago, itzulien patroia erabiltzen da: `Etapa{N}.jpg|.png`.
 */
(function () {
    'use strict';

    window.TXAPELKETAK = {
        pdfBase: '/data/Arauak%2C%20TxirrindulariZerrenda%20eta%20Porrak/',
        irudiBase: '/data/Etapen Profila/',

        kirolak: {
            tour:     { izena: 'Tour de France',  karrerakIzenburua: 'ETAPAZ ETAPA' },
            giro:     { izena: "Giro d'Italia",   karrerakIzenburua: 'ETAPAZ ETAPA' },
            vuelta:   { izena: 'Vuelta a España', karrerakIzenburua: 'ETAPAZ ETAPA' },
            klasikak: { izena: 'Klasikak',        karrerakIzenburua: 'LASTERKETAK' },
        },

        urteak: {
            // ── Tour de France ───────────────────────────────────────────────
            'tour/2023': {
                id: 4,
                arauak: 'Arauak%20tour%2023.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Tour%202023.pdf',
            },
            'tour/2024': {
                id: 5,
                porrak: 'Porrak%20Tour%202024.pdf',
                arauak: 'Arauak%20tour%2024.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Tour%202024.pdf',
            },
            'tour/2025': {
                id: 6,
                porrak: 'Porrak%20Tour%202025.pdf',
                arauak: 'Arauak%20tour%2025.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Tour%202025.pdf',
            },
            'tour/2026': {
                id: 17,
                arauak: 'Arauak%20tour%2026.pdf',
                profilaDir: 'tour/tour26',
                profilaIrudia: 'IbilbideOsoa.jpg',
            },

            // ── Giro d'Italia ────────────────────────────────────────────────
            'giro/2023': {
                id: 1,
                arauak: 'Arauak%20giro%2023.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Giro%202023.pdf',
            },
            'giro/2024': {
                id: 2,
                porrak: 'Porrak%20Giro%202024.pdf',
                arauak: 'Arauak%20giro%2024.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Giro%202024.pdf',
            },
            'giro/2025': {
                id: 3,
                porrak: 'Porrak%20Giro%202025.pdf',
                arauak: 'Arauak%20giro%2025.pdf',
            },
            'giro/2026': {
                id: 16,
                arauak: 'Arauak%20giro%2026.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Giro%202026.pdf',
                profilaDir: 'giro/giro26',
                profilaIrudia: 'IbildideOsoa.jpg',   // izen okerra, baina benetakoa
            },

            // ── Vuelta a España ──────────────────────────────────────────────
            'vuelta/2020': {
                id: 7,
                arauak: 'Arauak%20vuelta%2020.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Vuelta%202020.pdf',
            },
            'vuelta/2021': {
                id: 8,
                arauak: 'Arauak%20vuelta%2021.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Vuelta%202021.pdf',
            },
            'vuelta/2022': {
                id: 9,
                arauak: 'Arauak%20vuelta%2022.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Vuelta%202022.pdf',
            },
            'vuelta/2023': {
                id: 10,
                arauak: 'Arauak%20vuelta%2023.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Vuelta%202023.pdf',
            },
            'vuelta/2024': {
                id: 11,
                porrak: 'Porrak%20Vuelta%202024.pdf',
                arauak: 'Arauak%20vuelta%2024.pdf',
            },
            'vuelta/2025': {
                id: 12,
                porrak: 'Porrak%20Vuelta%202025.pdf',
                arauak: 'Arauak%20vuelta%2025.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Vuelta%202025.pdf',
            },
            'vuelta/2026': {
                id: null,               // oraindik ez da sortu; sortzean, ID-a jarri
                arauak: 'Arauak%20vuelta%2026.pdf',
                profilaDir: 'vuelta/vuelta26',
                profilaIrudia: 'IbilbideOsoa.jpg',
            },

            // ── Klasikak ─────────────────────────────────────────────────────
            // Lasterketa bakoitzak bere ibilbide-irudia du eta izenak ez dira
            // sistematikoak, beraz Karrerak_ID → fitxategia mapa behar da.
            'klasikak/2024': {
                id: 13,
                porrak: 'Porrak%20Klasikoak%202024.pdf',
                arauak: 'Arauak%20klasikak%2024.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Klasikak%202024.pdf',
            },
            'klasikak/2025': {
                id: 14,
                porrak: 'Porrak%20Klasikoak%202025.pdf',
                arauak: 'Arauak%20klasikak%2025.pdf',
                dortsalak: 'Txirrindulari%20Zerrenda%20Klasikak%202025.pdf',
            },
            'klasikak/2026': {
                id: 15,
                porrak: 'Porrak%20Klasikoak%202026.pdf',
                arauak: 'Arauak%20klasikak%202026.pdf',
                dortsalak: 'Txirrindulari%20zerrenda%20klasikak%202026.pdf',
                profilaDir: 'klasikak/klasikak26',
                irudiak: {
                    54: 'Omloop Nieuwsblad.png',
                    55: 'Strade Bianche.png',
                    56: 'Milano - Torino.png',
                    57: 'Milano - Sanremo.png',
                    58: 'Brugge-De Panne.png',
                    59: 'E3 Saxo.png',
                    // 60 Gent-Wevelgem: irudirik ez
                    61: 'a treves de flades.png',
                    62: 'Tour de flandes.png',
                    336: 'paris roubaix.png',
                    337: 'Braranconne.png',
                    338: 'Amstel.png',
                    339: 'fleche wallonne.png',
                    340: 'liege.png',
                    341: 'Frankfurt.png',
                    // 342 Brussels Cycling Classic, 343 Copenhagen Sprint: irudirik ez
                },
            },
        },
    };

    /** Bidetik kirola/urtea atera: "/tour/2026/", "/klasikak/2026", ".../index.html" */
    window.TXAPELKETAK.bidea = function () {
        const p = location.pathname.replace(/\.html?$/i, '').replace(/\/index$/i, '');
        const m = p.match(/\/(tour|giro|vuelta|klasikak)\/(\d{4})\/?$/i);
        if (!m) return null;
        return { kirola: m[1].toLowerCase(), urtea: m[2] };
    };

    /** Kirol baten urteak, ordenatuta. */
    window.TXAPELKETAK.kirolUrteak = function (kirola) {
        return Object.keys(window.TXAPELKETAK.urteak)
            .filter(k => k.startsWith(kirola + '/'))
            .map(k => k.split('/')[1])
            .sort();
    };

    /** PDF esteka osoa eraiki (ez badago, null). */
    window.TXAPELKETAK.pdf = function (mota, izena) {
        if (!izena) return null;
        const azpi = { arauak: 'Arauak/', dortsalak: 'TxirrindulariZerrenda/', porrak: 'Porrak/' }[mota];
        return window.TXAPELKETAK.pdfBase + azpi + izena;
    };

    /**
     * Karrera baten irudi-hautagaiak (`db-loader.js`-eko `loadKarrerak`-entzat).
     * Klasikoak: `irudiak` mapako fitxategia (bakarra; ez badago, irudirik ez).
     * Itzuliak: `Etapa{N}.jpg` eta, huts eginez gero, `Etapa{N}.png`.
     * Karpeta-izena zuriunearekin uzten da nahita: CSSko
     * `img[src*="Etapen Profila"]` arauak horrela mugatzen ditu 800 px-ra.
     */
    window.TXAPELKETAK.irudiFn = function (cfg) {
        if (!cfg.profilaDir) return null;
        const oinarria = window.TXAPELKETAK.irudiBase + cfg.profilaDir;
        if (cfg.irudiak) {
            return (karreraId) => {
                const fitx = cfg.irudiak[karreraId];
                return fitx ? [oinarria + '/' + encodeURIComponent(fitx)] : [];
            };
        }
        return (_karreraId, n) => [`${oinarria}/Etapa${n}.jpg`, `${oinarria}/Etapa${n}.png`];
    };
})();
