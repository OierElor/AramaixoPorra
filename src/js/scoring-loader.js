/**
 * Scoring Loader for Classic Races
 * Loads and parses scoring CSV files to populate horizontal points tables
 */

class ScoringLoader {
    /**
     * Load scoring data by category and populate container
     * @param {string} csvPath - Path to the scoring CSV file
     * @param {string} containerId - ID of the container element
     */
    async loadScoringTable(csvPath, containerId) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container with id "${containerId}" not found`);
            return;
        }

        try {
            const response = await fetch(csvPath);
            if (!response.ok) {
                throw new Error(`Failed to load scoring data: ${response.statusText}`);
            }

            const csvText = await response.text();
            const data = this.parseCSV(csvText);

            if (data.headers && data.points) {
                this.renderTable(container, data.headers, data.points);
            } else {
                throw new Error('Invalid CSV format');
            }

        } catch (error) {
            console.error('Error loading scoring table:', error);
            container.innerHTML = `<p style="color: #c00; text-align: center;">Errorea puntuazio sistema kargatzean: ${error.message}</p>`;
        }
    }

    /**
     * Parse the specialized scoring CSV
     * Expected format:
     * Posizioa,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15
     * Puntuak,800,640,520,440,360,280,240,200,160,135,110,95,85,65,55
     */
    parseCSV(csvText) {
        const lines = csvText.trim().split('\n');
        if (lines.length < 2) return null;

        const headers = lines[0].split(',').map(h => h.trim());
        const points = lines[1].split(',').map(p => p.trim());

        return {
            headers: headers.slice(1), // Remove "Posizioa"
            points: points.slice(1)    // Remove "Puntuak"
        };
    }

    /**
     * Render the premium horizontal scoring table
     */
    renderTable(container, headers, points) {
        let html = `
            <div class="table-content">
                <table class="sailkapena-table klasikak" style="width: 100%; max-width: 900px; margin: 0 auto;">
                    <thead>
                        <tr>
                            <th>Pos</th>
                            ${headers.map(h => `<th>${h}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Puntuak</strong></td>
                            ${points.map(p => `<td>${p}</td>`).join('')}
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
        container.innerHTML = html;
    }
}

// Create global instance
window.scoringLoader = new ScoringLoader();
