/**
 * Standings Loader for Vuelta 2025
 * Handles loading and displaying Porra and Cyclist standings
 */

class StandingsLoader {
    /**
     * Load Porra standings
     */
    async loadPorra(csvPath, tableId) {
        await this.loadAndPopulate(csvPath, tableId, 'porra');
    }

    /**
     * Load Cyclist standings
     */
    async loadCyclists(csvPath, tableId) {
        await this.loadAndPopulate(csvPath, tableId, 'cyclist');
    }

    async loadAndPopulate(csvPath, tableId, type) {
        const table = document.getElementById(tableId);
        if (!table) return;

        try {
            const response = await fetch(csvPath);
            if (!response.ok) throw new Error(`Failed to load CSV: ${response.statusText}`);
            const csvText = await response.text();

            const data = this.parseCSV(csvText, type);
            this.populateTable(table, data, type);
        } catch (error) {
            console.error('Error loading CSV:', error);
            table.innerHTML = `<tbody><tr><td colspan="12" style="text-align:center; color:red;">Errorea: ${error.message}</td></tr></tbody>`;
        }
    }

    parseCSV(csvText, type) {
        const lines = csvText.trim().split('\n');
        if (lines.length < 2) return [];

        const headers = lines[0].split(',').map(h => h.trim());
        const data = [];

        for (let i = 1; i < lines.length; i++) {
            let row = {};
            const parts = lines[i].split(',').map(v => v.trim());

            if (type === 'porra') {
                // Determine layout by checking for 'lerroa' or column count
                let offset = 0;
                if (headers[2] && headers[2].toLowerCase() === 'lerroa') {
                    offset = 1;
                }

                row['Pos'] = parts[0];
                row['Id'] = parts[1];
                row['Name'] = parts[2 + offset];
                row['Pts'] = parts[3 + offset];

                if (parts.length >= (10 + offset)) {
                    // Detailed (Stages 1-6 + maybe Mendia/Orokorra + Saria)
                    row['isDetailed'] = true;
                    row['stages'] = [
                        parts[4 + offset], parts[5 + offset], parts[6 + offset],
                        parts[7 + offset], parts[8 + offset], parts[9 + offset]
                    ];

                    // Mendia and Orokorra typically follow stages
                    if (parts.length >= (13 + offset)) {
                        row['Mendia'] = parts[10 + offset];
                        row['Orokorra'] = parts[11 + offset];
                        row['Saria'] = parts[12 + offset];
                    } else if (parts.length >= (11 + offset)) {
                        row['Saria'] = parts[parts.length - 1];
                    }
                } else {
                    // Simple
                    row['isDetailed'] = false;
                    if (parts.length > (4 + offset)) {
                        row['Saria'] = parts[4 + offset];
                    }
                }
            } else if (type === 'cyclist') {
                row['Pos'] = parts[0];
                if (parts.length >= 5) {
                    row['Bib'] = parts[1];
                    row['Name'] = parts[2];

                    let cyclistOffset = 0;
                    if (parts.length > 5 && (parts[3] === '' || parts[3] === undefined)) {
                        cyclistOffset = 1;
                    }

                    row['Count'] = parts[3 + cyclistOffset];
                    row['Total'] = parts[4 + cyclistOffset];

                    if (parts.length >= (8 + cyclistOffset)) {
                        row['isDetailed'] = true;
                        row['StagePts'] = parts[4 + cyclistOffset];
                        row['MtnPts'] = parts[5 + cyclistOffset];
                        row['GCPts'] = parts[6 + cyclistOffset];
                        row['Total'] = parts[7 + cyclistOffset];
                    } else {
                        row['isDetailed'] = false;
                    }
                } else if (parts.length === 4) {
                    row['Name'] = parts[1];
                    row['Count'] = parts[2];
                    row['Total'] = parts[3];
                    row['isDetailed'] = false;
                }
            }
            data.push(row);
        }
        return data;
    }

    populateTable(table, data, type) {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        data.forEach(item => {
            const row = document.createElement('tr');
            if (type === 'porra') {
                this.appendPorraColumns(row, item);
            } else {
                this.appendCyclistColumns(row, item);
            }
            tbody.appendChild(row);
        });
    }

    appendPorraColumns(row, item) {
        row.appendChild(this.createCell(item.Pos, 'pos-col'));
        row.appendChild(this.createCell(item.Id, 'id-col'));
        row.appendChild(this.createCell(item.Name, 'name-col'));
        row.appendChild(this.createCell(item.Pts, 'points-col'));

        if (item.isDetailed && item.stages) {
            item.stages.forEach(val => row.appendChild(this.createCell(val)));
            if (item.Mendia !== undefined) row.appendChild(this.createCell(item.Mendia));
            if (item.Orokorra !== undefined) row.appendChild(this.createCell(item.Orokorra));
        }

        if (item.Saria !== undefined) {
            row.appendChild(this.createCell(item.Saria));
        }
    }

    appendCyclistColumns(row, item) {
        row.appendChild(this.createCell(item.Pos, 'pos-col'));
        if (item.Bib !== undefined) {
            row.appendChild(this.createCell(item.Bib, 'id-col'));
        }
        row.appendChild(this.createCell(item.Name, 'name-col'));
        row.appendChild(this.createCell(item.Count));

        if (item.isDetailed) {
            row.appendChild(this.createCell(item.StagePts));
            row.appendChild(this.createCell(item.MtnPts));
            row.appendChild(this.createCell(item.GCPts));
        }

        row.appendChild(this.createCell(item.Total, 'points-col'));
    }

    createCell(text) {
        const td = document.createElement('td');
        td.textContent = text;
        return td;
    }
}

window.standingsLoader = new StandingsLoader();
