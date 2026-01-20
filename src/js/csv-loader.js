/**
 * CSV Loader for Race Standings
 * Loads and parses CSV files to populate race standings tables
 */

class CSVLoader {
    /**
     * Load CSV file and populate table
     * @param {string} csvPath - Path to CSV file
     * @param {string} tableId - ID of table to populate
     * @param {object} metadata - Optional metadata (etapa, tokia, data)
     */
    async loadAndPopulate(csvPath, tableId, metadata = {}) {
        const table = document.getElementById(tableId);
        if (!table) {
            console.error(`Table with id "${tableId}" not found`);
            return;
        }

        try {
            // Show loading indicator
            this.showLoading(table);

            // Fetch CSV file
            const response = await fetch(csvPath);
            if (!response.ok) {
                throw new Error(`Failed to load CSV: ${response.statusText}`);
            }

            const csvText = await response.text();

            // Parse CSV
            const data = this.parseCSV(csvText);

            // Populate table
            this.populateTable(table, data, metadata);

            // Hide loading indicator
            this.hideLoading(table);

        } catch (error) {
            console.error('Error loading CSV:', error);
            this.showError(table, `Error kargatzean: ${error.message}`);
        }
    }

    /**
     * Parse CSV text into array of objects
     * @param {string} csvText - Raw CSV text
     * @returns {Array} Array of data objects
     */
    parseCSV(csvText) {
        const lines = csvText.trim().split('\n');
        if (lines.length < 2) {
            throw new Error('CSV fitxategia hutsik dago edo ez du daturik');
        }

        // Get headers
        const headers = lines[0].split(',').map(h => h.trim());

        // Parse data rows
        const data = [];
        for (let i = 1; i < lines.length; i++) {
            const values = lines[i].split(',').map(v => v.trim());
            if (values.length === headers.length) {
                const row = {};
                headers.forEach((header, index) => {
                    row[header] = values[index];
                });
                data.push(row);
            }
        }

        return data;
    }

    /**
     * Populate table with data
     * @param {HTMLElement} table - Table element
     * @param {Array} data - Array of data objects
     * @param {object} metadata - Metadata for headers
     */
    populateTable(table, data, metadata) {
        const tbody = table.querySelector('tbody');
        if (!tbody) {
            throw new Error('Table tbody not found');
        }

        // Update metadata in header if provided
        if (metadata.etapa || metadata.tokia || metadata.data) {
            this.updateTableHeader(table, metadata);
        }

        // Clear existing rows
        tbody.innerHTML = '';

        // Split data into two columns (left: 1-33, right: 34-66)
        const midPoint = Math.ceil(data.length / 2);
        const leftData = data.slice(0, midPoint);
        const rightData = data.slice(midPoint);

        // Create rows
        for (let i = 0; i < leftData.length; i++) {
            const row = document.createElement('tr');

            // Left side data
            const left = leftData[i];
            row.appendChild(this.createCell(left.Posizioa, 'pos-col'));
            row.appendChild(this.createCell(left.Porreoa, 'name-col'));
            row.appendChild(this.createCell(left.Gaur, 'points-col'));
            row.appendChild(this.createCell(left.Guztira, 'points-col'));

            // Right side data (if exists)
            if (rightData[i]) {
                const right = rightData[i];
                row.appendChild(this.createCell(right.Posizioa, 'pos-col'));
                row.appendChild(this.createCell(right.Porreoa, 'name-col'));
                row.appendChild(this.createCell(right.Gaur, 'points-col'));
                row.appendChild(this.createCell(right.Guztira, 'points-col'));
            } else {
                // Empty cells if no right side data
                for (let j = 0; j < 4; j++) {
                    row.appendChild(this.createCell('', ''));
                }
            }

            tbody.appendChild(row);
        }
    }

    /**
     * Create table cell
     * @param {string} content - Cell content
     * @param {string} className - CSS class name
     * @returns {HTMLElement} Table cell element
     */
    createCell(content, className) {
        const cell = document.createElement('td');
        cell.textContent = content;
        if (className) {
            cell.className = className;
        }
        return cell;
    }

    /**
     * Update table header with metadata
     * @param {HTMLElement} table - Table element
     * @param {object} metadata - Metadata object
     */
    updateTableHeader(table, metadata) {
        const thead = table.querySelector('thead');
        if (!thead) return;

        // Update etapa
        if (metadata.etapa) {
            const etapaCell = thead.querySelector('.etapa-header');
            if (etapaCell) {
                etapaCell.textContent = `Etapa: ${metadata.etapa}`;
            }
        }

        // Update location and date
        const headerCells = thead.querySelectorAll('.etapa-header');
        if (metadata.tokia && headerCells[1]) {
            headerCells[1].textContent = metadata.tokia;
        }
        if (metadata.data && headerCells[2]) {
            headerCells[2].textContent = `Eguna: ${metadata.data}`;
        }
    }

    /**
     * Show loading indicator
     * @param {HTMLElement} table - Table element
     */
    showLoading(table) {
        const tbody = table.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px;">Kargatzen...</td></tr>';
        }
    }

    /**
     * Hide loading indicator
     * @param {HTMLElement} table - Table element
     */
    hideLoading(table) {
        // Loading is hidden when data is populated
    }

    /**
     * Show error message
     * @param {HTMLElement} table - Table element
     * @param {string} message - Error message
     */
    showError(table, message) {
        const tbody = table.querySelector('tbody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:20px; color:#c00;">${message}</td></tr>`;
        }
    }
}

// Create global instance
window.csvLoader = new CSVLoader();
