/**
 * Aurre-porra bidaltzeko formularioa (tresnak/porra-bidali).
 *
 * Txapelketa IREKIAK bakarrik erakusten ditu (Txapelketak.Porra_Irekita = 1) eta
 * txirrindulariak txapelketaren startlist-etik hautatzen dira. Bidalketa
 * `api/porra.php`-ra doa: EZ da datu-basean gordetzen.
 *
 * `Tresna.autocomplete()`/`.txapelketaIrekiak()`/`.startlista()` (js/tresna-komuna.js)
 * berrerabiltzen ditu. `?txap=ID[&dortsalak=1,2,...]` onartzen du esteka zuzenetarako
 * (adib. tresnak/porra-prestatu-tik).
 */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const txapSel   = $('txapelketa');
    const bilaketa  = $('bilaketa');
    const hautatuak = $('hautatuak');
    const kontagailua = $('kontagailua');
    const guztira   = $('guztira');
    const bidaliBtn = $('bidali-btn');
    const emaitza   = $('emaitza');
    const formaZatia = $('forma');
    const oharraEl  = $('sarrera-oharra');

    let startlist = [];      // [{ dortsala, izena }]
    let aukeratuak = [];     // [{ dortsala, izena }] — hautatze-ordenan
    let beharDira = 15;

    // ── Txapelketa irekiak kargatu ──────────────────────────────────────────
    async function txapelketakKargatu() {
        let rows;
        try {
            rows = await Tresna.txapelketaIrekiak();
        } catch (e) {
            oharraEl.innerHTML = '<p style="color:#c00;">Ezin izan dira txapelketak kargatu: ' +
                esc(e.message) + '</p>';
            return;
        }

        if (!rows.length) {
            oharraEl.innerHTML = '<p style="text-align:center; opacity:.75;">' +
                'Une honetan <strong>ez dago porra irekirik</strong>. Txapelketa bat irekitzen denean, hemen agertuko da.</p>';
            return;
        }

        formaZatia.hidden = false;
        txapSel.innerHTML = '<option value="">— Aukeratu txapelketa —</option>' +
            rows.map(r => `<option value="${r.id}" data-kop="${Number(r.kop) || 15}">${esc(r.izena)}</option>`).join('');

        // Esteka zuzena: /tresnak/porra-bidali/?txap=17[&dortsalak=101,111,...]
        const params = new URLSearchParams(location.search);
        const nahiDen = params.get('txap');
        if (nahiDen && rows.some(r => String(r.id) === String(nahiDen))) {
            txapSel.value = nahiDen;
            await txapelketaAldatu();
            aurreHautatu(params.get('dortsalak'));
        }
    }

    // ── Aurrez hautatutako dortsalak gehitu (adib. tresnak/porra-prestatu-tik) ──
    function aurreHautatu(zerrenda) {
        if (!zerrenda) return;
        const nahi = new Set(zerrenda.split(',').map(s => s.trim()).filter(Boolean));
        for (const r of startlist) {
            if (nahi.has(String(r.dortsala))) gehitu({ id: r.dortsala, izena: r.izena });
        }
    }

    // ── Startlist-a kargatu hautatutako txapelketarako ──────────────────────
    async function txapelketaAldatu() {
        aukeratuak = [];
        startlist = [];
        bilaketa.value = '';
        marraztu();

        const id = txapSel.value;
        if (!id) { bilaketa.disabled = true; return; }

        const aukera = txapSel.options[txapSel.selectedIndex];
        beharDira = Number(aukera.dataset.kop) || 15;
        guztira.textContent = beharDira;

        bilaketa.disabled = true;
        bilaketa.placeholder = 'Txirrindulariak kargatzen...';
        try {
            startlist = await Tresna.startlista(id);
        } catch (e) {
            bilaketa.placeholder = 'Errorea txirrindulariak kargatzen';
            return;
        }

        if (!startlist.length) {
            bilaketa.placeholder = 'Txapelketa honek ez du txirrindulari zerrendarik oraindik';
            return;
        }
        bilaketa.disabled = false;
        bilaketa.placeholder = 'Idatzi izena edo dortsala...';
        marraztu();
    }

    // ── Autocomplete: dagoeneko hautatuta daudenak ez erakutsi ──────────────
    function aukerak() {
        const hartuta = new Set(aukeratuak.map(a => String(a.dortsala)));
        return startlist
            .filter(r => !hartuta.has(String(r.dortsala)))
            .map(r => ({ id: r.dortsala, label: `${r.dortsala} · ${r.izena}`, izena: r.izena }));
    }

    function gehitu(it) {
        if (aukeratuak.length >= beharDira) return;
        if (aukeratuak.some(a => String(a.dortsala) === String(it.id))) return;
        aukeratuak.push({ dortsala: it.id, izena: it.izena });
        bilaketa.value = '';
        marraztu();
    }

    function kendu(dortsala) {
        aukeratuak = aukeratuak.filter(a => String(a.dortsala) !== String(dortsala));
        marraztu();
    }

    function marraztu() {
        hautatuak.innerHTML = aukeratuak.map(a =>
            `<span class="grafiko-chip" data-d="${esc(a.dortsala)}" title="Kendu">` +
            `<span class="chip-zbk">${esc(a.dortsala)}</span>${esc(a.izena)}` +
            `<span class="chip-x">×</span></span>`).join('');

        kontagailua.textContent = aukeratuak.length;
        const osatuta = aukeratuak.length === beharDira;
        kontagailua.classList.toggle('osatuta', osatuta);
        bilaketa.disabled = !startlist.length || osatuta;
        bidaliBtn.disabled = !osatuta;
    }

    hautatuak.addEventListener('click', e => {
        const chip = e.target.closest('.grafiko-chip');
        if (chip) kendu(chip.dataset.d);
    });

    txapSel.addEventListener('change', txapelketaAldatu);

    // ── Bidali ─────────────────────────────────────────────────────────────
    bidaliBtn.addEventListener('click', async () => {
        const ezizena = $('ezizena').value.trim();
        if (!ezizena) {
            emaitza.style.color = '#e53935';
            emaitza.textContent = '⚠ Idatzi zure ezizena.';
            return;
        }
        bidaliBtn.disabled = true;
        emaitza.style.color = 'inherit';
        emaitza.textContent = 'Bidaltzen...';
        try {
            const res = await fetch('/api/porra.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    txapelketa_id: Number(txapSel.value),
                    ezizena,
                    harremana: $('harremana').value.trim(),
                    oharrak: $('oharrak').value.trim(),
                    dortsalak: aukeratuak.map(a => Number(a.dortsala)),
                    hp: $('hp').value,
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) throw new Error(data.error || 'Errorea');

            formaZatia.hidden = true;
            oharraEl.innerHTML =
                '<div class="ohar-nabarmena" style="text-align:center;">' +
                '<p style="margin:0 0 8px;"><strong>✅ Jasota! Eskerrik asko.</strong></p>' +
                `<p style="margin:0;">${esc(data.abisua || '')}</p></div>`;
            oharraEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (e) {
            emaitza.style.color = '#e53935';
            emaitza.textContent = '⚠ ' + e.message;
            bidaliBtn.disabled = false;
        }
    });

    // ── Abiarazi ───────────────────────────────────────────────────────────
    Tresna.autocomplete(bilaketa, aukerak, gehitu);
    marraztu();
    txapelketakKargatu();
})();
