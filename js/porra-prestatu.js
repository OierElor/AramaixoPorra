/**
 * Porra prestatzeko laguntzailea (tresnak/porra-prestatu).
 *
 * Txapelketa IREKIAK bakarrik erakusten ditu (Txapelketak.Porra_Irekita = 1).
 * Erabiltzaileak bere LISTAK sortzen ditu (Etapa, Mendikoa, Generala...) eta
 * startlista horietan banatzen du; gero lista horietatik hainbat PORRA
 * ZIRRIBORRO osatu ditzake, alderatzeko. Amaitutakoan `tresnak/porra-bidali`-ra
 * bidaltzen du, dortsalak aurrez hautatuta (`?txap=ID&dortsalak=1,2,...`).
 *
 * Dena nabigatzailearen localStorage-n gordetzen da (gailu honetan bakarrik,
 * ez da datu-basera bidaltzen). Dortsalak bakarrik gordetzen dira — izenak
 * beti startlist-etik freskatzen dira, adminak txirrindulariak berrizenda
 * ditzakeelako.
 *
 * `Tresna.txapelketaIrekiak()` / `.startlista()` (js/tresna-komuna.js) berrerabiltzen ditu.
 */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const GAKOA = 'aramaixo.porra-prestatu.v1';

    const txapSel = $('txapelketa');
    const txapHautatzailea = $('txap-hautatzailea');
    const oharraEl = $('sarrera-oharra');
    const edukia = $('edukia');

    // Fitxa/tresnabarra edukiontziak iraunkorrak dira (haien innerHTML aldatzen da,
    // ez elementu bera); barrukoak ('listak-bilaketa', 'porra-bidali-btn'...) berriz
    // sortzen dira marraztuLista/PorraGorputza()-n, beraz beti `$()` freskoz atzitzen dira.
    const listakFitxak = $('listak-fitxak');
    const listaTresnabarra = $('lista-tresnabarra');
    const listakGorputza = $('listak-gorputza');

    const porrakFitxak = $('porrak-fitxak');
    const porraTresnabarra = $('porra-tresnabarra');
    const porraGorputza = $('porra-gorputza');
    const txapGarbituBtn = $('txap-garbitu-btn');

    let startlist = [];       // [{ dortsala, izena }] uneko txapelketarena
    let izenMapa = new Map(); // String(dortsala) -> izena
    let beharDira = 15;
    let tid = null;           // uneko Txapelketa_ID (string)
    let egoera = null;        // egoeraOsoa.txapelketak[tid] erreferentzia
    let tolestutakoTaldeak = new Set(); // lista-id-ak (bistaratze-egoera hutsa, ez da gordetzen)

    // «Porra bidali» tresna ikusgai dagoen (admin panela → Webgunea). Lehenetsia `true`
    // da (fail-open): fetch-a bukatu arte ez du inolako interakziorik blokeatzen.
    let porraBidaliIkusgai = true;
    (async function porraBidaliIkusgaiEgiaztatu() {
        try {
            const r = await fetch('/api/ezarpenak.php', { cache: 'no-cache' });
            const d = await r.json();
            const t = (d.tresnak || []).find(x => x.id === 'porra-bidali');
            if (t) porraBidaliIkusgai = !!t.ikusgai;
        } catch (e) { /* lehenetsiarekin jarraitu: ikusgai */ }
        // Fetch-a errenderizatu ondoren bukatu bada (kasurik ohikoena), egoera eguneratu.
        if (!porraBidaliIkusgai) marraztuPorraLaburpena();
    })();

    // ── localStorage: kargatu / gorde ────────────────────────────────────────
    function egoeraOsoaKargatu() {
        try {
            const gordeta = JSON.parse(localStorage.getItem(GAKOA));
            if (gordeta && gordeta.bertsioa === 1 && gordeta.txapelketak && typeof gordeta.txapelketak === 'object') {
                return gordeta;
            }
        } catch (e) { /* JSON hondatuta edo localStorage ez erabilgarri: hutsetik hasi */ }
        return { bertsioa: 1, txapelketak: {} };
    }

    let egoeraOsoa = egoeraOsoaKargatu();

    function gorde() {
        try { localStorage.setItem(GAKOA, JSON.stringify(egoeraOsoa)); }
        catch (e) { /* kuota beteta edo nabigatzaile pribatua: isilean jarraitu */ }
    }

    function id() {
        return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
    }

    /** Txapelketa honentzako egoera lortu, lehen aldia bada lehenetsiak sortuz. */
    function txapelketaEgoeraLortu(txapId) {
        if (!egoeraOsoa.txapelketak[txapId]) {
            const l1 = { id: id(), izena: 'Etapa', dortsalak: [] };
            const l2 = { id: id(), izena: 'Mendikoa', dortsalak: [] };
            const l3 = { id: id(), izena: 'Generala', dortsalak: [] };
            const p1 = { id: id(), izena: 'Porra 1', dortsalak: [] };
            egoeraOsoa.txapelketak[txapId] = {
                listak: [l1, l2, l3],
                porrak: [p1],
                unekoLista: l1.id,
                unekoPorra: p1.id,
            };
            gorde();
        }
        return egoeraOsoa.txapelketak[txapId];
    }

    function unekoLista() { return egoera.listak.find(l => l.id === egoera.unekoLista) || null; }
    function unekoPorra() { return egoera.porrak.find(p => p.id === egoera.unekoPorra) || null; }

    /** Dortsal-zerrenda batetik, uneko startlist-ean DAUDENAK bakarrik. */
    function baliozkoak(dortsalak) { return dortsalak.filter(d => izenMapa.has(String(d))); }
    /** Dortsal-zerrenda batetik, uneko startlist-ean EZ daudenak (adminak kendu ditu). */
    function ezezagunak(dortsalak) { return dortsalak.filter(d => !izenMapa.has(String(d))); }

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

        txapHautatzailea.hidden = false;
        txapSel.innerHTML = '<option value="">— Aukeratu txapelketa —</option>' +
            rows.map(r => `<option value="${r.id}" data-kop="${Number(r.kop) || 15}">${esc(r.izena)}</option>`).join('');

        const nahiDen = new URLSearchParams(location.search).get('txap');
        if (nahiDen && rows.some(r => String(r.id) === String(nahiDen))) {
            txapSel.value = nahiDen;
            await txapelketaAldatu();
        }
    }

    // ── Startlist-a kargatu hautatutako txapelketarako ──────────────────────
    async function txapelketaAldatu() {
        edukia.hidden = true;
        oharraEl.innerHTML = '';
        startlist = [];
        izenMapa = new Map();
        egoera = null;
        tid = txapSel.value;
        if (!tid) return;

        const eskatua = tid; // erabiltzaileak fetch-a martxan dela txapelketaz aldatzen badu, baztertu
        const aukera = txapSel.options[txapSel.selectedIndex];
        beharDira = Number(aukera.dataset.kop) || 15;

        oharraEl.innerHTML = '<p style="text-align:center; opacity:.6;">Kargatzen...</p>';
        try {
            startlist = await Tresna.startlista(tid);
        } catch (e) {
            if (txapSel.value !== eskatua) return;
            oharraEl.innerHTML = '<p style="color:#c00;">Errorea txirrindulariak kargatzean: ' + esc(e.message) + '</p>';
            return;
        }
        if (txapSel.value !== eskatua) return; // artean beste txapelketa bat hautatu da
        if (!startlist.length) {
            oharraEl.innerHTML = '<p style="text-align:center; opacity:.75;">Txapelketa honek ez du txirrindulari zerrendarik oraindik.</p>';
            return;
        }
        oharraEl.innerHTML = '';
        izenMapa = new Map(startlist.map(r => [String(r.dortsala), r.izena]));

        egoera = txapelketaEgoeraLortu(tid);
        tolestutakoTaldeak = new Set();

        marraztuListaFitxak();
        marraztuListaGorputza();
        marraztuPorraFitxak();
        marraztuPorraGorputza();
        edukia.hidden = false;
    }

    // ═══ A · NIRE LISTAK ═════════════════════════════════════════════════════

    function marraztuListaFitxak() {
        listakFitxak.innerHTML = egoera.listak.map(l =>
            `<button type="button" class="pp-fitxa${l.id === egoera.unekoLista ? ' active' : ''}" data-id="${esc(l.id)}">${esc(l.izena)}</button>`
        ).join('') + '<button type="button" class="pp-fitxa-berria" id="lista-berria-btn">+ Berria</button>';

        listaTresnabarra.hidden = !unekoLista();
    }

    function marraztuListaGorputza() {
        const lista = unekoLista();
        if (!lista) {
            listakGorputza.innerHTML = '<p style="text-align:center; opacity:.6;">Sortu lista bat gorago hasteko.</p>';
            return;
        }
        listakGorputza.innerHTML = `
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin:12px 0;">
                <input type="text" id="listak-bilaketa" autocomplete="off" placeholder="Iragazi izenez edo dortsalez..."
                    style="flex:1; min-width:200px; padding:9px 10px; border:1px solid #bbb; border-radius:6px; font-size:15px;">
                <span style="font-size:13px; opacity:.7;"><span id="listak-kontagailua">0</span> aukeratuta</span>
            </div>
            <div class="pp-zerrenda" id="listak-startlista"></div>`;
        marraztuListaZerrenda('');
        $('listak-bilaketa').addEventListener('input', e => marraztuListaZerrenda(e.target.value));
    }

    function marraztuListaZerrenda(iragazkia) {
        const lista = unekoLista();
        if (!lista) return;
        const cont = $('listak-startlista');
        const q = iragazkia.trim().toLowerCase();
        const hartuta = new Set(lista.dortsalak.map(String));

        const rows = startlist.filter(r => !q ||
            String(r.dortsala).includes(q) || r.izena.toLowerCase().includes(q));

        $('listak-kontagailua').textContent = lista.dortsalak.length;

        const besteListak = dortsala => egoera.listak
            .filter(l => l.id !== lista.id && l.dortsalak.some(d => String(d) === String(dortsala)))
            .map(l => `<span class="pp-badge">${esc(l.izena)}</span>`).join('');

        cont.innerHTML = rows.map(r => `
            <label class="pp-lerroa">
                <input type="checkbox" data-dortsala="${esc(r.dortsala)}" ${hartuta.has(String(r.dortsala)) ? 'checked' : ''}>
                <span class="pp-dortsala">${esc(r.dortsala)}</span>
                <span class="pp-izena">${esc(r.izena)}</span>
                <span class="pp-beste-listak">${besteListak(r.dortsala)}</span>
            </label>`).join('') || '<p style="padding:12px; opacity:.6;">Ez da inor aurkitu.</p>';

        // Startlistan dagoeneko ez dauden dortsalak (gordeta zeuden, adminak kendu ditu).
        const galduak = ezezagunak(lista.dortsalak);
        if (galduak.length) {
            cont.innerHTML += galduak.map(d => `
                <div class="pp-lerroa pp-ez-dago">
                    <span class="pp-dortsala">${esc(d)}</span>
                    <span class="pp-izena"></span>
                    <button type="button" class="pp-btn-txiki" data-kendu-galdua="${esc(d)}">Kendu</button>
                </div>`).join('');
        }
    }

    listakFitxak.addEventListener('click', e => {
        const berria = e.target.closest('#lista-berria-btn');
        if (berria) {
            const izena = (prompt('Lista berriaren izena:', 'Lista berria') || '').trim();
            if (!izena) return;
            const berri = { id: id(), izena, dortsalak: [] };
            egoera.listak.push(berri);
            egoera.unekoLista = berri.id;
            gorde();
            marraztuListaFitxak();
            marraztuListaGorputza();
            return;
        }
        const btn = e.target.closest('.pp-fitxa');
        if (btn) {
            egoera.unekoLista = btn.dataset.id;
            gorde();
            marraztuListaFitxak();
            marraztuListaGorputza();
        }
    });

    $('lista-berrizendatu-btn').addEventListener('click', () => {
        const lista = unekoLista();
        if (!lista) return;
        const berria = (prompt('Listaren izen berria:', lista.izena) || '').trim();
        if (!berria || berria === lista.izena) return;
        lista.izena = berria;
        gorde();
        marraztuListaFitxak();
        marraztuListaGorputza();
        marraztuPorraTaldeak(); // talde-izenburuak eguneratu
    });

    $('lista-ezabatu-btn').addEventListener('click', () => {
        const lista = unekoLista();
        if (!lista) return;
        if (!confirm(`"${lista.izena}" lista ezabatu? Zure porretan hautatutakoak EZ dira galduko.`)) return;
        egoera.listak = egoera.listak.filter(l => l.id !== lista.id);
        egoera.unekoLista = egoera.listak.length ? egoera.listak[0].id : null;
        gorde();
        marraztuListaFitxak();
        marraztuListaGorputza();
        marraztuPorraTaldeak();
    });

    listakGorputza.addEventListener('change', e => {
        const chk = e.target.closest('input[type="checkbox"][data-dortsala]');
        if (!chk) return;
        const lista = unekoLista();
        if (!lista) return;
        const d = Number(chk.dataset.dortsala);
        if (chk.checked) {
            if (!lista.dortsalak.some(x => Number(x) === d)) lista.dortsalak.push(d);
        } else {
            lista.dortsalak = lista.dortsalak.filter(x => Number(x) !== d);
        }
        gorde();
        marraztuListaZerrenda($('listak-bilaketa')?.value || '');
        marraztuPorraTaldeak(); // beste-lista bereizgarriak eta taldeak eguneratu
    });

    listakGorputza.addEventListener('click', e => {
        const btn = e.target.closest('[data-kendu-galdua]');
        if (!btn) return;
        const lista = unekoLista();
        if (!lista) return;
        const d = btn.dataset.kenduGaldua;
        lista.dortsalak = lista.dortsalak.filter(x => String(x) !== String(d));
        gorde();
        marraztuListaZerrenda($('listak-bilaketa')?.value || '');
    });

    // ═══ B · NIRE PORRAK ═════════════════════════════════════════════════════

    function marraztuPorraFitxak() {
        porrakFitxak.innerHTML = egoera.porrak.map(p =>
            `<button type="button" class="pp-fitxa${p.id === egoera.unekoPorra ? ' active' : ''}" data-id="${esc(p.id)}">${esc(p.izena)}</button>`
        ).join('') + '<button type="button" class="pp-fitxa-berria" id="porra-berria-btn">+ Berria</button>';

        porraTresnabarra.hidden = !unekoPorra();
    }

    function marraztuPorraGorputza() {
        const porra = unekoPorra();
        if (!porra) {
            porraGorputza.innerHTML = '<p style="text-align:center; opacity:.6;">Sortu porra bat gorago hasteko.</p>';
            return;
        }
        porraGorputza.innerHTML = `
            <div style="margin:12px 0;">
                <span class="porra-kontagailua" id="porra-kontagailua-wrap">
                    <span id="porra-kontagailua">0</span>/<span id="porra-guztira">${beharDira}</span>
                </span>
                <span style="font-size:13px; opacity:.7; margin-left:8px;">hautatuta</span>
            </div>
            <div id="porra-hautatuak" class="porra-hautatuak"></div>
            <div id="porra-taldeak" style="margin-top:16px;"></div>
            <div style="margin-top:16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <button id="porra-bidali-btn" disabled
                    style="padding:10px 20px; border:none; border-radius:6px; background:#43a047; color:#fff; font-size:15px; font-weight:600; cursor:pointer;">
                    Porra hau bidali →
                </button>
                <span id="porra-bidali-ezkutatuta" style="font-size:13px; opacity:.7;" hidden>«Porra bidali» tresna aldi baterako ez dago eskuragarri.</span>
            </div>`;
        marraztuPorraTaldeak();
        marraztuPorraLaburpena();
        $('porra-bidali-btn').addEventListener('click', porraBidali);
    }

    function marraztuPorraTaldeak() {
        const cont = $('porra-taldeak');
        if (!cont) return;
        const porra = unekoPorra();
        if (!porra) return;
        const hartuta = new Set(porra.dortsalak.map(String));
        const inLista = new Set(); // dortsal bat gutxienez lista batean sartuta

        const taldeak = egoera.listak.map(lista => {
            const rows = startlist.filter(r => lista.dortsalak.some(d => String(d) === String(r.dortsala)));
            rows.forEach(r => inLista.add(String(r.dortsala)));
            return { id: lista.id, izena: lista.izena, rows };
        });

        const gainerakoak = startlist.filter(r => !inLista.has(String(r.dortsala)));
        taldeak.push({ id: '__gainerakoak__', izena: 'Listarik gabe', rows: gainerakoak });

        cont.innerHTML = taldeak.map(t => {
            const tolestuta = tolestutakoTaldeak.has(t.id);
            const zenbat = t.rows.filter(r => hartuta.has(String(r.dortsala))).length;
            return `
            <div class="pp-taldea">
                <div class="pp-taldea-burua" data-talde-id="${esc(t.id)}">
                    <span>${esc(t.izena)} (${zenbat}/${t.rows.length})</span>
                    <span>${tolestuta ? '▸' : '▾'}</span>
                </div>
                <div class="pp-taldea-gorputza${tolestuta ? ' collapsed' : ''}">
                    ${t.rows.map(r => `
                        <label class="pp-lerroa">
                            <input type="checkbox" data-dortsala="${esc(r.dortsala)}" ${hartuta.has(String(r.dortsala)) ? 'checked' : ''}>
                            <span class="pp-dortsala">${esc(r.dortsala)}</span>
                            <span class="pp-izena">${esc(r.izena)}</span>
                        </label>`).join('') || '<p style="padding:10px 12px; opacity:.6; margin:0;">Hutsik.</p>'}
                </div>
            </div>`;
        }).join('');
    }

    function marraztuPorraLaburpena() {
        const porra = unekoPorra();
        const hautatuakEl = $('porra-hautatuak');
        if (!porra || !hautatuakEl) return;

        hautatuakEl.innerHTML = porra.dortsalak.map(d => {
            const ezaguna = izenMapa.has(String(d));
            const izena = ezaguna ? izenMapa.get(String(d)) : 'Ezezaguna — startlistan ez dago';
            return `<span class="grafiko-chip${ezaguna ? '' : ' pp-ez-dago'}" data-d="${esc(d)}" title="Kendu">` +
                `<span class="chip-zbk">${esc(d)}</span>${esc(izena)}` +
                `<span class="chip-x">×</span></span>`;
        }).join('');

        const balid = baliozkoak(porra.dortsalak).length;
        const osatuta = balid === beharDira;
        $('porra-kontagailua').textContent = balid;
        $('porra-kontagailua-wrap').classList.toggle('osatuta', osatuta);
        $('porra-bidali-btn').disabled = !osatuta || !porraBidaliIkusgai;
        const ohEl = $('porra-bidali-ezkutatuta');
        if (ohEl) ohEl.hidden = porraBidaliIkusgai;
    }

    function gehituPorra(dortsala) {
        const porra = unekoPorra();
        if (!porra) return;
        if (baliozkoak(porra.dortsalak).length >= beharDira) return;
        if (porra.dortsalak.some(d => String(d) === String(dortsala))) return;
        porra.dortsalak.push(Number(dortsala));
        gorde();
        marraztuPorraTaldeak();
        marraztuPorraLaburpena();
    }

    function kenduPorra(dortsala) {
        const porra = unekoPorra();
        if (!porra) return;
        porra.dortsalak = porra.dortsalak.filter(d => String(d) !== String(dortsala));
        gorde();
        marraztuPorraTaldeak();
        marraztuPorraLaburpena();
    }

    porrakFitxak.addEventListener('click', e => {
        const berria = e.target.closest('#porra-berria-btn');
        if (berria) {
            const izena = (prompt('Porra berriaren izena:', `Porra ${egoera.porrak.length + 1}`) || '').trim();
            if (!izena) return;
            const berri = { id: id(), izena, dortsalak: [] };
            egoera.porrak.push(berri);
            egoera.unekoPorra = berri.id;
            gorde();
            marraztuPorraFitxak();
            marraztuPorraGorputza();
            return;
        }
        const btn = e.target.closest('.pp-fitxa');
        if (btn) {
            egoera.unekoPorra = btn.dataset.id;
            gorde();
            marraztuPorraFitxak();
            marraztuPorraGorputza();
        }
    });

    $('porra-berrizendatu-btn').addEventListener('click', () => {
        const porra = unekoPorra();
        if (!porra) return;
        const berria = (prompt('Porraren izen berria:', porra.izena) || '').trim();
        if (!berria || berria === porra.izena) return;
        porra.izena = berria;
        gorde();
        marraztuPorraFitxak();
    });

    $('porra-bikoiztu-btn').addEventListener('click', () => {
        const porra = unekoPorra();
        if (!porra) return;
        const berri = { id: id(), izena: porra.izena + ' (kopia)', dortsalak: porra.dortsalak.slice() };
        egoera.porrak.push(berri);
        egoera.unekoPorra = berri.id;
        gorde();
        marraztuPorraFitxak();
        marraztuPorraGorputza();
    });

    $('porra-ezabatu-btn').addEventListener('click', () => {
        const porra = unekoPorra();
        if (!porra) return;
        if (!confirm(`"${porra.izena}" porra ezabatu?`)) return;
        egoera.porrak = egoera.porrak.filter(p => p.id !== porra.id);
        egoera.unekoPorra = egoera.porrak.length ? egoera.porrak[0].id : null;
        gorde();
        marraztuPorraFitxak();
        marraztuPorraGorputza();
    });

    // Taldeen tolestea + kaxatxoak, delegazioz (gorputza dinamikoki berridazten baita).
    edukia.addEventListener('click', e => {
        const burua = e.target.closest('.pp-taldea-burua');
        if (burua) {
            const tid2 = burua.dataset.taldeId;
            if (tolestutakoTaldeak.has(tid2)) tolestutakoTaldeak.delete(tid2);
            else tolestutakoTaldeak.add(tid2);
            burua.nextElementSibling.classList.toggle('collapsed');
            burua.querySelector('span:last-child').textContent = tolestutakoTaldeak.has(tid2) ? '▸' : '▾';
            return;
        }
        const chip = e.target.closest('.porra-hautatuak .grafiko-chip');
        if (chip) { kenduPorra(chip.dataset.d); return; }
    });

    edukia.addEventListener('change', e => {
        const chk = e.target.closest('#porra-taldeak input[type="checkbox"][data-dortsala]');
        if (!chk) return;
        // gehituPorra/kenduPorra-k berrezarritzen dute taldeen marrazketa datu errealetik,
        // beraz mugara iritsitakoan (gehituPorra baztertua) kaxatxoa automatikoki
        // desmarkatuta geratzen da berriz ere.
        if (chk.checked) gehituPorra(chk.dataset.dortsala);
        else kenduPorra(chk.dataset.dortsala);
    });

    function porraBidali() {
        const porra = unekoPorra();
        if (!porra) return;
        const dortsalak = baliozkoak(porra.dortsalak);
        if (dortsalak.length !== beharDira) return;
        location.href = `/tresnak/porra-bidali/?txap=${encodeURIComponent(tid)}&dortsalak=${dortsalak.join(',')}`;
    }

    txapGarbituBtn.addEventListener('click', () => {
        if (!tid) return;
        const izena = txapSel.options[txapSel.selectedIndex]?.textContent || 'txapelketa hau';
        if (!confirm(`Ziur zaude? "${izena}"-ren zure lista eta porra guztiak betiko ezabatuko dira gailu honetatik.`)) return;
        delete egoeraOsoa.txapelketak[tid];
        gorde();
        egoera = txapelketaEgoeraLortu(tid);
        tolestutakoTaldeak = new Set();
        marraztuListaFitxak();
        marraztuListaGorputza();
        marraztuPorraFitxak();
        marraztuPorraGorputza();
    });

    // ── Abiarazi ───────────────────────────────────────────────────────────
    txapSel.addEventListener('change', txapelketaAldatu);
    txapelketakKargatu();
})();
