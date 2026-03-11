'use strict';

const MONTHS = { 
    Jan:1, Feb:2, Mar:3, Apr:4, May:5, Jun:6, 
    Jul:7, Aug:8, Sep:9, Oct:10, Nov:11, Dec:12 
};

let lastCell = null;
let lastTime = 0;

// Modal controls
function openJobModal() {
    document.getElementById('jobModal').style.display = 'block';
}

function closeJobModal() {
    document.getElementById('jobModal').style.display = 'none';
}

// Close modal on outside click
window.onclick = function(e) {
    const modal = document.getElementById('jobModal');
    if (e.target === modal) modal.style.display = 'none';
}

// Generate Drawing Planning PDF with TCPDF
function generateDrawingPDF(mon, week, year, fromDate) {
    const url = `drawingPlanPDF.php?fromDate=${fromDate}&mon=${mon}&weekNo=${week}&yr=${year}`;
    window.open(url, '_blank');
    console.log('✓ Drawing PDF opened:', url);
}

// Generate Requirement Planning PDF (Drawing, Tinning, Bunching, Mica)
function generateRequirementPDF(mon, week, year, fromDate) {
    const url = `requirementPDF.php?fromDate=${fromDate}&mon=${mon}&weekNo=${week}&yr=${year}`;
    window.open(url, '_blank');
    console.log('✓ Requirement PDF opened:', url);
}

// Fetch job-wise data via AJAX
async function fetchJobWizeData(mon, week, year, fromDate) {
    try {
        openJobModal();
        const formData = new FormData();
        formData.append('fromDate', fromDate);
        formData.append('mon', mon);
        formData.append('weekNo', week);
        formData.append('yr', year);

        const res = await fetch('getJobWizeConductorPlanning.php', {
            method: 'POST',
            body: formData,
            cache: 'no-store'
        });

        const json = await res.json();

        if (!json.ok) throw new Error(json.error || 'Failed to fetch data');

        // Build table
        let html = `<table class="modal-table">
            <thead>
                <tr>
                    <th>Job No</th>
                    <th>No of Str</th>
                    <th>Str Dia</th>
                    <th>Is Mica</th>
                    <th>Cond Type</th>
                    <th>Mtr</th>
                    <th>Weight</th>
                </tr>
            </thead>
            <tbody>`;

        let totalMtr = 0;
        let totalWeight = 0;

        for (let row of json.data) {
            const mtr = parseFloat(row.Mtr) || 0;
            const weight = parseFloat(row.Weight) || 0;
            totalMtr += mtr;
            totalWeight += weight;

            html += `<tr>
                <td>${row.JobNo || '-'}</td>
                <td>${row.NoOfStr || '-'}</td>
                <td>${row.StrDia || '-'}</td>
                <td>${row.isMica || '-'}</td>
                <td>${row.CondTypeTag || '-'}</td>
                <td style="text-align:right; font-weight:600;">
                ${Math.round(mtr).toLocaleString('en-IN')}
                </td>
                <td style="text-align:right; font-weight:600;">
                ${Math.round(weight).toLocaleString('en-IN')}
                </td>
            </tr>`;
        }

        // Grand total row
        html += `<tr style="background-color:#E8F4F8; font-weight:bold;">
                <td colspan="5" style="text-align:right; padding-right:10px;">GRAND TOTAL</td>
                <td style="text-align:right; border-top:2px solid #2980B9;">${totalMtr.toFixed(2)}</td>
                <td style="text-align:right; border-top:2px solid #2980B9;">${totalWeight.toFixed(2)}</td>
            </tr>`;

        html += `</tbody></table>`;

        // Update modal
        document.getElementById('modalTitle').textContent = `Job-Wise Planning - ${json.period} (${json.count} records)`;
        document.getElementById('jobModalBody').innerHTML = html;
        console.log('✓ Job-wise data loaded:', json.count, 'records');

    } catch (e) {
        document.getElementById('jobModalBody').innerHTML = `<div style="color:red; padding:20px; font-weight:600;">Error: ${e.message}</div>`;
        console.error('✗ Error fetching data:', e.message);
    }
}

// Header click handler with double-click detection
$('#sheet').on('click', function(e) {
    const $cell = $(e.target).closest('th.jss_header[data-role="header"]');
    if (!$cell.length) return;

    let str = $cell.data('title').replace(/\s+/g, " ").trim();
    let parts = str.split(" ");
    let [monthYear, weekStr, metricType] = parts;
    let [mon, yr] = monthYear.split("-");

    const month = MONTHS[mon] || 0;
    const year = 2000 + parseInt(yr);
    const week = parseInt(weekStr.replace("W", "")) || 0;
    const fromDate = $('#fromDate').val();

    const now = Date.now();
    const isSameCell = lastCell && lastCell[0] === $cell[0];
    const isDoubleClick = isSameCell && (now - lastTime < 400);

    if (isDoubleClick) {
        e.preventDefault();
        e.stopPropagation();

        switch(metricType) {
            case 'Mtr':
                fetchJobWizeData(month, week, year, fromDate);
                break;

            case 'Kgs':
                generateRequirementPDF(month, week, year, fromDate);
                break;    

            case 'Drawing':
                // generateDrawingPDF(month, week, year, fromDate);
                // break;
            
            case 'Tinning':
            case 'Bunching':
            case 'Mica':
                console.log(month, week, year, fromDate);
                break;
            
            default:
                console.log('Other column:', str);
        }
    }

    lastCell = $cell;
    lastTime = now;
});