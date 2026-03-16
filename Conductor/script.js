'use strict';

const MONTHS = { 
    Jan:1, Feb:2, Mar:3, Apr:4, May:5, Jun:6, 
    Jul:7, Aug:8, Sep:9, Oct:10, Nov:11, Dec:12 
};

let lastCell = null;
let lastTime = 0;
let lastJobWizeData = null; // Store data for export

// Modal controls
function openJobModal() {
    document.getElementById('jobModal').style.display = 'block';
}

function closeJobModal() {
    document.getElementById('jobModal').style.display = 'none';
}

function showJobModalLoading() {
    const modalBody = document.getElementById('jobModalBody');
    if (modalBody) {
        modalBody.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading...</p>
            </div>
        `;
    }
    openJobModal();
}

// Close modal on outside click
window.onclick = function(e) {
    const modal = document.getElementById('jobModal');
    if (e.target === modal) modal.style.display = 'none';
}

// Export modal table to Excel
function exportModalToExcel() {
    if (typeof XLSX === 'undefined') {
        alert('Excel library not loaded. Please refresh the page.');
        return;
    }

    if (!lastJobWizeData || lastJobWizeData.length === 0) {
        alert('No data to export');
        return;
    }

    try {
        // Mtr columns removed as requested
        const headers = [
            'Job No',
            'No of Str',
            'Str Dia',
            'Is Mica',
            'Cond Type',
            'Required Weight',
            'Prod Allocated Weight',
            'Balance Weight',
            'Status'
        ];

        const rows = lastJobWizeData.map(row => [
            row.JobNo || '-',
            row.NoOfStr || '-',
            Number.isFinite(Number(row.StrDia)) ? Number(row.StrDia).toFixed(4) : '-',
            Number(row.isMica) === 1 ? 'Yes' : 'No',
            row.CondTypeTag || '-',
            parseFloat(row.RequiredWeight) || 0,
            parseFloat(row.ProdAllocatedWeight) || 0,
            parseFloat(row.BalanceWeight) || 0,
            row.ProdStatus || '-'
        ]);

        const totalRow = ['GRAND TOTAL', '', '', '', ''];
        let totalRequiredWeight = 0;
        let totalAllocatedWeight = 0;
        let totalBalanceWeight = 0;

        lastJobWizeData.forEach(row => {
            totalRequiredWeight += parseFloat(row.RequiredWeight) || 0;
            totalAllocatedWeight += parseFloat(row.ProdAllocatedWeight) || 0;
            totalBalanceWeight += parseFloat(row.BalanceWeight) || 0;
        });

        totalRow.push(
            totalRequiredWeight.toFixed(2),
            totalAllocatedWeight.toFixed(2),
            totalBalanceWeight.toFixed(2),
            ''
        );

        const sheetData = [headers, ...rows, totalRow];
        const ws = XLSX.utils.aoa_to_sheet(sheetData);

        ws['!rows'] = [{ hpx: 25, level: 0 }];
        ws['!cols'] = [
            { wch: 15 },
            { wch: 10 },
            { wch: 12 },
            { wch: 10 },
            { wch: 12 },
            { wch: 18 },
            { wch: 20 },
            { wch: 18 },
            { wch: 12 }
        ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Job-Wise Planning');

        const fileName = `JobWisePlanning_${new Date().toISOString().split('T')[0]}.xlsx`;
        XLSX.writeFile(wb, fileName);

        // alert('✓ Data exported successfully to ' + fileName);
    } catch (e) {
        console.error('Export error:', e);
        alert('Error exporting: ' + e.message);
    }
}

// Fetch job-wise data via AJAX
async function fetchJobWizeData(month, week, year, fromDate) {
    showJobModalLoading();

    try {
        // FIX: remove extra "Conductor/" from path
        const response = await fetch('getJobWizeConductorPlanning.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                mon: month,
                weekNo: week,
                yr: year,
                fromDate: fromDate
            })
        });

        const res = await response.json();

        if (!res.ok) {
            throw new Error(res.error || 'Job-wise data load failed.');
        }

        lastJobWizeData = Array.isArray(res.data) ? res.data : [];
        renderJobWizeTable(lastJobWizeData, month, week, year);
    } catch (err) {
        console.error('fetchJobWizeData error:', err);
        const modalBody = document.getElementById('jobModalBody');
        if (modalBody) {
            modalBody.innerHTML = `
                <div class="text-center" style="padding:20px; color:#c00;">
                    Unable to load job-wise planning data.<br>
                    ${escapeHtml(err.message || '')}
                </div>
            `;
        }
        alert('Unable to load job-wise planning data.');
    }
}

function renderJobWizeTable(rows, month, week, year) {
    const modalBody = document.getElementById('jobModalBody');
    const modalTitle = document.getElementById('modalTitle');
    if (!modalBody) return;

    const monthName = Object.keys(MONTHS).find(key => MONTHS[key] === Number(month)) || month;
    if (modalTitle) {
        modalTitle.textContent = `Job-Wise Planning Details - ${monthName}-${String(year).slice(-2)} W${week}`;
    }

    // Create table dynamically inside modal body
    modalBody.innerHTML = `
        <table id="jobWizeModalTable" class="modal-table">
            <thead>
                <tr>
                    <th>Job No</th>
                    <th>No Of Str</th>
                    <th>Str Dia</th>
                    <th>Is Mica</th>
                    <th>Cond Type</th>
                    <th>Required Weight</th>
                    <th>Prod Allocated Weight</th>
                    <th>Balance Weight</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    `;

    const tbody = modalBody.querySelector('tbody');
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center">No data found</td>
            </tr>
        `;
        openJobModal();
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
            <td>${escapeHtml(r.JobNo ?? '')}</td>
            <td class="text-end">${formatNumber(r.NoOfStr, 0)}</td>
            <td class="text-end">${formatStrDia(r.StrDia)}</td>
            <td class="text-center">${Number(r.isMica) === 1 ? 'Yes' : '--'}</td>
            <td>${escapeHtml(r.CondTypeTag ?? '')}</td>
            <td class="text-end">${formatNumber(r.RequiredWeight, 0)}</td>
            <td class="text-end">${formatNumber(r.ProdAllocatedWeight, 0)}</td>
            <td class="text-end">${formatNumber(r.BalanceWeight, 0)}</td>
            <td>${escapeHtml(r.ProdStatus ?? '')}</td>
        </tr>
    `).join('');

    openJobModal();
}

function formatNumber(value, digits = 2) {
    const num = Number(value || 0);
    return num.toLocaleString('en-IN', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
    });
}

function formatStrDia(value) {
    const num = Number(value);
    return Number.isFinite(num) ? num.toFixed(4) : '0.0000';
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Generate PDFs
function generateDrawingPDF(mon, week, year, fromDate) {
    const url = `drawingPlanPDF.php?fromDate=${fromDate}&mon=${mon}&weekNo=${week}&yr=${year}`;
    window.open(url, '_blank');
}

function generateRequirementPDF(mon, week, year, fromDate) {
    const url = `requirementPDF.php?fromDate=${fromDate}&mon=${mon}&weekNo=${week}&yr=${year}`;
    window.open(url, '_blank');
}

// Header click handler with double-click detection
$('#sheet').on('click', function(e) {
    const $cell = $(e.target).closest('th.jss_header[data-role="header"]');
    if (!$cell.length) return;

    const rawTitle = String($cell.attr('data-title') || $cell.data('title') || $cell.text() || '')
        .replace(/\s+/g, ' ')
        .trim();

    if (!rawTitle) return;

    const titleParts = rawTitle.split(' ');
    if (titleParts.length < 3) return;

    const metricType = (titleParts.pop() || '').toLowerCase();
    const weekStr = titleParts.pop() || '';
    const monthYear = titleParts.join(' ');
    const [mon, yr] = monthYear.split('-');

    const month = MONTHS[mon] || 0;
    const year = 2000 + parseInt(yr, 10);
    const week = parseInt(weekStr.replace('W', ''), 10) || 0;
    const fromDate = $('#fromDate').val();

    const now = Date.now();
    const isSameCell = lastCell && lastCell[0] === $cell[0];
    const isDoubleClick = isSameCell && (now - lastTime < 400);

    if (isDoubleClick) {
        e.preventDefault();
        e.stopPropagation();

        switch (metricType) {
            case 'mtr':
                fetchJobWizeData(month, week, year, fromDate);
                break;

            case 'kgs':
                generateRequirementPDF(month, week, year, fromDate);
                break;

            case 'drawing':
            case 'tinning':
            case 'bunching':
            case 'mica':
                console.log(month, week, year, fromDate);
                break;

            default:
                console.log('Other column:', rawTitle);
        }
    }

    lastCell = $cell;
    lastTime = now;
});