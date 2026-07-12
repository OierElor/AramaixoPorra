/**
 * Tresna komuna — Tresnak ataleko orriek partekatzen duten logika
 * (porra-fitxa, konparatzailea...). window.dbLoader behar du.
 */
const Tresna = {
    COLORS: [
        '#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4', '#42d4f4',
        '#f032e6', '#bfef45', '#fabed4', '#469990', '#9a6324', '#808000',
    ],

    async q(sql, params = []) {
        return window.dbLoader.query(sql, params);
    },

    /** Txapelketak: lasterketa motaz taldekatuta (alfabetikoki), gero urte beheraka. */
    async txapelketak() {
        const rows = await this.q(
            "SELECT Txapelketa_ID AS id, Izena AS izena, Urtea AS urtea FROM Txapelketak");
        rows.sort((a, b) => {
            const baseA = a.izena.replace(/\s+\d{4}$/, '');
            const baseB = b.izena.replace(/\s+\d{4}$/, '');
            if (baseA !== baseB) return baseA.localeCompare(baseB, 'eu');
            return b.urtea - a.urtea;
        });
        return rows;
    },

    /**
     * Txapelketako benetako karrerak ordenan (agregatu 'Azken Karrera' kanpo).
     * Kategoria beteta dutenak ETA emaitzak dituzten guztiak: karrera batek emaitzak
     * baditu, bere puntuak zenbatu behar dira, Kategoria falta bazaio ere.
     * `js/db-loader.js`-eko akordeoiaren iragazki bera.
     */
    async karrerak(tid) {
        return this.q("SELECT k.Karrerak_ID AS kid, k.Izena AS izena FROM Karrerak k " +
            "WHERE k.Txapelketa_ID = ? " +
            "  AND ( (k.Kategoria IS NOT NULL AND k.Kategoria <> '') " +
            "        OR EXISTS (SELECT 1 FROM KarreraSailkapena ks " +
            "                   WHERE ks.Karrera_ID = k.Karrerak_ID) ) " +
            "ORDER BY (k.Ordena IS NULL), k.Ordena, k.Karrerak_ID", [tid]);
    },

    /** "{Txapelketa} - {N}. etapa ({Helmuga})" → "{N}. etapa ({Helmuga})".
     *  Aurrizkia etapa-zenbaki batek jarraitzen dionean BAKARRIK kentzen da:
     *  klasikoen izenek ' - ' izan dezakete ("Milano - Torino") eta oso-osorik
     *  utzi behar dira. Helmugak berak ere ' - ' izan dezake. */
    karreraLabel(izena) {
        const s = String(izena);
        const m = s.match(/^.+?\s-\s(\d+\.\s*etapa.*)$/i);
        return m ? m[1] : s;
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
            // Kategoria-iragazkirik EZ: KarreraSailkapena-rekin JOIN egiten denez, emaitzak
            // izatea inplizitua da. Iragazkiak Kategoriarik gabeko etapa baten puntuak
            // baztertuko lituzke (admin-eko sailkapenak BAI zenbatzen dituenak).
            "  WHERE pa.Ezizen_ID = ez.Ezizen_ID AND k.Txapelketa_ID = ez.Txapelketa_ID) AS etapaTot, " +
            " e.Posizioa AS ofPos, e.Puntuak AS ofPts " +
            "FROM PorraEzizenak ez " +
            "LEFT JOIN TxapelketaEmaitzaPorralariak e " +
            "  ON e.Ezizen_ID = ez.Ezizen_ID AND e.Txapelketa_ID = ez.Txapelketa_ID " +
            "WHERE ez.Txapelketa_ID = ?", [tid]);
        const hasOfficial = rows.some(r => r.ofPos != null);
        rows.forEach(r => { r.etapaTot = Number(r.etapaTot); r.ofPts = r.ofPts != null ? Number(r.ofPts) : null; });
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
            "    AND k.Txapelketa_ID = pa.Txapelketa_ID) AS pts " +   // Kategoria-iragazkirik ez (ikus porrak())
            "FROM PorraApustuak pa " +
            "JOIN Txirrindulariak t ON t.Txirrindularia_ID = pa.Txirrindularia_ID " +
            "LEFT JOIN TxirrindulariakTxapleketanParteHartzea h " +
            "  ON h.TxapelketaID = pa.Txapelketa_ID AND h.TxirrindulariaID = pa.Txirrindularia_ID " +
            "WHERE pa.Txapelketa_ID = ? AND pa.Ezizen_ID = ? " +
            "ORDER BY pts DESC, t.Izena", [tid, ezId]);
    },

    // ─── EBOLUZIO-GERUZA BATERATUA ──────────────────────────────────────────
    // Grafiko-tresna guztien (grafikoak, porra-fitxa, konparatzailea) datu-iturri
    // BAKARRA. Lehen hiruretan bikoiztuta zegoen.

    /**
     * Karrerak **EMAITZEKIN SOILIK**, ordenan. Emaitzarik gabekoak ez dira grafikoan
     * agertzen: bestela lerro laua marrazten zuten (adib. Tour 2026: 21 karrera, 8 emaitzekin).
     */
    async karrerakEmaitzekin(tid) {
        return this.q(
            "SELECT k.Karrerak_ID AS kid, k.Izena AS izena FROM Karrerak k " +
            "WHERE k.Txapelketa_ID = ? " +
            "  AND EXISTS (SELECT 1 FROM KarreraSailkapena ks " +
            "              WHERE ks.Karrera_ID = k.Karrerak_ID) " +
            "ORDER BY (k.Ordena IS NULL), k.Ordena, k.Karrerak_ID", [tid]);
    },

    /**
     * Eboluzio-datuak txapelketa batean: puntu metatuak, postua eta erreferentziarekiko
     * desberdintasuna karreraz karrera.
     *
     * **AMAIERA-PUNTUA** (itzuli handietan bakarrik): azken karreraren ondoren puntu bat
     * gehitzen da **sailkapen ofizialeko puntuekin**, zeinak sailkapen orokorreko eta
     * mendiko puntuak **jada barne baititu**. `bonusa = ofiziala − etapetan metatua`.
     *   - `Puntuak_Generala`/`Puntuak_Mendikoa` zutabeak EZ dira erabiltzen: ia hutsik daude.
     *   - Klasikoetan EZ (han ofiziala ez da "etapak + bonusa").
     *   - Bonusik ez badago (adib. emaitza ofizialak bonusik gabe sartu badira), EZ da
     *     erakusten: puntu lau erredundante bat besterik ez litzateke.
     *
     * @param {number} tid  Txapelketa_ID
     * @param {'txirrindulari'|'porralari'} mota
     * @returns {{karrerak, labelak, ents, N_ref, refLabel, badaAmaiera, N}}
     *          `ents[i] = { id, izena, pts[], cum[], pos[], diff[], metatua, total, bonusa }`
     */
    async eboluzioaKargatu(tid, mota) {
        const txapRows = await this.q(
            "SELECT Izena AS izena FROM Txapelketak WHERE Txapelketa_ID = ?", [tid]);
        const txapIzena = txapRows.length ? String(txapRows[0].izena) : '';
        const itzuliHandia = !/klasik/i.test(txapIzena);

        const karrerak = await this.karrerakEmaitzekin(tid);
        const N = karrerak.length;
        const kidx = new Map(karrerak.map((k, j) => [k.kid, j]));

        let rows, ofRows, N_ref, refLabel;
        if (mota === 'txirrindulari') {
            rows = await this.q(
                "SELECT ks.Txirrindularia_ID AS id, t.Izena AS izena, ks.Karrera_ID AS kid, ks.Puntuak AS pts " +
                "FROM KarreraSailkapena ks " +
                "JOIN Karrerak k ON k.Karrerak_ID = ks.Karrera_ID " +
                "JOIN Txirrindulariak t ON t.Txirrindularia_ID = ks.Txirrindularia_ID " +
                "WHERE k.Txapelketa_ID = ?", [tid]);
            ofRows = await this.q(
                "SELECT Txirrindularia_ID AS id, Puntuak AS ofiziala " +
                "FROM TxapelketaEmaitzaTxirrindulariak WHERE Txapelketa_ID = ?", [tid]);
            N_ref = itzuliHandia ? 15 : 25;
            refLabel = `${N_ref}. txirrindularia (talde ideala)`;
        } else {
            rows = await this.q(
                "SELECT pa.Ezizen_ID AS id, ez.Ezizena AS izena, k.Karrerak_ID AS kid, SUM(ks.Puntuak) AS pts " +
                "FROM PorraApustuak pa " +
                "JOIN KarreraSailkapena ks ON ks.Txirrindularia_ID = pa.Txirrindularia_ID " +
                "JOIN Karrerak k ON k.Karrerak_ID = ks.Karrera_ID AND k.Txapelketa_ID = pa.Txapelketa_ID " +
                "JOIN PorraEzizenak ez ON ez.Ezizen_ID = pa.Ezizen_ID " +
                "WHERE pa.Txapelketa_ID = ? " +
                "GROUP BY pa.Ezizen_ID, ez.Ezizena, k.Karrerak_ID", [tid]);
            ofRows = await this.q(
                "SELECT Ezizen_ID AS id, Puntuak AS ofiziala " +
                "FROM TxapelketaEmaitzaPorralariak WHERE Txapelketa_ID = ?", [tid]);
            N_ref = 5;
            refLabel = `${N_ref}. porra (erreferentzia)`;
        }

        const ofMap = new Map();
        ofRows.forEach(r => { if (r.ofiziala != null) ofMap.set(r.id, Number(r.ofiziala)); });

        // Entitateak eraiki: puntuak karrera-ordenan
        const map = new Map();
        rows.forEach(r => {
            if (!map.has(r.id)) map.set(r.id, { id: r.id, izena: r.izena, pts: new Array(N).fill(0) });
            const j = kidx.get(r.kid);
            if (j != null) map.get(r.id).pts[j] = Number(r.pts) || 0;
        });
        const ents = [...map.values()];

        // Metatua + amaiera-puntua
        ents.forEach(e => {
            const cum = [];
            let s = 0;
            e.pts.forEach(p => { s += p; cum.push(s); });
            e.metatua = s;
            e.ofiziala = ofMap.has(e.id) ? ofMap.get(e.id) : null;
            e.bonusa = e.ofiziala != null ? e.ofiziala - s : 0;
            e.cum = cum;
        });

        // Amaiera-puntua: itzuli handia + emaitza ofizialak + norbaitek bonusa
        const badaAmaiera = itzuliHandia && ofMap.size > 0 && ents.some(e => e.bonusa !== 0);
        if (badaAmaiera) {
            ents.forEach(e => e.cum.push(e.ofiziala != null ? e.ofiziala : e.metatua));
        } else {
            ents.forEach(e => { e.bonusa = 0; });
        }
        ents.forEach(e => { e.total = e.cum.length ? e.cum[e.cum.length - 1] : 0; });

        // Postua eta desberdintasuna puntu bakoitzean (amaiera barne)
        const P = N + (badaAmaiera ? 1 : 0);
        for (let j = 0; j < P; j++) {
            const order = [...ents].sort((a, b) => b.cum[j] - a.cum[j]);
            order.forEach((e, rank) => { (e.pos = e.pos || new Array(P).fill(0))[j] = rank + 1; });
            const ref = order.length >= N_ref ? order[N_ref - 1].cum[j] : 0;
            ents.forEach(e => { (e.diff = e.diff || new Array(P).fill(0))[j] = e.cum[j] - ref; });
        }

        ents.sort((a, b) => b.total - a.total);

        const labelak = karrerak.map((_, i) => String(i + 1));
        if (badaAmaiera) labelak.push('Amaiera');

        return { karrerak, labelak, ents, N_ref, refLabel, badaAmaiera, N };
    },

    /**
     * Custom autocomplete dropdown (datalist-aren ordez, mobilean ere ongi funtzionatzen du).
     * @param {HTMLInputElement} input
     * @param {Array|Function} getItems  [{id, label}] array edo array itzultzen duen funtzioa
     * @param {Function} onSelect  onSelect({id, label}) deitua aukera hautatzean
     */
    autocomplete(input, getItems, onSelect) {
        const items = () => typeof getItems === 'function' ? getItems() : getItems;

        const wrap = document.createElement('div');
        wrap.className = 'ac-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const drop = document.createElement('div');
        drop.className = 'ac-drop';
        drop.style.display = 'none';
        wrap.appendChild(drop);

        let visible = [];

        function populate(query) {
            const all = items();
            const q = (query || '').toLowerCase().trim();
            visible = (q ? all.filter(it => it.label.toLowerCase().includes(q)) : all.slice())
                .sort((a, b) => a.label.localeCompare(b.label, 'eu'));
            if (!visible.length) { drop.style.display = 'none'; return; }
            drop.innerHTML = visible.map((it, i) =>
                `<div class="ac-item" data-i="${i}">${it.label}</div>`).join('');
            drop.style.display = '';
        }

        function hide() { drop.style.display = 'none'; }

        function pick(i) {
            const it = visible[i];
            if (!it) return;
            input.value = it.label;
            hide();
            onSelect(it);
        }

        input.addEventListener('focus', () => populate(input.value));
        input.addEventListener('input', () => populate(input.value));
        // mousedown prevents blur (mahai-gainekoak); touchend handles touch (mugikorrak)
        drop.addEventListener('mousedown', e => e.preventDefault());
        drop.addEventListener('click', e => {
            const el = e.target.closest('.ac-item');
            if (el) pick(Number(el.dataset.i));
        });
        drop.addEventListener('touchend', e => {
            const el = e.target.closest('.ac-item');
            if (el) { e.preventDefault(); pick(Number(el.dataset.i)); }
        });
        input.addEventListener('blur', () => setTimeout(hide, 150));
    },

    // ─── GRAFIKO-ERRENDATZAILE PARTEKATUA ───────────────────────────────────

    /**
     * Chart.js lerro-grafiko baten oinarri komuna. Grafiko-tresna GUZTIEK hau erabiltzen
     * dute (itxura eta tooltip-ak leku bakarrean).
     */
    lineChart(canvas, { labels, datasets, xTitle, yTitle, alderantziz = false, legenda = true,
                        zeroLerroa = false, tooltipIzenburua = null, tooltipEtiketa = null }) {
        const y = alderantziz
            ? { reverse: true, min: 1, title: { display: true, text: yTitle }, ticks: { precision: 0 } }
            : { title: { display: true, text: yTitle } };
        if (zeroLerroa) {
            y.grid = {
                color: ctx => ctx.tick.value === 0 ? 'rgba(0,0,0,0.45)' : 'rgba(0,0,0,0.08)',
                lineWidth: ctx => ctx.tick.value === 0 ? 2 : 1,
            };
        }
        return new Chart(canvas, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: legenda, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            ...(tooltipIzenburua ? { title: tooltipIzenburua } : {}),
                            ...(tooltipEtiketa ? { label: tooltipEtiketa } : {}),
                        },
                        // Erreferentzia-marra etena ez erakutsi tooltip-ean
                        filter: ctx => !ctx.dataset.borderDash,
                    },
                },
                scales: {
                    x: { title: { display: true, text: xTitle || 'Karrera' } },
                    y,
                },
            },
        });
    },

    /**
     * Eboluzio-grafikoa (`eboluzioaKargatu`-ren datuekin). Hiru tresnek partekatzen dute.
     *
     * @param canvas   HTMLCanvasElement
     * @param datu     `Tresna.eboluzioaKargatu()`-ren emaitza
     * @param entsSel  Marraztu beharreko entitateak; bakoitzak `kolorea` izan behar du
     * @param metrika  'diff' (erreferentziarekiko aldea) | 'postua'
     * @param opts     { legenda = true, errefLerroa = false }
     *                 `errefLerroa`: marra etena N_ref postuan ('postua' metrikan).
     */
    eboluzioGrafikoa(canvas, datu, entsSel, metrika, opts = {}) {
        const { legenda = true, errefLerroa = false } = opts;
        const usePos = metrika === 'postua';
        // Tooltip-eko izenburua: karreraren izena, edo amaiera-puntuaren azalpena.
        const izenburuak = datu.karrerak.map(k => Tresna.karreraLabel(k.izena));
        if (datu.badaAmaiera) izenburuak.push('Sailkapen finala (orokorra + mendia barne)');

        // Amaiera-puntua nabarmendu (puntu handiagoa azkenean)
        const pointRadius = datu.badaAmaiera
            ? datu.labelak.map((_, i) => (i === datu.labelak.length - 1 ? 5 : 2))
            : 2;

        const datasets = entsSel.map(e => ({
            label: e.izena,
            data: usePos ? e.pos : e.diff,
            borderColor: e.kolorea,
            backgroundColor: e.kolorea,
            borderWidth: 2,
            pointRadius,
            tension: 0.15,
        }));

        if (usePos && errefLerroa) {
            datasets.push({
                label: `${datu.N_ref}. postua (erreferentzia)`,
                data: new Array(datu.labelak.length).fill(datu.N_ref),
                borderColor: 'rgba(255,140,0,0.8)', backgroundColor: 'transparent',
                borderWidth: 1.5, borderDash: [6, 4], pointRadius: 0,
            });
        }

        return Tresna.lineChart(canvas, {
            labels: datu.labelak,
            datasets,
            legenda,
            yTitle: usePos ? 'Postua' : `Desberdintasuna: ${datu.refLabel}`,
            alderantziz: usePos,
            zeroLerroa: !usePos,
            tooltipIzenburua: items => izenburuak[items[0].dataIndex],
            tooltipEtiketa: ctx => {
                if (ctx.dataset.borderDash) return null;          // erreferentzia-marra
                const v = ctx.raw;
                return usePos ? `${ctx.dataset.label}: ${v}. postua`
                              : `${ctx.dataset.label}: ${v > 0 ? '+' : ''}${v} p.`;
            },
        });
    },
};
