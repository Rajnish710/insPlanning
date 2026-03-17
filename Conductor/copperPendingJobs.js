'use strict';

let pendingJobsSheet = null;
let isWeekSelectReady = false;

function buildPendingColumns(headers) {
    return headers.map((header) => {
        const isNumeric = /No Of Str|Str Dia|Required Mtr|Prod Mtr|Balance Mtr|Calculated Weight/i.test(header);

        if (header === 'Job No') {
            return { title: header, type: 'text', width: 130, align: 'left' };
        }

        if (header === 'Is Mica' || header === 'Cond Type') {
            return { title: header, type: 'text', width: 90 };
        }

        if (header === 'No Of Str') {
            return { title: header, type: 'numeric', width: 80, mask: '#,##0' };
        }

        if (header === 'Str Dia') {
            return { title: header, type: 'numeric', width: 80, mask: '0.0000' };
        }

        if (header === 'insuStartDate') {
            return { title: header, type: 'text', width: 110 };
        }

        if (isNumeric) {
            return { title: header, type: 'numeric', width: 120, mask: '#,##0' };
        }

        return { title: header, type: 'text', width: 100 };
    });
}

function computePendingFooters(headers, rows) {
    const footerRow = new Array(headers.length).fill('');
    const calculatedWeightIndex = headers.indexOf('Calculated Weight');

    if (headers.length > 0) {
        footerRow[0] = 'Total';
    }

    if (calculatedWeightIndex !== -1) {
        let totalCalculatedWeight = 0;

        for (const row of rows) {
            const value = row[calculatedWeightIndex];
            const num = value === null || value === undefined || value === ''
                ? 0
                : parseFloat(String(value).replace(/,/g, ''));

            if (!Number.isNaN(num)) {
                totalCalculatedWeight += num;
            }
        }

        footerRow[calculatedWeightIndex] = Math.round(totalCalculatedWeight).toLocaleString('en-IN');
    }

    return [footerRow];
}

function getSelectedWeekParams() {
    const weekSelect = document.getElementById('weekSelect');
    if (!weekSelect || !weekSelect.value) {
        return null;
    }

    if (weekSelect.value === 'ALL') {
        return { week: 'ALL' };
    }

    const [yr, mon, weekNo] = weekSelect.value.split('|').map(Number);
    if (!yr || !mon || !weekNo) {
        return null;
    }

    return { yr, mon, weekNo };
}

function syncWeekSelect(weeks, selectedWeek) {
    const weekSelect = document.getElementById('weekSelect');
    if (!weekSelect) {
        return;
    }

    const sortedWeeks = [...weeks].sort((a, b) => {
        const aKey = Number(a.sortKey || ((a.yr * 100 + a.mon) * 10 + a.weekNo));
        const bKey = Number(b.sortKey || ((b.yr * 100 + b.mon) * 10 + b.weekNo));
        return aKey - bKey;
    });

    const selectedValue = selectedWeek
        ? `${selectedWeek.yr}|${selectedWeek.mon}|${selectedWeek.weekNo}`
        : 'ALL';

    weekSelect.innerHTML = [
        `<option value="ALL"${selectedValue === 'ALL' ? ' selected' : ''}>All</option>`,
        ...sortedWeeks.map((week) => {
            const value = `${week.yr}|${week.mon}|${week.weekNo}`;
            const selectedAttr = value === selectedValue ? ' selected' : '';
            return `<option value="${value}"${selectedAttr}>${week.label}</option>`;
        })
    ].join('');

    if (!isWeekSelectReady) {
        weekSelect.addEventListener('change', () => {
            loadPendingJobs();
        });
        isWeekSelectReady = true;
    }
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
        const params = new URLSearchParams();
        const selectedWeek = getSelectedWeekParams();

        if (selectedWeek) {
            if (selectedWeek.week === 'ALL') {
                params.set('week', 'ALL');
            } else {
                params.set('yr', selectedWeek.yr);
                params.set('mon', selectedWeek.mon);
                params.set('weekNo', selectedWeek.weekNo);
            }
        }

        const queryString = params.toString();
        const response = await fetch(`fetch_copper_pending_jobs.php${queryString ? `?${queryString}` : ''}`, {
            cache: 'no-store'
        });
        const rawText = await response.text();
        let result;

        try {
            result = JSON.parse(rawText);
        } catch (parseError) {
            const preview = rawText.slice(0, 200).trim();
            throw new Error(`Server returned invalid JSON: ${preview}`);
        }

        if (!result.ok) {
            throw new Error(result.error || 'Unable to load pending jobs.');
        }

        const headers = Array.isArray(result.headers) ? result.headers : [];
        const rows = Array.isArray(result.rows) ? result.rows : [];
        const weeks = Array.isArray(result.weeks) ? result.weeks : [];
        const columns = buildPendingColumns(headers);
        const footers = computePendingFooters(headers, rows);
        const viewportWidth = Math.max(Math.floor(window.innerWidth * 0.88), 900);

        syncWeekSelect(weeks, result.selectedWeek);

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
                filters: true,
                footers: footers
            }],
            includeHeadersOnDownload: true
        });

        const periodText = result.period ? ` ${result.period}` : '';
        setPendingStatus(`Loaded ${rows.length} pending jobs for${periodText}.`);
    } catch (error) {
        console.error(error);
        setPendingStatus('Error: ' + error.message);
    }
}

window.loadPendingJobs = loadPendingJobs;

loadPendingJobs();
