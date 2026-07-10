/**
 * Itzuli handien konfigurazioa (Tour / Giro / Vuelta) — EGIA-ITURRI BAKARRA.
 *
 * Urte berri bat gehitzeko: sarrera bat gehitu `urteak`-en eta orri-stub-ak kopiatu.
 *   { id, arauak, dortsalak, porrak, profilaDir, profilaIrudia }
 *
 *  - id            : Txapelketa_ID. `null` bada (edo DBan karrerarik ez badu),
 *                    sailkapen-taulak EZ dira erakusten.
 *  - arauak/dortsalak/porrak : PDF fitxategi-izena (URL-kodetua). Ez badago, kendu.
 *  - profilaDir    : ibilbide/etapa irudien karpeta (akordeoiko etapa-profiletarako ere).
 *  - profilaIrudia : ibilbide osoaren irudia.
 */
(function () {
    'use strict';

    window.ITZULIAK = {
        pdfBase: '/data/Arauak%2C%20TxirrindulariZerrenda%20eta%20Porrak/',
        irudiBase: '/data/Etapen Profila/',

        kirolak: {
            tour:   { izena: 'Tour de France' },
            giro:   { izena: "Giro d'Italia" },
            vuelta: { izena: 'Vuelta a España' },
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
        },
    };

    /** Bidetik kirola/urtea atera: "/tour/2026/", "/tour/2026", "/tour/2026/index.html" */
    window.ITZULIAK.bidea = function () {
        const p = location.pathname.replace(/\.html?$/i, '').replace(/\/index$/i, '');
        const m = p.match(/\/(tour|giro|vuelta)\/(\d{4})\/?$/i);
        if (!m) return null;
        return { kirola: m[1].toLowerCase(), urtea: m[2] };
    };

    /** Kirol baten urteak, ordenatuta. */
    window.ITZULIAK.kirolUrteak = function (kirola) {
        return Object.keys(window.ITZULIAK.urteak)
            .filter(k => k.startsWith(kirola + '/'))
            .map(k => k.split('/')[1])
            .sort();
    };

    /** PDF esteka osoa eraiki (ez badago, null). */
    window.ITZULIAK.pdf = function (mota, izena) {
        if (!izena) return null;
        const azpi = { arauak: 'Arauak/', dortsalak: 'TxirrindulariZerrenda/', porrak: 'Porrak/' }[mota];
        return window.ITZULIAK.pdfBase + azpi + izena;
    };
})();
