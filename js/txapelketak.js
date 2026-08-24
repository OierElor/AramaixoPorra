/**
 * Txapelketa guztien konfigurazioa — EGIA-ITURRI BAKARRA.
 * Itzuli handiak (Tour / Giro / Vuelta) eta Klasikak, denak egitura berarekin.
 *
 * Urte berri bat gehitzeko: sarrera bat gehitu `urteak`-en eta orri-stub bat kopiatu.
 *   { id, arauak, dortsalak, porrak, profilaDir, profilaIrudia, irudiak }
 *
 *  - id            : Txapelketa_ID. AUKERAKOA: `null`/kendua bada, `txapelketa-orria.js`-k
 *                    AUTO-LOTZEN du DBan (kirol-izena + urtea bat eginez). Beraz normalean
 *                    ez da jarri behar; jarri soilik DBko izena kanonikoarekin bat ez badator.
 *                    (DBan karrerarik ez badu, sailkapen-taulak EZ dira erakusten.)
 *  - arauak/dortsalak/porrak : PDF fitxategi-izena. Ez badago, kendu.
 *  - profilaDir    : karrera-irudien azpikarpeta (`karpetak.profilak`-etik zintzilik).
 *  - profilaIrudia : ibilbide osoaren irudia, orriaren goialdean (itzuliak).
 *  - irudiak       : klasikoetan bakarrik. { Karrerak_ID: 'fitxategia.png' }.
 *                    Ez badago, itzulien patroia erabiltzen da: `Etapa{N}.jpg|.png`.
 *
 * KARPETAK: fitxategi-mota bakoitzaren karpeta `karpetak`-en dago. Balio lehenetsiak
 * hemen daude, baina `txapelketa-orria.js`-k `/api/ezarpenak.php`-tik freskatzen ditu:
 * adminak karpeta bat aldatzen badu (admin panela → Fitxategiak), gunea berehala moldatzen
 * da. Fetch-ak huts eginez gero lehenetsiek balio dute → gunea ez da inoiz hausten.
 */
