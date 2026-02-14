/**
 * Klasikak Results Loader
 * CSV fitxategitik lasterketa emaitzak kargatu eta taula betetzeko
 */

class KlasikakResultsLoader {
    /**
     * CSV fitxategia kargatu eta taula betetu
     * @param {string} csvPath - CSV fitxategiaren path-a
     * @param {string} tableId - Taularen ID-a
     * @param {number} raceIndex - CSV-ko lasterketa zenbakia (1-indexatuta)
     */
    async load(csvPath, tableId, raceIndex) {
        const table = document.getElementById(tableId);
        if (!table) {
            console.error(`"${tableId}" id-a duen taula ez da aurkitu`);
            return;
        }

        try {
            const tbody = table.querySelector('tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="2" style="text-align:center; padding:20px;">Kargatzen...</td></tr>';
            }

            const response = await fetch(csvPath);
            if (!response.ok) throw new Error(`CSV kargatzean errorea: ${response.statusText}`);

            const csvText = await response.text();
            const lines = csvText.trim().split('\n');

            // Aurkitu lasterketa errenkada indexaren bidez
            let raceRow = null;
            for (let i = 1; i < lines.length; i++) {
                const parts = lines[i].split(',');
                if (parts[0] && parseInt(parts[0].trim()) === raceIndex) {
                    raceRow = parts;
                    break;
                }
            }

            if (!raceRow) {
                throw new Error(`${raceIndex} indexeko lasterketa ez da aurkitu CSV-an`);
            }

            // Txirrindulariak 5. zutabetik aurrera (index 4-18, 15 txirrindulari)
            const cyclists = [];
            for (let i = 4; i < raceRow.length && i < 19; i++) {
                const name = raceRow[i].trim();
                if (name) {
                    cyclists.push(name);
                }
            }

            // Taula betetu
            if (tbody) {
                tbody.innerHTML = '';
                cyclists.forEach((name, index) => {
                    const tr = document.createElement('tr');
                    const pos = index + 1;

                    // Podium koloreak
                    if (pos === 1) {
                        tr.style.backgroundColor = '#fff4cc';
                        tr.style.fontWeight = 'bold';
                    } else if (pos === 2) {
                        tr.style.backgroundColor = '#f0f0f0';
                    } else if (pos === 3) {
                        tr.style.backgroundColor = '#fde8d0';
                    }

                    const tdPos = document.createElement('td');
                    tdPos.textContent = pos;
                    tr.appendChild(tdPos);

                    const tdName = document.createElement('td');
                    tdName.textContent = name;
                    tr.appendChild(tdName);

                    tbody.appendChild(tr);
                });
            }

        } catch (error) {
            console.error('Errorea CSV kargatzen:', error);
            const tbody = table.querySelector('tbody');
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="2" style="text-align:center; padding:20px; color:#c00;">Errorea: ${error.message}</td></tr>`;
            }
        }
    }
}

window.klasikakResults = new KlasikakResultsLoader();
