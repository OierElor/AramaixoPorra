/**
 * Txapelketa baten URTE-orria marrazten du: Tour / Giro / Vuelta eta Klasikak.
 * Denek egitura BERA dute; dena bidetik eta `js/txapelketak.js` konfiguraziotik
 * ateratzen da.
 *
 * Sailkapen-taulak SOILIK erakusten dira txapelketa DBan existitzen bada
 * ETA karrerarik badu. Bestela "ez dago daturik" oharra.
 *
 * `layout.js` baino LEHEN kargatu behar da (defer), goiburua/oina inguruan gera daitezen.
 */
(function () {
    'use strict';

    const C = window.TXAPELKETAK;
    if (!C) return;
    const loc = C.bidea();
    if (!loc) return;   // urte-orria soilik

    const { kirola, urtea } = loc;
    const meta = C.kirolak[kirola];
    const cfg = C.urteak[kirola + '/' + urtea];
    if (!meta || !cfg) return;

    const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    document.title = `Aramaixo Porra - ${meta.izena} ${urtea}`;

    // ── Nabigazioa (aurreko / hurrengo urtea) ────────────────────────────────
    const urteak = C.kirolUrteak(kirola);
    const i = urteak.indexOf(urtea);
    const aurrekoa = i > 0 ? urteak[i - 1] : null;
    const hurrengoa = i >= 0 && i < urteak.length - 1 ? urteak[i + 1] : null;

    const gezia = (u, ikurra, label) => u
        ? `<a href="/${kirola}/${u}/" class="etapa-arrow ${kirola}" aria-label="${label}">${ikurra}</a>`
        : `<button class="etapa-arrow ${kirola} disabled" disabled aria-label="${label}">${ikurra}</button>`;

    const nav = `
        <div class="etapa-navegazioa ${kirola}">
            ${gezia(aurrekoa, '&#8592;', 'Aurrekoa')}
            <span class="etapa-info ${kirola}">${urtea}</span>
            ${gezia(hurrengoa, '&#8594;', 'Hurrengoa')}
        </div>`;

    // ── Karpeta-mapatik eratorriak (PDFak + ibilbide-irudia) ─────────────────
    // Karpeta-mapa kargatu ONDOREN deitzen dira, adminak karpeta bat aldatu badu ere
    // esteka zuzenak sor daitezen.
    function deskargakMarraztu() {
        const botoia = (mota, izena, testua) => {
            const href = C.pdf(mota, izena);
            return href ? `<a href="${href}" class="download-button ${kirola}" target="_blank">${testua}</a>` : '';
        };
        const webBotoia = (cfg.webOfiziala && /^https?:\/\//i.test(cfg.webOfiziala))
            ? `<a href="${esc(cfg.webOfiziala)}" class="download-button ${kirola}" target="_blank" rel="noopener noreferrer">WEBGUNE OFIZIALA ↗</a>`
            : '';
        const pdfak = [
            botoia('porrak', cfg.porrak, 'PORRALARIEN LISTA (PDF)'),
            botoia('arauak', cfg.arauak, 'ARAUAK (PDF)'),
            botoia('dortsalak', cfg.dortsalak, 'DORTSALAK (PDF)'),
            webBotoia,
        ].filter(Boolean).join('\n');
        const el = document.getElementById('itz-deskargak');
        if (el && pdfak) el.innerHTML = `<div class="download-section">${pdfak}</div>`;
    }

    /** Ibilbide osoaren irudia (itzuliak; klasikoek ez dute). */
    function profilaMarraztu() {
        if (!cfg.profilaIrudia || !cfg.profilaDir) return;
        const el = document.getElementById('itz-profila');
        if (!el) return;
        const src = C.url('profilak', cfg.profilaDir, cfg.profilaIrudia);
        el.innerHTML = `<div class="profile-container">
               <img class="profil-irudia" src="${src}"
                    alt="${esc(meta.izena)} ${urtea} Ibilbide Osoa"
                    onerror="this.style.display='none'">
           </div>`;
    }

    // Egitura SINKRONIKOKI txertatzen da (ez await honen aurretik): `layout.js`-ek oina
    // `beforeend` jartzen du, eta hemen itxarongo bagenu, edukia oinaren ONDOREN geratuko
    // litzateke. Karpeta-mapa behar duten zatiak (PDFak, profil-irudia) gero betetzen dira.
    document.body.insertAdjacentHTML('beforeend', `
        ${nav}
        <div class="container">
            <main>
                <section>
                    <h2 class="${kirola}">${esc(meta.izena)} ${urtea}</h2>
                    <div id="itz-deskargak"></div>
                    <div id="itz-profila"></div>
                    <div id="porra-banner"></div>
                    <div id="itz-taulak"></div>
                </section>
            </main>
        </div>`);

    // ── Taulak: txapelketa existitzen bada ETA karrerak baditu ───────────────
    const edukiontzia = () => document.getElementById('itz-taulak');

    const oharra = () => {
        const el = edukiontzia();
        if (el) el.innerHTML = `<p style="text-align:center; opacity:.6; margin-top:24px;">
            Ez dago daturik oraindik. Txapelketa hasten denean agertuko dira sailkapenak.</p>`;
    };

    const taula = (izenburua, id) => `
        <div class="table-wrapper" style="width: 100%; overflow-x: auto;">
            <h3 class="${kirola}" style="text-align: center;">${izenburua}</h3>
            <div class="table-content">
                <table class="sailkapena-table ${kirola}" id="${id}" style="width: 100%;">
                    <thead><tr><th>Pos</th><th>Izena</th><th>Puntuak</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>`;

    /**
     * "Porra irekita" banner-a (Txapelketak.Porra_Irekita = 1).
     *
     * Kontsulta BEREIZIA da nahita, bi arrazoirengatik:
     *  1. Txapelketa bat irekita egon daiteke oraindik karrerarik gabe; kasu horretan
     *     `taulakMarraztu()`-k goiz irteten du eta banner-a ez litzateke agertuko.
     *  2. `Porra_Irekita` zutabea sortu gabe badago (db/aurre-porrak.sql exekutatu arte),
     *     erroreak hemen bakarrik geratzen dira eta taulek funtzionatzen jarraitzen dute.
     */
    async function bannerMarraztu() {
        if (!cfg.id) return;
        // Bannerraren helburu bakarra "porra-bidali" tresnara bideratzea da: hura
        // ez-ikusgai badago (admin panela → Webgunea), bannerrak ez du zentzurik.
        if (!window.TXAPELKETAK.tresnaIkusgai('porra-bidali')) return;
        try {
            const rows = await window.dbLoader.query(
                'SELECT Porra_Irekita AS irekita FROM "Txapelketak" WHERE Txapelketa_ID = ?',
                [cfg.id]);
            if (!rows.length || Number(rows[0].irekita) !== 1) return;
        } catch (e) {
            return;   // zutabea oraindik ez dago: banner-ik ez, baina orria osorik
        }

        const el = document.getElementById('porra-banner');
        if (!el) return;
        el.innerHTML = `
            <div class="ohar-nabarmena" style="text-align:center;">
                <p style="margin:0 0 10px;"><strong>Porra irekita dago!</strong>
                   Zure txirrindulariak aukeratu eta porra aurretik bidal dezakezu.</p>
                <a href="/tresnak/porra-bidali/?txap=${cfg.id}" class="download-button ${kirola}">PORRA BIDALI</a>
                <p style="margin:10px 0 0; font-size:.9em;">
                   Gogoratu: porra ofizialki uzteko <strong>Anboto tabernara</strong> joan behar duzu.</p>
            </div>`;
    }

    async function taulakMarraztu() {
        if (!cfg.id) return oharra();
        try {
            const rows = await window.dbLoader.query(
                'SELECT (SELECT COUNT(*) FROM "Txapelketak" WHERE Txapelketa_ID = ?) AS bada, ' +
                '(SELECT COUNT(*) FROM "Karrerak" WHERE Txapelketa_ID = ?) AS karrerak',
                [cfg.id, cfg.id]);
            const r = rows && rows[0];
            if (!r || Number(r.bada) === 0 || Number(r.karrerak) === 0) return oharra();
        } catch (e) {
            return oharra();
        }

        const el = edukiontzia();
        if (!el) return;
        el.innerHTML = `
            <div class="tables-container stacked">
                ${taula('PORRA SAILKAPENA', 'porra-table')}
                ${taula('TXIRRINDULARIAK', 'txirrindulari-table')}
                <div class="table-wrapper" style="width: 100%; overflow-x: auto;">
                    <h3 class="${kirola}" style="text-align: center;">${meta.karrerakIzenburua}</h3>
                    <div id="karrerak-container"></div>
                </div>
            </div>`;

        window.dbLoader.loadPorra(cfg.id, 'porra-table');
        window.dbLoader.loadCyclists(cfg.id, 'txirrindulari-table');
        window.dbLoader.loadKarrerak(cfg.id, 'karrerak-container', kirola, C.irudiFn(cfg));
    }

    /**
     * Txapelketa DBtik kargatu eta `cfg` osatu:
     *  - AUTO-LOTURA: `cfg.id` konfiguratu gabe badago, DBan bilatzen da kirol-izena
     *    (`meta.izena`) + urtea bat eginez → urte berri batean ez da id-a eskuz jarri behar.
     *  - PDF GAINIDAZKETAK: `Txapelketak.Arauak_PDF/Dortsalak_PDF/Porrak_PDF` DBan jarrita
     *    badaude, `cfg`-koak gainidazten dituzte (admin → Baliabideak). NULL → config-ekoa.
     * `SELECT *` migrazio-segurua da (zutaberik ez → undefined → config-ek balio du).
     * Huts eginez gero, config estatikoa erabiltzen da (gunea ez da hausten).
     */
    async function txapelketaKargatu() {
        try {
            let row;
            if (cfg.id != null) {
                row = (await window.dbLoader.query('SELECT * FROM "Txapelketak" WHERE Txapelketa_ID = ?', [cfg.id]))[0];
            } else {
                row = (await window.dbLoader.query(
                    'SELECT * FROM "Txapelketak" WHERE Urtea = ? AND Izena LIKE ? ORDER BY Txapelketa_ID LIMIT 1',
                    [urtea, meta.izena + '%']))[0];
                if (row) cfg.id = Number(row.Txapelketa_ID);
            }
            if (row) {
                if (row.Arauak_PDF)    cfg.arauak    = row.Arauak_PDF;
                if (row.Dortsalak_PDF) cfg.dortsalak = row.Dortsalak_PDF;
                if (row.Porrak_PDF)    cfg.porrak    = row.Porrak_PDF;
                if (row.Web_Ofiziala)  cfg.webOfiziala = row.Web_Ofiziala;
            }
        } catch (e) { /* fallback: config estatikoa */ }
    }

    // Karpeta-mapa lehenik: PDF/irudi esteken karpetak hortik datoz. Fetch-ak huts eginez
    // gero, `txapelketak.js`-eko lehenetsiak erabiltzen dira (gunea ez da hausten).
    (async function hasi() {
        await C.karpetakKargatu();
        await txapelketaKargatu();   // id auto-lotura + PDF gainidazketak (DBtik), deskargak baino lehen
        deskargakMarraztu();
        profilaMarraztu();
        bannerMarraztu();
        taulakMarraztu();
    })();
})();
