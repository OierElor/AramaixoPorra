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

    // ── Deskarga-botoiak (dauden PDFak soilik) ───────────────────────────────
    const botoia = (mota, izena, testua) => {
        const href = C.pdf(mota, izena);
        return href ? `<a href="${href}" class="download-button ${kirola}" target="_blank">${testua}</a>` : '';
    };
    const pdfak = [
        botoia('porrak', cfg.porrak, 'PORRALARIEN LISTA (PDF)'),
        botoia('arauak', cfg.arauak, 'ARAUAK (PDF)'),
        botoia('dortsalak', cfg.dortsalak, 'DORTSALAK (PDF)'),
    ].filter(Boolean).join('\n');
    const deskargak = pdfak ? `<div class="download-section">${pdfak}</div>` : '';

    // ── Ibilbide osoaren irudia (itzuliak; klasikoek ez dute) ────────────────
    const profila = cfg.profilaIrudia
        ? `<div class="profile-container">
               <img src="${C.irudiBase}${cfg.profilaDir}/${cfg.profilaIrudia}"
                    alt="${esc(meta.izena)} ${urtea} Ibilbide Osoa"
                    onerror="this.style.display='none'">
           </div>`
        : '';

    document.body.insertAdjacentHTML('beforeend', `
        ${nav}
        <div class="container">
            <main>
                <section>
                    <h2 class="${kirola}">${esc(meta.izena)} ${urtea}</h2>
                    ${deskargak}
                    ${profila}
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

    taulakMarraztu();
})();
