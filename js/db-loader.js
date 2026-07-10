/**
 * DB Loader — datuak MySQL-etik kargatu /api/q.php API seguruaren bidez.
 * SELECT kontsultak JSON gisa bidaltzen dira eta emaitzak JSON gisa jasotzen.
 */

const API_URL = '/api/q.php';

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
                    h.Dortsala    AS Dortsala,
                    t.Izena       AS Txirrindularia,
                    ks.Puntuak    AS Puntuak,
                    (SELECT COUNT(*) FROM "PorraApustuak" pa
                     WHERE pa.Txapelketa_ID = k.Txapelketa_ID
                       AND pa.Txirrindularia_ID = ks.Txirrindularia_ID) AS Zenbatek
                FROM "KarreraSailkapena" ks
                JOIN "Karrerak" k ON k.Karrerak_ID = ks.Karrera_ID
                JOIN "Txirrindulariak" t ON t.Txirrindularia_ID = ks.Txirrindularia_ID
                LEFT JOIN "TxirrindulariakTxapleketanParteHartzea" h
                    ON h.TxapelketaID = k.Txapelketa_ID
                   AND h.TxirrindulariaID = ks.Txirrindularia_ID
                WHERE ks.Karrera_ID = ?
                ORDER BY ks.Sailkapena
            `;

            const rows = await this._query(sql, [karreraId]);

            if (rows.length === 0) {
                this._showMissing(table, 'Ez dago daturik.', karreraId);
                return;
            }

            const hasDortsala = rows.some(r => this._hasData(r.Dortsala));
            const hasPuntuak  = rows.some(r => this._hasData(r.Puntuak));
            const hasZenbatek = rows.some(r => this._hasData(r.Zenbatek));

            const thead = table.querySelector('thead');
            if (thead) {
                let h = '<tr><th>Pos</th>';
                if (hasDortsala) h += '<th>Zbk</th>';
                h += '<th>Txirrindularia</th>';
                if (hasPuntuak)  h += '<th>Puntuak</th>';
                if (hasZenbatek) h += '<th>Zenbatek?</th>';
                h += '</tr>';
                thead.innerHTML = h;
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
                tr.appendChild(this._td(pos, 'pos-col'));
                if (hasDortsala) tr.appendChild(this._td(row.Dortsala ?? '—'));
                tr.appendChild(this._td(row.Txirrindularia, 'name-col'));
                if (hasPuntuak)  tr.appendChild(this._td(row.Puntuak, 'points-col'));
                if (hasZenbatek) tr.appendChild(this._td(row.Zenbatek ?? '—'));
                tbody.appendChild(tr);
            });

        } catch (err) {
            console.error(err);
            this._showError(table, err.message);
        }
    }

    /**
     * Etapa baten emaitzak kargatu zenbakiaren arabera (etapa-orri indibidualetarako).
     * Txapelketako N. benetako karrera aurkitu eta haren emaitzak erakutsi.
     */
    async loadStageByNumber(txapelketaId, n, tableId) {
        const k = await this._query(
            "SELECT Karrerak_ID AS id FROM Karrerak WHERE Txapelketa_ID = ? " +
            "AND Kategoria IS NOT NULL AND Kategoria <> '' ORDER BY (Ordena IS NULL), Ordena, Karrerak_ID LIMIT 1 OFFSET ?",
            [txapelketaId, n - 1]);
        const table = document.getElementById(tableId);
        if (!k.length) {
            if (table) this._showMissing(table, 'Ez dago etapa honetako daturik oraindik.', txapelketaId);
            return;
        }
        return this.loadKlasikaResults(k[0].id, tableId);
    }

    /**
     * Klasika baten orria bete: UCI kategoria badge-a (Karrerak.Kategoria) eta
     * emaitza-taula (Pos, Dortsala, Txirrindularia, Puntuak, Zenbatek).
     * @param {?number} karreraId  - null bada, "daturik ez" + kategoria '—'.
     */
    async loadKlasikaRace(karreraId, txapelketaId, tableId, badgeId, kategoria = null) {
        const KAT_KOLORE = {
            'Monumentua': '#f5c6cb', '3': '#ffcc99', '4': '#d4f1d4',
            '5': '#cce5ff', 'Proseries': '#fff3cd', 'Berezia': '#e2d9f3',
        };
        const badge = document.getElementById(badgeId);
        const table = document.getElementById(tableId);
        const setBadge = (kat) => {
            if (!badge) return;
            badge.textContent = this._hasData(kat) ? kat : '—';
            if (KAT_KOLORE[kat]) badge.style.backgroundColor = KAT_KOLORE[kat];
        };
        if (karreraId == null) {
            setBadge(kategoria);  // korritu gabeko lasterketa: kategoria ezaguna (parametroz)
            if (table) this._showMissing(table, 'Ez dago daturik oraindik.', txapelketaId);
            return;
        }
        try {
            if (kategoria != null) {
                setBadge(kategoria);
            } else {
                const kr = await this._query("SELECT Kategoria FROM \"Karrerak\" WHERE Karrerak_ID = ?", [karreraId]);
                if (kr.length) setBadge(kr[0].Kategoria);
            }
            const sql = `
                SELECT ks.Sailkapena AS Posizioa, h.Dortsala AS Dortsala,
                       t.Izena AS Txirrindularia, ks.Puntuak AS Puntuak,
                       (SELECT COUNT(*) FROM "PorraApustuak" pa
                         WHERE pa.Txapelketa_ID = ? AND pa.Txirrindularia_ID = ks.Txirrindularia_ID) AS Zenbatek
                FROM "KarreraSailkapena" ks
                JOIN "Txirrindulariak" t ON t.Txirrindularia_ID = ks.Txirrindularia_ID
                LEFT JOIN "TxirrindulariakTxapleketanParteHartzea" h
                    ON h.TxapelketaID = ? AND h.TxirrindulariaID = ks.Txirrindularia_ID
                WHERE ks.Karrera_ID = ? ORDER BY ks.Sailkapena`;
            const rows = await this._query(sql, [txapelketaId, txapelketaId, karreraId]);
            if (!table) return;
            if (!rows.length) { this._showMissing(table, 'Ez dago daturik oraindik.', karreraId); return; }
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            rows.forEach(row => {
                const tr = document.createElement('tr');
                const pos = row.Posizioa;
                if (pos === 1)      { tr.style.backgroundColor = '#fff4cc'; tr.style.fontWeight = 'bold'; }
                else if (pos === 2) { tr.style.backgroundColor = '#f0f0f0'; }
                else if (pos === 3) { tr.style.backgroundColor = '#fde8d0'; }
                tr.appendChild(this._td(pos, 'pos-col'));
                tr.appendChild(this._td(row.Dortsala ?? '—'));
                tr.appendChild(this._td(row.Txirrindularia, 'name-col'));
                tr.appendChild(this._td(row.Puntuak, 'points-col'));
                tr.appendChild(this._td(row.Zenbatek ?? '—'));
                tbody.appendChild(tr);
            });
        } catch (err) {
            console.error(err);
            if (table) this._showError(table, err.message);
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
    /**
     * "Etapaz etapa" akordeoia: etapa bakoitzaren emaitzak (eta profil-irudia).
     * @param {string} profilaOinarria - Etapa-profilen karpeta, adib.
     *        "/data/Etapen Profila/giro/giro26". Hutsik bada, irudirik ez.
     */
    async loadStages(txapelketaId, containerId, colorClass = '', profilaOinarria = '') {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '<p style="text-align:center;opacity:.6;padding:16px;">Etapak kargatzen...</p>';

        try {
            const stages = await this._query(`
                SELECT k.Karrerak_ID AS id, k.Izena AS izena
                FROM "Karrerak" k
                WHERE k.Txapelketa_ID = ? AND k.Kategoria = 'Etapa'
                ORDER BY (k.Ordena IS NULL), k.Ordena, k.Karrerak_ID
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
            stages.forEach((s, i) => {
                // Izena: "{Txapelketa} - {N}. etapa ({Helmuga})". Lehen ' - '-aren
                // ondorengo GUZTIA hartu (helmugak berak ' - ' izan dezake).
                const zatiak = String(s.izena).split(' - ');
                const label = zatiak.length > 1 ? zatiak.slice(1).join(' - ') : s.izena;

                const n = i + 1;
                const profila = profilaOinarria ? `
                            <div class="profile-container">
                                <img src="${profilaOinarria}/Etapa${n}.jpg" alt="${n}. etapa profila"
                                     onerror="this.onerror=null; this.src='${profilaOinarria}/Etapa${n}.png'; this.onerror=function(){this.style.display='none'};">
                            </div>` : '';

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
                        <div class="etapa-panel" hidden>${profila}
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
