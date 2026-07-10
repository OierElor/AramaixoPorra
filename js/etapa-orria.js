/**
 * Itzuli baten ETAPA-orria marrazten du (adib. /tour/2026/etapa3).
 * Dena bidetik eta `js/itzuliak.js` konfiguraziotik ateratzen da,
 * beraz etapa-orri guztiak fitxategi berdinak dira.
 *
 * `layout.js` baino LEHEN kargatu behar da (defer).
 */
(function () {
    'use strict';

    const C = window.ITZULIAK;
    if (!C) return;
    const loc = C.bidea();
    if (!loc || !loc.etapa) return;   // etapa-orria soilik

    const { kirola, urtea, etapa } = loc;
    const meta = C.kirolak[kirola];
    const cfg = C.urteak[kirola + '/' + urtea];
    if (!meta || !cfg) return;

    const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const azkena = cfg.etapak || etapa;

    document.title = `Aramaixo Porra - ${meta.izena} ${urtea} — ${etapa}. etapa`;

    // ── Nabigazioa: urtera itzuli + aurreko/hurrengo etapa ───────────────────
    const gezia = (n, ikurra, label) => (n >= 1 && n <= azkena)
        ? `<button onclick="window.location.href='etapa${n}.html'" aria-label="${label}" class="etapa-arrow ${kirola}">${ikurra}</button>`
        : `<button class="etapa-arrow ${kirola} disabled" disabled aria-label="${label}">${ikurra}</button>`;

    const nav = `
        <div class="etapa-navegazioa ${kirola}">
            <a href="/${kirola}/${urtea}/" class="etapa-back-btn ${kirola}">${urtea}</a>
            ${gezia(etapa - 1, '&#8592;', 'Aurrekoa')}
            <span class="etapa-info ${kirola}">Etapa ${etapa}</span>
            ${gezia(etapa + 1, '&#8594;', 'Hurrengoa')}
        </div>`;

    // ── Etaparen profil-irudia (.jpg → .png → ezkutatu) ──────────────────────
    let profila = '';
    if (cfg.profilaDir) {
        const oin = `${C.irudiBase}${cfg.profilaDir}/Etapa${etapa}`;
        profila = `
            <div class="profile-container">
                <img src="${oin}.jpg" alt="${etapa}. etapa profila"
                     onerror="this.onerror=null; this.src='${oin}.png'; this.onerror=function(){this.style.display='none'};">
            </div>`;
    }

    document.body.insertAdjacentHTML('beforeend', `
        ${nav}
        <main>
            <section>
                <h2 class="${kirola}">${esc(meta.izena)} ${urtea} — ${etapa}. etapa</h2>
                ${profila}
                <div id="etapa-emaitzak"></div>
            </section>
        </main>`);

    // ── Etapako emaitzak (txapelketa existitzen bada) ────────────────────────
    const el = document.getElementById('etapa-emaitzak');
    if (!el) return;

    if (!cfg.id) {
        el.innerHTML = `<p style="text-align:center; opacity:.6; margin-top:24px;">
            Ez dago daturik oraindik. Lasterketa amaitzean agertuko dira emaitzak.</p>`;
        return;
    }

    el.innerHTML = `
        <div class="table-wrapper" style="max-width:520px;margin:20px auto;">
            <h3 class="${kirola}" style="text-align:center;">ETAPAKO EMAITZAK</h3>
            <div class="table-content">
                <table class="sailkapena-table ${kirola}" id="emaitzak-table">
                    <thead><tr><th>Pos</th><th>Txirrindularia</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>`;

    // db-loader-ek berak erakusten du "ez dago daturik" mezua emaitzarik ezean.
    window.dbLoader.loadStageByNumber(cfg.id, etapa, 'emaitzak-table');
})();
