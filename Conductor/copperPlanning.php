<?php
include('../key.php');
$title = "Copper Planning";
include '../includes/header.php';
include '../includes/dbcon45.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Copper Planning</title>
  <!-- Load XLSX library FIRST -->
  <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
  
  <style type="text/css">
        .jss > thead > tr > th {
        font-size: 16px !important;
        text-align: center !important;
        font-family: 'Times New Roman' !important;
        white-space: pre-line;
        background-color: #bb76df !important;
    }
    td{
        font-size: 13px !important;
    }
    
    /* ===== Toolbar Container ===== */
#toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 18px;
  margin-bottom: 15px;
  background: #f8f9fb;
  border: 1px solid #e2e6ea;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

#toolbar label {
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

#toolbar input[type="date"] {
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid #ccc;
  font-size: 14px;
  outline: none;
}

#toolbar input[type="date"]:focus {
  border-color: #007bff;
}

#toolbar button {
  padding: 6px 14px;
  border-radius: 6px;
  border: none;
  background: #007bff;
  color: #fff;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}

#toolbar button:hover {
  background: #0056b3;
}

#status {
  font-size: 13px;
  font-weight: 500;
  color: #555;
}

.jss_footer{
    background-color: #c5e935 !important;
    font-weight: 600;
    color: #333 !important; 
    font-family: monospace !important;
}

.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  background-color: #fefefe;
  margin: 5% auto;
  padding: 0;
  border: 1px solid #888;
  width: 90%;
  max-width: 1200px;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
  max-height: 80vh;
  display: flex;
  flex-direction: column;
}

.modal-header {
  padding: 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-radius: 8px 8px 0 0;
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
}

.close-btn {
  color: white;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
}

.close-btn:hover {
  transform: scale(1.2);
}

.modal-body {
  padding: 20px;
  overflow: auto;
  flex: 1;
}

.modal-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 13px;
  border: 1px solid #ddd;
}

.modal-table thead {
  position: sticky;
  top: 0;
  z-index: 2;
}

.modal-table thead th {
  padding: 12px;
  text-align: center;
  font-weight: 600;
  background-color: #bb76df !important;
  color: white;
  border-top: 1px solid #8b58a7;
  border-bottom: 1px solid #8b58a7;
  border-right: 1px solid #8b58a7;
}

.modal-table thead th:first-child {
  border-left: 1px solid #8b58a7;
}

.modal-table tbody td {
  padding: 10px;
  border-right: 1px solid #ddd;
  border-bottom: 1px solid #ddd;
  background: #fff;
}

.modal-table tbody td:first-child {
  border-left: 1px solid #ddd;
}

.modal-table tbody tr:hover td {
  background-color: #f5f5f5;
}

.modal-footer {
  padding: 15px 20px;
  background-color: #f5f5f5;
  text-align: right;
  border-top: 1px solid #ddd;
  border-radius: 0 0 8px 8px;
}

.modal-footer button {
  padding: 8px 16px;
  margin-left: 10px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
}

.modal-footer button:hover {
  background-color: #0056b3;
}

.modal-footer .btn-export {
  background-color: #28a745;
}

.modal-footer .btn-export:hover {
  background-color: #218838;
}

.loading-spinner {
  text-align: center;
  padding: 30px;
}

