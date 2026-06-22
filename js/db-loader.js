/**
 * DB Loader — AramaixoPorra.db-tik datuak kargatu BROWSERREAN
 * Zero zerbitzari: sql.js erabiltzen du datu-basea memoriara kargatzeko.
 */

class DBLoader {
    constructor() {
        this.dbPromise = null;
    }

    /**
     * Datu-basea hasieratu eta itzuli (behin bakarrik).
     */
    async getDB() {
        if (!this.dbPromise) {
            this.dbPromise = (async () => {
                // 1. sql.js script-a dinamikoki kargatu
                if (!window.initSqlJs) {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.8.0/sql-wasm.js';
                        script.onload = resolve;
                        script.onerror = () => reject(new Error('Ezin izan da sql.js kargatu CDN-tik.'));
                        document.head.appendChild(script);
                    });
                }

                // 2. sql.js hasieratu wasm fitxategiarekin
                const SQL = await window.initSqlJs({
                    locateFile: file => `https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.8.0/${file}`
                });

                // 3. Datu-basea deskargatu (/data/AramaixoPorra.db)
                const dbUrl = '/data/AramaixoPorra.db';
                const res = await fetch(dbUrl);
                if (!res.ok) throw new Error(`Ezin izan da datu-basea aurkitu: ${dbUrl}`);
                const buf = await res.arrayBuffer();

                // 4. SQL.Database instantzia sortu
                return new SQL.Database(new Uint8Array(buf));
            })();
        }
        return this.dbPromise;
    }

    /**
     * SQL kontsulta exekutatu eta array of objects itzuli.
     */
    async _query(sql, params = []) {
        const db = await this.getDB();
        const stmt = db.prepare(sql);
        stmt.bind(params);
        const rows = [];
        while (stmt.step()) {
            rows.push(stmt.getAsObject());
        }
        stmt.free();
        return rows;
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
                    te.Puntuak_Generala,
                    s.Saria
                FROM "TxapelketaEmaitzaPorralariak" te
                JOIN "PorralariEzizenak" ez ON te.Ezizen_ID = ez.Ezizen_ID
                LEFT JOIN "Sariak" s
                    ON s.Txapelketa_ID = te.Txapelketa_ID
                    AND s.Posizioa = te.Posizioa
                WHERE te.Txapelketa_ID = ?
                ORDER BY te.Posizioa
            `;

            const rows = await this._query(sql, [txapelketaId]);

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
                        te."Zenbatek?",
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

            const rows = await this._query(sql, [txapelketaId]);

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
     * Klasika baten emaitzak kargatu DB-tik (KarreraSailkapena) eta taula bete.
     * EMAITZAK taula: Pos + Txirrindularia (podium koloreekin).
     */
    async loadKlasikaResults(karreraId, tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        this._showLoading(table);

        try {
            const sql = `
                SELECT
                    ks.Sailkapena AS Posizioa,
                    t.Izena       AS Txirrindularia
                FROM "KarreraSailkapena" ks
                JOIN "Txirrindulariak" t ON t.Txirrindularia_ID = ks.Txirrindularia_ID
                WHERE ks.Karrera_ID = ?
                ORDER BY ks.Sailkapena
            `;

            const rows = await this._query(sql, [karreraId]);

            if (rows.length === 0) {
                this._showMissing(table, 'Ez dago daturik.', karreraId);
                return;
            }

            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            tbody.innerHTML = '';

            rows.forEach(row => {
                const tr = document.createElement('tr');
                const pos = row.Posizioa;
                if (pos === 1)      { tr.style.backgroundColor = '#fff4cc'; tr.style.fontWeight = 'bold'; }
                else if (pos === 2) { tr.style.backgroundColor = '#f0f0f0'; }
                else if (pos === 3) { tr.style.backgroundColor = '#fde8d0'; }
                tr.appendChild(this._td(pos));
                tr.appendChild(this._td(row.Txirrindularia));
                tbody.appendChild(tr);
            });

        } catch (err) {
            console.error(err);
            this._showError(table, err.message);
        }
    }

    /**
     * Hiru handietako etapaz etapako emaitzak kargatu (KarreraSailkapena) eta
     * akordeoi gisa erakutsi: etapa bakoitza zabaltzean puntuatu duten
     * txirrindulariak (Pos, Txirrindularia, Puntuak).
     * @param {number} txapelketaId
     * @param {string} containerId  - etapen edukiontziaren ID-a
     * @param {string} colorClass   - 'vuelta' | 'giro' | 'tour' (estiloetarako)
     */
    async loadStages(txapelketaId, containerId, colorClass = '') {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '<p style="text-align:center;opacity:.6;padding:16px;">Etapak kargatzen...</p>';

        try {
            const stages = await this._query(`
                SELECT k.Karrerak_ID AS id, k.Izena AS izena
                FROM "Karrerak" k
                WHERE k.Txapelketa_ID = ? AND k.Kategoria = 'Etapa'
                ORDER BY k.Karrerak_ID
            `, [txapelketaId]);

            if (stages.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center;padding:24px;">
                        <span style="font-size:1.4em;">⚠️</span><br>
                        <strong>Ez dago etapa daturik oraindik</strong>
                    </div>`;
                return;
            }

            const results = await this._query(`
                SELECT ks.Karrera_ID AS kid, ks.Sailkapena AS pos,
                       t.Izena AS izena, ks.Puntuak AS puntuak
                FROM "KarreraSailkapena" ks
                JOIN "Karrerak" k ON k.Karrerak_ID = ks.Karrera_ID
                JOIN "Txirrindulariak" t ON t.Txirrindularia_ID = ks.Txirrindularia_ID
                WHERE k.Txapelketa_ID = ? AND k.Kategoria = 'Etapa'
                ORDER BY ks.Karrera_ID, ks.Sailkapena
            `, [txapelketaId]);

            const byStage = {};
            results.forEach(r => { (byStage[r.kid] = byStage[r.kid] || []).push(r); });

            const cls = colorClass ? ' ' + colorClass : '';
            let html = '<div class="etapak-accordion">';
            stages.forEach(s => {
                const label = String(s.izena).split(' - ').pop();  // "1. etapa (Helmuga)"
                const riders = byStage[s.id] || [];
                const rowsHtml = riders.map(r =>
                    `<tr><td class="pos-col">${r.pos}</td>` +
                    `<td class="name-col">${this._esc(r.izena)}</td>` +
                    `<td class="points-col">${r.puntuak}</td></tr>`
                ).join('');
                html += `
                    <div class="etapa-item">
                        <button type="button" class="etapa-toggle${cls}">
                            <span>${this._esc(label)}</span>
                            <span class="etapa-chevron">▾</span>
                        </button>
                        <div class="etapa-panel" hidden>
                            <table class="sailkapena-table${cls}" style="margin:0;">
                                <thead><tr><th>Pos</th><th>Txirrindularia</th><th>Puntuak</th></tr></thead>
                                <tbody>${rowsHtml ||
                                    '<tr><td colspan="3" style="opacity:.6;">Daturik ez</td></tr>'}</tbody>
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
            container.innerHTML = `<p style="color:#c00;text-align:center;padding:16px;">Errorea etapak kargatzen: ${err.message}</p>`;
        }
    }

    // ── Barne laguntzaileak ────────────────────────────────────────────────────

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
        const hasSaria    = rows.some(r => this._hasData(r.Saria));

        this._updatePorraThead(table, hasMendikoa, hasGenerala, hasSaria);

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
            if (hasSaria)    tr.appendChild(this._td(row.Saria ?? ''));
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

    _updatePorraThead(table, hasMendikoa, hasGenerala, hasSaria) {
        const thead = table.querySelector('thead');
        if (!thead) return;

        let html = '<tr>';
        html += '<th>Pos</th>';
        html += '<th>Porralaria</th>';
        html += '<th>Puntuak</th>';
        if (hasMendikoa) html += '<th>Mendia</th>';
        if (hasGenerala) html += '<th>Orokorra</th>';
        if (hasSaria)    html += '<th>Saria</th>';
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