(function () {
    'use strict';

    window.TXAPELKETAK = {
        // Karpeta lehenetsiak (api/ezarpenak.php-koekin bat etorri behar dute)
        karpetak: {
            arauak:    'arauak',
            dortsalak: 'dortsalak',
            porrak:    'porrak',
            profilak:  'profilak',
        },

        // Tresna publikoen ikusgaitasuna (id → bool), karpetakKargatu()-k betetzen du.
        // Hutsik dagoen bitartean (fetch-a egin aurretik) tresnaIkusgai()-k `true` itzultzen
        // du beti (fail-open) — admin panela → Webgunea.
        tresnak: {},

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
                arauak: 'Arauak tour 23.pdf',
                dortsalak: 'Txirrindulari Zerrenda Tour 2023.pdf',
            },
            'tour/2024': {
                id: 5,
                porrak: 'Porrak Tour 2024.pdf',
                arauak: 'Arauak tour 24.pdf',
                dortsalak: 'Txirrindulari Zerrenda Tour 2024.pdf',
            },
            'tour/2025': {
                id: 6,
                porrak: 'Porrak Tour 2025.pdf',
                arauak: 'Arauak tour 25.pdf',
                dortsalak: 'Txirrindulari Zerrenda Tour 2025.pdf',
            },
            'tour/2026': {
                id: 17,
                arauak: 'Arauak tour 26.pdf',
                profilaDir: 'tour26',
                profilaIrudia: 'IbilbideOsoa.jpg',
            },

            // ── Giro d'Italia ────────────────────────────────────────────────
            'giro/2023': {
                id: 1,
                arauak: 'Arauak giro 23.pdf',
                dortsalak: 'Txirrindulari Zerrenda Giro 2023.pdf',
            },
            'giro/2024': {
                id: 2,
                porrak: 'Porrak Giro 2024.pdf',
                arauak: 'Arauak giro 24.pdf',
                dortsalak: 'Txirrindulari Zerrenda Giro 2024.pdf',
            },
            'giro/2025': {
                id: 3,
                porrak: 'Porrak Giro 2025.pdf',
                arauak: 'Arauak giro 25.pdf',
            },
            'giro/2026': {
                id: 16,
                arauak: 'Arauak giro 26.pdf',
                dortsalak: 'Txirrindulari Zerrenda Giro 2026.pdf',
                profilaDir: 'giro26',
                profilaIrudia: 'IbildideOsoa.jpg',   // izen okerra, baina benetakoa
            },

            // ── Vuelta a España ──────────────────────────────────────────────
            'vuelta/2020': {
                id: 7,
                arauak: 'Arauak vuelta 20.pdf',
                dortsalak: 'Txirrindulari Zerrenda Vuelta 2020.pdf',
            },
            'vuelta/2021': {
                id: 8,
                arauak: 'Arauak vuelta 21.pdf',
                dortsalak: 'Txirrindulari Zerrenda Vuelta 2021.pdf',
            },
            'vuelta/2022': {
                id: 9,
                arauak: 'Arauak vuelta 22.pdf',
                dortsalak: 'Txirrindulari Zerrenda Vuelta 2022.pdf',
            },
            'vuelta/2023': {
                id: 10,
                arauak: 'Arauak vuelta 23.pdf',
                dortsalak: 'Txirrindulari Zerrenda Vuelta 2023.pdf',
            },
            'vuelta/2024': {
                id: 11,
                porrak: 'Porrak Vuelta 2024.pdf',
                arauak: 'Arauak vuelta 24.pdf',
            },
            'vuelta/2025': {
                id: 12,
                porrak: 'Porrak Vuelta 2025.pdf',
                arauak: 'Arauak vuelta 25.pdf',
                dortsalak: 'Txirrindulari Zerrenda Vuelta 2025.pdf',
            },
            'vuelta/2026': {
                id: 18,
                arauak: 'Arauak vuelta 26.pdf',
                dortsalak: 'Txirrindulari Zerrenda Vuelta 2026.pdf',
                profilaDir: 'vuelta26',
                profilaIrudia: 'IbilbideOsoa.jpg',
            },

            // ── Klasikak ─────────────────────────────────────────────────────
            // Lasterketa bakoitzak bere ibilbide-irudia du eta izenak ez dira
            // sistematikoak, beraz Karrerak_ID → fitxategia mapa behar da.
            'klasikak/2024': {
                id: 13,
                porrak: 'Porrak Klasikoak 2024.pdf',
                arauak: 'Arauak klasikak 24.pdf',
                dortsalak: 'Txirrindulari Zerrenda Klasikak 2024.pdf',
            },
            'klasikak/2025': {
                id: 14,
                porrak: 'Porrak Klasikoak 2025.pdf',
                arauak: 'Arauak klasikak 25.pdf',
                dortsalak: 'Txirrindulari Zerrenda Klasikak 2025.pdf',
            },
            'klasikak/2026': {
                id: 15,
                porrak: 'Porrak Klasikoak 2026.pdf',
                arauak: 'Arauak klasikak 2026.pdf',
                dortsalak: 'Txirrindulari zerrenda klasikak 2026.pdf',
                profilaDir: 'klasikak26',
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

    /** Mota baten karpeta (uneko ezarpenetatik). */
    window.TXAPELKETAK.karpeta = function (mota) {
        return window.TXAPELKETAK.karpetak[mota] || mota;
    };

    /** Fitxategi baten URL publikoa: /data/<karpeta>/<azpibidea>. Segmentuak kodetuta. */
    window.TXAPELKETAK.url = function (mota, ...zatiak) {
        const seg = [window.TXAPELKETAK.karpeta(mota), ...zatiak]
            .filter(Boolean)
            .flatMap(z => String(z).split('/'))
            .map(encodeURIComponent);
        return '/data/' + seg.join('/');
    };

    /** PDF esteka osoa eraiki (ez badago, null). */
    window.TXAPELKETAK.pdf = function (mota, izena) {
        if (!izena) return null;
        // Konfigurazioan izenak URL-kodetuta egon daitezke (historikoa) → dekodetu lehenik,
        // `url()`-k segmentu bakoitza behin bakarrik kodetu dezan.
        let garbia = izena;
        try { garbia = decodeURIComponent(izena); } catch (e) { /* kodeketa okerra: bere horretan */ }
        return window.TXAPELKETAK.url(mota, garbia);
    };

    /**
     * Karrera baten irudi-hautagaiak (`db-loader.js`-eko `loadKarrerak`-entzat).
     * Klasikoak: `irudiak` mapako fitxategia (bakarra; ez badago, irudirik ez).
     * Itzuliak: `Etapa{N}.jpg` eta, huts eginez gero, `Etapa{N}.png`.
     */
    window.TXAPELKETAK.irudiFn = function (cfg) {
        const U = (fitx) => window.TXAPELKETAK.url('profilak', cfg.profilaDir, fitx);
        return (karreraId, n, profila) => {
            // 1) Lotura ESPLIZITUA (Karrerak.Profil_Irudia): profilak-etik zintzilik, adib.
            //    'tour26/Etapa9.jpg'. Karpeta-aldaketa jasaten du.
            if (profila) return [window.TXAPELKETAK.url('profilak', profila)];
            if (!cfg.profilaDir) return [];
            // 2) Klasikoen mapa (kodean).
            if (cfg.irudiak) {
                const fitx = cfg.irudiak[karreraId];
                return fitx ? [U(fitx)] : [];
            }
            // 3) Izen-konbentzioa (itzuliak): Etapa{Ordena}.jpg|.png.
            return [U(`Etapa${n}.jpg`), U(`Etapa${n}.png`)];
        };
    };

    /**
     * Karpeta-mapa eta tresna-ikusgaitasuna freskatu `/api/ezarpenak.php`-tik.
     * Huts eginez gero, lehenetsiak (karpetak) / dena ikusgai (tresnak).
     */
    window.TXAPELKETAK.karpetakKargatu = async function () {
        try {
            const r = await fetch('/api/ezarpenak.php', { cache: 'no-cache' });
            if (!r.ok) return;
            const d = await r.json();
            if (d && d.karpetak) Object.assign(window.TXAPELKETAK.karpetak, d.karpetak);
            if (d && Array.isArray(d.tresnak)) {
                d.tresnak.forEach(t => { window.TXAPELKETAK.tresnak[t.id] = !!t.ikusgai; });
            }
        } catch (e) {
            /* lehenetsiekin jarraitu */
        }
    };

    /** Tresna bat ikusgai dagoen. Fetch-a egin ez bada oraindik, `true` (fail-open). */
    window.TXAPELKETAK.tresnaIkusgai = function (id) {
        return window.TXAPELKETAK.tresnak[id] !== false;
    };
})();