.spinner {
  border: 4px solid #f3f3f3;
  border-top: 4px solid #bb76df;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
   </style> 
</head>
<body>
<div id="toolbar">
  <div class="toolbar-left">
    <label for="fromDate">From Date</label>
    <input type="date" id="fromDate" value="<?php echo date('Y-m-d'); ?>" />
    <button onclick="loadReport()">Load</button>
  </div>
  <div class="toolbar-right">
    <span id="status"></span>
  </div>
</div>

<div id="sheet"></div>

<!-- Modal for Job-Wise Data -->
<div id="jobModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="modalTitle">Job-Wise Planning Details</h2>
      <button class="close-btn" onclick="closeJobModal()">&times;</button>
    </div>
    <div class="modal-body" id="jobModalBody">
      <div class="loading-spinner">
        <div class="spinner"></div>
        <p>Loading...</p>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-export" onclick="exportModalToExcel()">📥 Export to Excel</button>
      <button onclick="closeJobModal()">Close</button>
    </div>
  </div>
</div>

<!-- Load script.js AFTER XLSX library -->
<script src="script.js"></script> 

<script>
$('#copperPlan').addClass('active');
let spreadsheet = null;
let lastLoadedHeaders = [];
let lastLoadedRows = [];

function makePrettyTitle(h) {
  if (h === 'isMica') return 'Is Mica';
  if (h === 'CondTypeTag') return 'Cond Type';
  if (h === 'Total_Kgs') return 'Total\nKgs';
  if (h === 'NoOfStr') return 'No of Str';
  if (h === 'StrDia') return 'Str Dia';

  const m = h.match(/^(.*)_(mtr|Drawing|Tinning|Bunching|Mica)$/i);
  if (m) {
    const period = (m[1] || '').replace(/_/g, ' ');
    let metric = m[2] || '';
    if (metric.toLowerCase() === 'mtr') metric = 'Mtr';
    else metric = metric.charAt(0).toUpperCase() + metric.slice(1).toLowerCase();
    return period + '\n' + metric;
  }
  return String(h).replace(/_/g, ' ');
}

function buildColumns(headers) {
  return headers.map((h) => {
    const title = makePrettyTitle(h);

    if (h === 'isMica') {
      return { title, type: 'numeric', width: '60px', mask: '0' };
    }
    if (h === 'CondTypeTag') {
      return { title, type: 'text', width: '80px' };
    }
    if (h === 'NoOfStr') {
      return { title, type: 'numeric', width: '80px', mask: '#,##0', align: 'center' };
    }
    if (h === 'StrDia') {
      return { title, type: 'numeric', width: '100px', mask: '0.0000', align: 'center' };
    }
    if (/_mtr$/i.test(h)) {
      return { title, type: 'numeric', width: '100px', mask: '#,##0', hidden: true };
    }
    if (/_Kgs$/i.test(h)) {
      return { title, type: 'numeric', width: '100px', mask: '#,##0' };
    }
    if (/_(Drawing|Tinning|Bunching|Mica)$/i.test(h)) {
      return { title, type: 'numeric', width: '100px', readOnly: true, filter: false };
    }
    return { title, type: 'text', width: '120px' };
  });
}

function appendTotalKgsColumn(headers, rows) {
  const condIdx = headers.indexOf('CondTypeTag');
  if (condIdx === -1) return { headers, rows };

  const insertAt = condIdx + 1;
  const kgsIndexes = [];

  for (let i = 0; i < headers.length; i++) {
    if (/_Kgs$/i.test(headers[i])) kgsIndexes.push(i);
  }

  const newHeaders = headers.slice();
  newHeaders.splice(insertAt, 0, 'Total_Kgs');

  const newRows = rows.map((r) => {
    let total = 0;

    for (let i = 0; i < kgsIndexes.length; i++) {
      const v = r[kgsIndexes[i]];
      const num = (v === null || v === undefined || v === '')
        ? 0
        : parseFloat(String(v).replace(/,/g, ''));
      if (!Number.isNaN(num)) total += num;
    }

    const nr = r.slice();
    nr.splice(insertAt, 0, total);
    return nr;
  });

  return { headers: newHeaders, rows: newRows };
}

function computeFooters(headers, rows) {
  const footer = new Array(headers.length).fill('');

  if (headers.length > 0) {
    footer[0] = 'Total';
  }

  const parseNumber = (value) => {
    if (value === null || value === undefined || value === '') return 0;

    const num = parseFloat(String(value).replace(/,/g, ''));
    return Number.isNaN(num) ? 0 : num;
  };

  const sumColumn = (colIndex) => {
    let sum = 0;
    for (const row of rows) {
      sum += parseNumber(row[colIndex]);
    }
    return sum;
  };

  for (let c = 0; c < headers.length; c++) {
    const header = headers[c];
    const sum = sumColumn(c);

    if (/_Kgs$/i.test(header)) {
      footer[c] = Math.round(sum).toLocaleString('en-IN');
    } else if (/_(Drawing|Tinning|Bunching|Mica)$/i.test(header)) {
      footer[c] = `${(sum * 100).toFixed(1)}%`;
    }
  }

  return [footer];
}

async function loadReport() {
  const from = document.getElementById('fromDate').value || '2026-03-01';
  const status = document.getElementById('status');
  status.textContent = 'Loading...';
  var w = $(window).width();

  try {
    const res = await fetch(`fetch_copper_planning.php?from=${encodeURIComponent(from)}`, {
      cache: 'no-store'
    });
    const json = await res.json();

    if (!json.ok) throw new Error(json.error || 'Unknown error');

    const transformed = appendTotalKgsColumn(json.headers, json.rows);
    const headers = transformed.headers;
    const rows = transformed.rows;
    
    lastLoadedHeaders = headers;
    lastLoadedRows = rows;
    
    console.log('Data loaded:', headers);

    const columns = buildColumns(headers);
    const footers = computeFooters(headers, rows);

    if (spreadsheet) {
      jspreadsheet.destroy(document.getElementById('sheet'));
      spreadsheet = null;
    }

    spreadsheet = jspreadsheet(document.getElementById('sheet'), {
      worksheets: [{
        data: rows,
        tableWidth: (w * 0.86) + 'px',
        tableHeight: '700px',
        tableOverflow: true,
        columns: columns,
        freezeColumns: 4,
        columnSorting: false,
        filters: true,
        footers: footers,
      }],
      includeHeadersOnDownload: true,
    });

    status.textContent = `Loaded: ${rows.length} rows, ${headers.length} columns`;
  } catch (e) {
    status.textContent = 'Error: ' + e.message;
    console.error(e);
  }
}

loadReport();
</script>
</body>
</html>