'use strict';

let pendingJobsSheet = null;

function buildPendingColumns(headers) {
    return headers.map((header) => {
        const isNumeric = /No Of Str|Str Dia|Required Mtr|Prod Mtr|Balance Mtr|Calculated Weight/i.test(header);

        if (header === 'Job No') {
            return { title: header, type: 'text', width: 130 };
        }

        if (header === 'Is Mica' || header === 'Cond Type') {
            return { title: header, type: 'text', width: 90 };
        }

        if (header === 'No Of Str') {
            return { title: header, type: 'numeric', width: 90, mask: '#,##0' };
        }

        if (header === 'Str Dia') {
            return { title: header, type: 'numeric', width: 90, mask: '0.0000' };
        }

        if (isNumeric) {
            return { title: header, type: 'numeric', width: 150, mask: '#,##0' };
        }

        return { title: header, type: 'text', width: 150 };
    });
}

function setPendingStatus(message) {
    const status = document.getElementById('status');
    if (status) {
        status.textContent = message;
    }
}

async function loadPendingJobs() {
    setPendingStatus('Loading...');

    try {
        const response = await fetch('fetch_copper_pending_jobs.php', {
            cache: 'no-store'
        });
        const result = await response.json();

        if (!result.ok) {
            throw new Error(result.error || 'Unable to load pending jobs.');
        }

        const headers = Array.isArray(result.headers) ? result.headers : [];
        const rows = Array.isArray(result.rows) ? result.rows : [];
        const columns = buildPendingColumns(headers);
        const viewportWidth = Math.max(Math.floor(window.innerWidth * 0.88), 900);

        if (pendingJobsSheet) {
            jspreadsheet.destroy(document.getElementById('pendingSheet'));
            pendingJobsSheet = null;
        }

        pendingJobsSheet = jspreadsheet(document.getElementById('pendingSheet'), {
            worksheets: [{
                data: rows,
                columns: columns,
                tableWidth: viewportWidth + 'px',
                tableHeight: '700px',
                tableOverflow: true,
                freezeColumns: 2,
                columnSorting: true,
                filters: true
            }],
            includeHeadersOnDownload: true
        });

        setPendingStatus(`Loaded ${rows.length} pending jobs. 95%+ completed jobs hidden.`);
    } catch (error) {
        console.error(error);
        setPendingStatus('Error: ' + error.message);
    }
}

window.loadPendingJobs = loadPendingJobs;

loadPendingJobs();
