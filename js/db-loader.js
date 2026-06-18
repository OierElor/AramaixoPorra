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
                    t.Izena   AS Txirrindularia,
                    te.Puntuak,
                    te."Zenbatek?"        AS Zenbatek,
                    te.Puntuak_Sailkapen_Nag,
                    te.Puntuak_Mendian
                FROM "TxapelketaEmaitzaTxirrindulariak" te
                JOIN "Txirrindulariak" t ON te.Txirrindularia_ID = t.Txirrindularia_ID
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

    // ── Barne laguntzaileak ────────────────────────────────────────────────────

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
        const hasZenbatek  = rows.some(r => this._hasData(r.Zenbatek));
        const hasSailkNag  = rows.some(r => this._hasData(r.Puntuak_Sailkapen_Nag));
        const hasMendia    = rows.some(r => this._hasData(r.Puntuak_Mendian));

        this._updateCyclistThead(table, hasZenbatek, hasSailkNag, hasMendia);

        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.appendChild(this._td(row.Posizioa, 'pos-col'));
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

    _updateCyclistThead(table, hasZenbatek, hasSailkNag, hasMendia) {
        const thead = table.querySelector('thead');
        if (!thead) return;

        let html = '<tr>';
        html += '<th>Pos</th>';
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
