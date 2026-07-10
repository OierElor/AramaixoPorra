/**
 * DB Loader — datuak MySQL-etik kargatu /api/q.php API seguruaren bidez.
 * SELECT kontsultak JSON gisa bidaltzen dira eta emaitzak JSON gisa jasotzen.
 */

const API_URL = '/api/q.php';

/** Klasikoen UCI kategoria bakoitzaren koloretxoa (akordeoiaren txartelean). */
const KAT_KOLOREAK = {
    'Monumentua': '#f5c6cb', '3': '#ffcc99', '4': '#d4f1d4',
    '5': '#cce5ff', 'Proseries': '#fff3cd', 'Berezia': '#e2d9f3',
};

class DBLoader {
    /**
     * SQL kontsulta publikoa (grafikoetarako e.a.): array of objects itzuli.
     */
    async query(sql, params = []) {
        return this._query(sql, params);
    }

    /**
     * SELECT kontsulta API-ra bidali eta emaitza-lerroak (array of objects) itzuli.
     */
    async _query(sql, params = []) {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sql, params }),
        });
        if (!res.ok) {
            let msg = 'HTTP ' + res.status;
            try { const j = await res.json(); if (j && j.error) msg = j.error; } catch (e) { /* ignoratu */ }
            throw new Error(msg);
        }
        return res.json();
    }

    /**
     * Porra sailkapena kargatu DB-tik eta taula bete.
     */
    async loadPorra(txapelketaId, tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        this._showLoading(table);

        try {
            const sql = `
                SELECT
                    te.Posizioa,
                    ez.Ezizena,
                    te.Puntuak,
                    te.Puntuak_Mendikoa,
                    te.Puntuak_Generala
                FROM "TxapelketaEmaitzaPorralariak" te
                JOIN "PorraEzizenak" ez ON te.Ezizen_ID = ez.Ezizen_ID
                WHERE te.Txapelketa_ID = ?
                ORDER BY te.Posizioa
            `;

            let rows = await this._query(sql, [txapelketaId]);

            // Emaitza ofizialik ezean, azken sailkapena erakutsi (bilakaeratik).
            if (rows.length === 0) {
                rows = await this._porraFallback(txapelketaId);
            }

            if (rows.length === 0) {
                this._showMissing(table, 'Ez dago daturik.', txapelketaId);
                return;
            }

            this._fillPorraTable(table, rows);

        } catch (err) {
            console.error(err);
            this._showError(table, err.message);
        }
    }

    /**
     * Txirrindulari sailkapena kargatu DB-tik eta taula bete.
     */
    async loadCyclists(txapelketaId, tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        this._showLoading(table);

        try {
            const sql = `
                SELECT
                    te.Posizioa,
                    h.Dortsala,
                    t.Izena   AS Txirrindularia,
                    te.Puntuak,
                    COALESCE(
                        te.Zenbatek,
                        (SELECT COUNT(*) FROM "PorraApustuak" pa
                          WHERE pa.Txapelketa_ID = te.Txapelketa_ID
                            AND pa.Txirrindularia_ID = te.Txirrindularia_ID)
                    ) AS Zenbatek,
                    te.Puntuak_Sailkapen_Nag,
                    te.Puntuak_Mendian
                FROM "TxapelketaEmaitzaTxirrindulariak" te
                JOIN "Txirrindulariak" t ON te.Txirrindularia_ID = t.Txirrindularia_ID
                LEFT JOIN "TxirrindulariakTxapleketanParteHartzea" h
                    ON h.TxapelketaID = te.Txapelketa_ID
                    AND h.TxirrindulariaID = te.Txirrindularia_ID
                WHERE te.Txapelketa_ID = ?
                ORDER BY te.Posizioa
            `;

            let rows = await this._query(sql, [txapelketaId]);

            // Emaitza ofizialik ezean, azken sailkapena erakutsi (bilakaera / etapak).
            if (rows.length === 0) {
                rows = await this._cyclistFallback(txapelketaId);
            }

            if (rows.length === 0) {
                this._showMissing(table, 'Ez dago daturik.', txapelketaId);
                return;
            }

            this._fillCyclistTable(table, rows);

        } catch (err) {
            console.error(err);
            this._showError(table, err.message);
        }
    }

    /**
     * Porra azken sailkapena (emaitza ofizialik ezean): bilakaera-taulako azken
     * karrera arteko puntu metatuak. Posizioa JS-en kalkulatzen da.
     */
    async _porraFallback(txapelketaId) {
        const sql = `
            SELECT ez.Ezizena, sp.Puntuak_Totalean AS Puntuak
            FROM "TxapelketaSailkapenaPorralariak" sp
            JOIN "PorraEzizenak" ez ON ez.Ezizen_ID = sp.Ezizen_ID
            WHERE sp.Txapelketa_ID = ?
              AND sp.Azken_Karrera_ID = (
                  SELECT s2.Azken_Karrera_ID FROM "TxapelketaSailkapenaPorralariak" s2
                  JOIN "Karrerak" k ON k.Karrerak_ID = s2.Azken_Karrera_ID
                  WHERE s2.Txapelketa_ID = ?
                  ORDER BY (k.Ordena IS NULL) DESC, k.Ordena DESC, k.Karrerak_ID DESC LIMIT 1)
            ORDER BY sp.Puntuak_Totalean DESC, ez.Ezizena
        `;
        const rows = await this._query(sql, [txapelketaId, txapelketaId]);
        rows.forEach((r, i) => { r.Posizioa = i + 1; });
        return rows;
    }

    /**
     * Txirrindulari azken sailkapena (emaitza ofizialik ezean): lehenik
     * txirrindularien bilakaera-taula; hori ezean, KarreraSailkapena puntuen batura.
     */
    async _cyclistFallback(txapelketaId) {
        const evoSql = `
            SELECT t.Izena AS Txirrindularia, st.Puntuak_Totalean AS Puntuak, h.Dortsala,
                   (SELECT COUNT(*) FROM "PorraApustuak" pa
                     WHERE pa.Txapelketa_ID = st.Txapelketa_ID
                       AND pa.Txirrindularia_ID = st.Txirrindularia_ID) AS Zenbatek
            FROM "TxapelketaSailkapenaTxirrindulariak" st
            JOIN "Txirrindulariak" t ON t.Txirrindularia_ID = st.Txirrindularia_ID
            LEFT JOIN "TxirrindulariakTxapleketanParteHartzea" h
                ON h.TxapelketaID = st.Txapelketa_ID AND h.TxirrindulariaID = st.Txirrindularia_ID
            WHERE st.Txapelketa_ID = ?
              AND st.Azken_Karrera_ID = (
                  SELECT s2.Azken_Karrera_ID FROM "TxapelketaSailkapenaTxirrindulariak" s2
                  JOIN "Karrerak" k ON k.Karrerak_ID = s2.Azken_Karrera_ID
                  WHERE s2.Txapelketa_ID = ?
                  ORDER BY (k.Ordena IS NULL) DESC, k.Ordena DESC, k.Karrerak_ID DESC LIMIT 1)
            ORDER BY st.Puntuak_Totalean DESC, t.Izena
        `;
        let rows = await this._query(evoSql, [txapelketaId, txapelketaId]);

        if (rows.length === 0) {
            // KarreraSailkapena puntuen batura (etapak/lasterketak)
            const sumSql = `
                SELECT t.Izena AS Txirrindularia, SUM(ks.Puntuak) AS Puntuak, h.Dortsala,
                       (SELECT COUNT(*) FROM "PorraApustuak" pa
                         WHERE pa.Txapelketa_ID = k.Txapelketa_ID
                           AND pa.Txirrindularia_ID = ks.Txirrindularia_ID) AS Zenbatek
                FROM "KarreraSailkapena" ks
                JOIN "Karrerak" k ON k.Karrerak_ID = ks.Karrera_ID
                JOIN "Txirrindulariak" t ON t.Txirrindularia_ID = ks.Txirrindularia_ID
                LEFT JOIN "TxirrindulariakTxapleketanParteHartzea" h
                    ON h.TxapelketaID = k.Txapelketa_ID AND h.TxirrindulariaID = ks.Txirrindularia_ID
                WHERE k.Txapelketa_ID = ?
                GROUP BY ks.Txirrindularia_ID
                ORDER BY Puntuak DESC, t.Izena
            `;
            rows = await this._query(sumSql, [txapelketaId]);
        }
        rows.forEach((r, i) => { r.Posizioa = i + 1; });
        return rows;
    }

    /**
     * Karrera-emaitzen akordeoia — txapelketa GUZTIENTZAKO sistema BAKARRA
     * (itzuli handiak eta klasikoak). Karrera bakoitzaren panelak bere
     * profil/ibilbide irudia (baldin badago) eta emaitza-taula erakusten ditu.
     *
     * Zutabeak karreraz karrera egokitzen dira datuen arabera:
     * Pos · Zbk · Txirrindularia · Puntuak · Zenbatek?
     *
     * @param {number} txapelketaId
     * @param {string} containerId  akordeoiaren edukiontziaren ID-a
     * @param {string} colorClass   'tour' | 'giro' | 'vuelta' | 'klasikak'
     * @param {?function(number, number): string[]} irudiFn
     *        (karreraId, zenbakia) → probatu beharreko irudi-URLak (gehienez bi).
     *        Lehenak huts eginez gero bigarrena; denek huts, irudia ezkutatu.
     *        null → irudirik ez.
     */
    async loadKarrerak(txapelketaId, containerId, colorClass = '', irudiFn = null) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '<p style="text-align:center;opacity:.6;padding:16px;">Karrerak kargatzen...</p>';

        try {
            const karrerak = await this._query(`
                SELECT Karrerak_ID AS kid, Izena AS izena, Kategoria AS kat
                FROM "Karrerak"
                WHERE Txapelketa_ID = ? AND Kategoria IS NOT NULL AND Kategoria <> ''
                ORDER BY (Ordena IS NULL), Ordena, Karrerak_ID
            `, [txapelketaId]);

            if (!karrerak.length) {
                container.innerHTML = `
                    <div style="text-align:center;padding:24px;">
                        <span style="font-size:1.4em;">⚠️</span><br>
                        <strong>Ez dago karrera daturik oraindik</strong>
                    </div>`;
                return;
            }

            const emaitzak = await this._query(`
                SELECT ks.Karrera_ID AS kid, ks.Txirrindularia_ID AS txid,
                       ks.Sailkapena AS pos, ks.Puntuak AS puntuak,
                       t.Izena AS izena, h.Dortsala AS dortsala
                FROM "KarreraSailkapena" ks
                JOIN "Karrerak" k ON k.Karrerak_ID = ks.Karrera_ID
                JOIN "Txirrindulariak" t ON t.Txirrindularia_ID = ks.Txirrindularia_ID
                LEFT JOIN "TxirrindulariakTxapleketanParteHartzea" h
                    ON h.TxapelketaID = k.Txapelketa_ID
                   AND h.TxirrindulariaID = ks.Txirrindularia_ID
                WHERE k.Txapelketa_ID = ? AND k.Kategoria IS NOT NULL AND k.Kategoria <> ''
                ORDER BY ks.Karrera_ID, ks.Sailkapena
            `, [txapelketaId]);

            // Txirrindulari bakoitza zenbat porralarik jokatu duen: kontsulta bakarra
            // (karreraz karrerako azpi-kontsulta korrelatuak saihesteko).
            const apustuak = await this._query(`
                SELECT Txirrindularia_ID AS txid, COUNT(*) AS zenbatek
                FROM "PorraApustuak"
                WHERE Txapelketa_ID = ?
                GROUP BY Txirrindularia_ID
            `, [txapelketaId]);
            const zenbatekaz = new Map(apustuak.map(a => [a.txid, Number(a.zenbatek)]));
            // Txapelketak apusturik ez badu, `Zenbatek?` zutabea ezkutatzen da (null).
            // Baditu: apusturik gabeko txirrindulariak 0 dira, ez hutsune.
            const apustuakBadaude = apustuak.length > 0;

            const karreraka = new Map();
            emaitzak.forEach(r => {
                if (!karreraka.has(r.kid)) karreraka.set(r.kid, []);
                karreraka.get(r.kid).push(r);
            });

            const cls = colorClass ? ' ' + colorClass : '';
            let html = '<div class="etapak-accordion">';

            karrerak.forEach((k, i) => {
                const lerroak = karreraka.get(k.kid) || [];
                lerroak.forEach(r => {
                    r.zenbatek = apustuakBadaude ? (zenbatekaz.get(r.txid) ?? 0) : null;
                });

                const badaDortsala = lerroak.some(r => this._hasData(r.dortsala));
                const badaPuntuak  = lerroak.some(r => this._hasData(r.puntuak));
                const badaZenbatek = lerroak.some(r => this._hasData(r.zenbatek));
                const zutabeKop = 2 + Number(badaDortsala) + Number(badaPuntuak) + Number(badaZenbatek);

                let goiburua = '<tr><th>Pos</th>';
                if (badaDortsala) goiburua += '<th>Zbk</th>';
                goiburua += '<th>Txirrindularia</th>';
                if (badaPuntuak)  goiburua += '<th>Puntuak</th>';
                if (badaZenbatek) goiburua += '<th>Zenbatek?</th>';
                goiburua += '</tr>';

                const lerroakHtml = lerroak.map(r => {
                    let tr = `<tr${this._podiumEstiloa(r.pos)}><td class="pos-col">${this._esc(r.pos)}</td>`;
                    if (badaDortsala) tr += `<td>${this._esc(r.dortsala ?? '—')}</td>`;
                    tr += `<td class="name-col">${this._esc(r.izena)}</td>`;
                    if (badaPuntuak)  tr += `<td class="points-col">${this._esc(r.puntuak)}</td>`;
                    if (badaZenbatek) tr += `<td>${this._esc(r.zenbatek ?? '—')}</td>`;
                    return tr + '</tr>';
                }).join('');

                const irudia = this._profilaHtml(irudiFn ? irudiFn(k.kid, i + 1) : null, `${k.izena} profila`);

                html += `
                    <div class="etapa-item">
                        <button type="button" class="etapa-toggle${cls}">
                            <span class="etapa-izena">${this._esc(this._karreraLabel(k.izena))}${this._katBadge(k.kat)}</span>
                            <span class="etapa-chevron">▾</span>
                        </button>
                        <div class="etapa-panel" hidden>${irudia}
                            <table class="sailkapena-table${cls}" style="margin:0;">
                                <thead>${goiburua}</thead>
                                <tbody>${lerroakHtml ||
                                    `<tr><td colspan="${zutabeKop}" style="opacity:.6;">Daturik ez</td></tr>`}</tbody>
                            </table>
                        </div>
                    </div>`;
            });
            html += '</div>';
            container.innerHTML = html;

            container.querySelectorAll('.etapa-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    const panel = btn.nextElementSibling;
                    const wasOpen = !panel.hidden;
                    panel.hidden = wasOpen;
                    btn.classList.toggle('open', !wasOpen);
                });
            });

        } catch (err) {
            console.error(err);
            container.innerHTML = `<p style="color:#c00;text-align:center;padding:16px;">Errorea karrerak kargatzen: ${err.message}</p>`;
        }
    }

    // ── Barne laguntzaileak ────────────────────────────────────────────────────

    /**
     * Akordeoiaren etiketa. Itzulietan karreraren izena
     * "{Txapelketa} - {N}. etapa ({Helmuga})" da → txapelketaren aurrizkia kentzen da
     * (helmugak berak ' - ' izan dezake: "1. etapa (Durazzo - Tirana)").
     * Klasikoetan izena osorik uzten da: "Milano - Torino" EZIN da zatitu.
     */
    _karreraLabel(izena) {
        const s = String(izena);
        const m = s.match(/^.+?\s-\s(\d+\.\s*etapa.*)$/i);
        return m ? m[1] : s;
    }

    /** UCI kategoria-txartela (klasikoak). Itzulietako 'Etapa' ez da erakusten. */
    _katBadge(kat) {
        if (!this._hasData(kat) || String(kat) === 'Etapa') return '';
        const kolorea = KAT_KOLOREAK[kat] || '#e0e0e0';
        return ` <span class="kat-badge" style="background-color:${kolorea};">${this._esc(kat)}</span>`;
    }

    /** Podiumeko hiru lehenak nabarmendu (akordeoiko emaitza-taulan). */
    _podiumEstiloa(pos) {
        const p = Number(pos);
        if (p === 1) return ' style="background-color:#fff4cc;font-weight:bold;"';
        if (p === 2) return ' style="background-color:#f0f0f0;"';
        if (p === 3) return ' style="background-color:#fde8d0;"';
        return '';
    }

    /**
     * Profil/ibilbide irudia. `urls`: gehienez bi hautagai; lehenak huts eginez
     * gero bigarrena saiatzen da, hark ere huts eginez gero irudia ezkutatzen da.
     * URLek zuriuneak izan ditzakete kodetu gabe (adib. ".../Etapen Profila/...");
     * horrela `css/styles.css`-eko `img[src*="Etapen Profila"]` arauak 800 px-ra
     * mugatzen ditu. Fitxategi-izenetan ez dago komatxorik ez apostroforik.
     */
    _profilaHtml(urls, alt) {
        if (!urls || !urls.length) return '';
        const onerror = urls.length > 1
            ? `this.onerror=function(){this.style.display='none'};this.src='${urls[1]}'`
            : `this.style.display='none'`;
        return '<div class="profile-container">' +
               `<img src="${urls[0]}" alt="${this._esc(alt)}" loading="lazy" onerror="${onerror}">` +
               '</div>';
    }

    _esc(s) {
        const div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    _hasData(value) {
        return value != null && String(value).trim() !== '';
    }

    _fillPorraTable(table, rows) {
        const hasMendikoa = rows.some(r => this._hasData(r.Puntuak_Mendikoa));
        const hasGenerala = rows.some(r => this._hasData(r.Puntuak_Generala));

        this._updatePorraThead(table, hasMendikoa, hasGenerala);

        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.appendChild(this._td(row.Posizioa, 'pos-col'));
            tr.appendChild(this._td(row.Ezizena, 'name-col'));
            tr.appendChild(this._td(row.Puntuak, 'points-col'));
            if (hasMendikoa) tr.appendChild(this._td(row.Puntuak_Mendikoa ?? '—'));
            if (hasGenerala) tr.appendChild(this._td(row.Puntuak_Generala ?? '—'));
            tbody.appendChild(tr);
        });
    }

    _fillCyclistTable(table, rows) {
        const hasDortsala  = rows.some(r => this._hasData(r.Dortsala));
        const hasZenbatek  = rows.some(r => this._hasData(r.Zenbatek));
        const hasSailkNag  = rows.some(r => this._hasData(r.Puntuak_Sailkapen_Nag));
        const hasMendia    = rows.some(r => this._hasData(r.Puntuak_Mendian));

        this._updateCyclistThead(table, hasDortsala, hasZenbatek, hasSailkNag, hasMendia);

        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.appendChild(this._td(row.Posizioa, 'pos-col'));
            if (hasDortsala) tr.appendChild(this._td(row.Dortsala ?? '—'));
            tr.appendChild(this._td(row.Txirrindularia, 'name-col'));
            tr.appendChild(this._td(row.Puntuak, 'points-col'));
            if (hasZenbatek) tr.appendChild(this._td(row.Zenbatek ?? '—'));
            if (hasSailkNag) tr.appendChild(this._td(row.Puntuak_Sailkapen_Nag ?? '—'));
            if (hasMendia)   tr.appendChild(this._td(row.Puntuak_Mendian ?? '—'));
            tbody.appendChild(tr);
        });
    }

    _updatePorraThead(table, hasMendikoa, hasGenerala) {
        const thead = table.querySelector('thead');
        if (!thead) return;

        let html = '<tr>';
        html += '<th>Pos</th>';
        html += '<th>Porralaria</th>';
        html += '<th>Puntuak</th>';
        if (hasMendikoa) html += '<th>Mendia</th>';
        if (hasGenerala) html += '<th>Orokorra</th>';
        html += '</tr>';

        thead.innerHTML = html;
    }

    _updateCyclistThead(table, hasDortsala, hasZenbatek, hasSailkNag, hasMendia) {
        const thead = table.querySelector('thead');
        if (!thead) return;

        let html = '<tr>';
        html += '<th>Pos</th>';
        if (hasDortsala) html += '<th>Dortsala</th>';
        html += '<th>Txirrindularia</th>';
        html += '<th>Puntuak</th>';
        if (hasZenbatek) html += '<th>Zenbatek?</th>';
        if (hasSailkNag) html += '<th>Orokorra</th>';
        if (hasMendia)   html += '<th>Mendia</th>';
        html += '</tr>';

        thead.innerHTML = html;
    }

    _td(content, className) {
        const td = document.createElement('td');
        td.textContent = this._hasData(content) ? content : '';
        if (className) td.className = className;
        return td;
    }

    _showLoading(table) {
        const tbody = table.querySelector('tbody');
        const cols = table.querySelectorAll('thead th').length || 4;
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;padding:20px;opacity:.6;">Datu-basea kargatzen... (lehenengo aldian denbora pixka bat har dezake)</td></tr>`;
        }
    }

    _showMissing(table, message, txapelketaId) {
        const tbody = table.querySelector('tbody');
        const cols = table.querySelectorAll('thead th').length || 4;
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${cols}" style="text-align:center;padding:24px;">
                        <span style="font-size:1.4em;">⚠️</span><br>
                        <strong>Ez dago daturik oraindik taula honetan</strong><br>
                        <small style="opacity:.65;">${message}</small>
                    </td>
                </tr>`;
        }
    }

    _showError(table, message) {
        const tbody = table.querySelector('tbody');
        const cols = table.querySelectorAll('thead th').length || 4;
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${cols}" style="text-align:center;padding:24px;color:#c00;">
                        <span style="font-size:1.4em;">❌</span><br>
                        <strong>Errorea datuak kargatzean</strong><br>
                        <small style="opacity:.75;">${message}</small>
                    </td>
                </tr>`;
        }
    }
}

window.dbLoader = new DBLoader();
