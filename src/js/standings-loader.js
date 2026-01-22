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
                // Check if detailed (Vuelta) or simple (Giro)
                // Detailed: Pos, Id, Name, Pts, 1st, 2nd, 3rd, 4th, 5th, 6th, Saria (11+ cols)
                // Simple: Pos, Id, Name, Pts, Saria (5 cols)

                if (parts.length >= 10) {
                    // Detailed
                    row['Pos'] = parts[0];
                    row['Id'] = parts[1];
                    row['Name'] = parts[2];
                    row['Pts'] = parts[3];
                    row['1st'] = parts[4];
                    row['2nd'] = parts[5];
                    row['3rd'] = parts[6];
                    row['4th'] = parts[7];
                    row['5th'] = parts[8];
                    row['6th'] = parts[9];
                    row['Saria'] = parts.slice(10).join(',');
                    row['isDetailed'] = true;
                } else {
                    // Simple
                    row['Pos'] = parts[0];
                    row['Id'] = parts[1];
                    row['Name'] = parts[2];
                    row['Pts'] = parts[3];
                    row['Saria'] = parts[4] || ''; // Saria might be the last one
                    row['isDetailed'] = false;
                }

            } else if (type === 'cyclist') {
                // Detailed: Pos, Bib, Name, Count, StagePts, MtnPts, GCPts, Total (8 cols)
                // Simple: Pos, Bib, Name, Count, Total (5 cols)

                if (parts.length >= 8) {
                    row['Pos'] = parts[0];
                    row['Bib'] = parts[1];
                    row['Name'] = parts[2];
                    row['Count'] = parts[3];
                    row['StagePts'] = parts[4];
                    row['MtnPts'] = parts[5];
                    row['GCPts'] = parts[6];
                    row['Total'] = parts[7];
                    row['isDetailed'] = true;
                } else if (parts.length === 6) {
                    // Klasikak 2024 (6 cols): Pos, Bib, Name, Count, Total, Extra
                    row['Pos'] = parts[0];
                    row['Bib'] = parts[1];
                    row['Name'] = parts[2];
                    row['Count'] = parts[3];
                    row['Total'] = parts[4];
                    row['isDetailed'] = false;
                } else if (parts.length === 5) {
                    // Standard Simple (5 cols): Pos, Bib, Name, Count, Total
                    row['Pos'] = parts[0];
                    row['Bib'] = parts[1];
                    row['Name'] = parts[2];
                    row['Count'] = parts[3];
                    row['Total'] = parts[4];
                    row['isDetailed'] = false;
                } else if (parts.length === 4) {
                    // Klasikak Simple (4 cols): Pos, Name, Count, Total
                    // No Bib column
                    row['Pos'] = parts[0];
                    row['Bib'] = ''; // No Bib
                    row['Name'] = parts[1];
                    row['Count'] = parts[2];
                    row['Total'] = parts[3];
                    row['isDetailed'] = false;
                } else {
                    // Fallback using available columns if length mismatch but roughly matches
                    row['Pos'] = parts[0];
                    row['Bib'] = '';
                    row['Name'] = parts[1] || '';
                    row['Count'] = parts[2] || '';
                    row['Total'] = parts[3] || '';
                    row['isDetailed'] = false;
                }
            } else {
                // Fallback standard parsing
                headers.forEach((header, index) => {
                    row[header || index] = parts[index];
                });
            }
            data.push(row);
        }
        return data;
    }

    populateTable(table, data, type) {
        const tbody = table.querySelector('tbody');
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
        row.appendChild(this.createCell(item.Pos));
        row.appendChild(this.createCell(item.Id));
        row.appendChild(this.createCell(item.Name, 'name-col'));
        row.appendChild(this.createCell(item.Pts));

        if (item.isDetailed) {
            row.appendChild(this.createCell(item['1st']));
            row.appendChild(this.createCell(item['2nd']));
            row.appendChild(this.createCell(item['3rd']));
            row.appendChild(this.createCell(item['4th']));
            row.appendChild(this.createCell(item['5th']));
            row.appendChild(this.createCell(item['6th']));
        }

        row.appendChild(this.createCell(item.Saria));
    }

    appendCyclistColumns(row, item) {
        row.appendChild(this.createCell(item.Pos));
        row.appendChild(this.createCell(item.Bib));
        row.appendChild(this.createCell(item.Name, 'name-col'));
        row.appendChild(this.createCell(item.Count));

        if (item.isDetailed) {
            row.appendChild(this.createCell(item.StagePts));
            row.appendChild(this.createCell(item.MtnPts));
            row.appendChild(this.createCell(item.GCPts));
        }

        row.appendChild(this.createCell(item.Total));
    }

    createCell(text) {
        const td = document.createElement('td');
        td.textContent = text;
        return td;
    }
}

window.standingsLoader = new StandingsLoader();
