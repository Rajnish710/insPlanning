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
   <style type="text/css">
   	 .jss > thead > tr > th {
        font-size: 16px !important;
        text-align: center !important;
        font-family: 'Times New Roman' !important;
        white-space: pre-line;
        background-color: #bb76df !important;
    }
    /* #sheet tr:nth-child(even) td{
            background-color: #edf3ff;
        } */
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

/* Left Section */
.toolbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Label */
#toolbar label {
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

/* Date Input */
#toolbar input[type="date"] {
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid #ccc;
  font-size: 14px;
  outline: none;
  transition: border 0.2s ease;
}

#toolbar input[type="date"]:focus {
  border-color: #007bff;
}

/* Button */
#toolbar button {
  padding: 6px 14px;
  border-radius: 6px;
  border: none;
  background: #007bff;
  color: #fff;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s ease, transform 0.1s ease;
}

#toolbar button:hover {
  background: #0056b3;
}

#toolbar button:active {
  transform: scale(0.97);
}

/* Status Text */
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

/* Modal Styling */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-content {
  background-color: #fefefe;
  margin: 5% auto;
  padding: 0;
  border: 1px solid #888;
  width: 90%;
  max-width: 1000px;
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
  transition: transform 0.2s;
}

.close-btn:hover {
  transform: scale(1.2);
}

.modal-body {
  padding: 0 20px 20px 20px;
  overflow-y: auto;
  flex: 1;
}

.modal-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  margin: 0;
}

.modal-table thead {
  /* background-color: #e8e8e8; */
  position: sticky;
  top: 0;
}

.modal-table thead th {
  padding: 10px;
  text-align: left;
  border: 1px solid #ddd;
  font-weight: 600;
  background-color: #bb76df;
  color: white;
}

.modal-table tbody td {
  padding: 10px;
  border: 1px solid #ddd;
}

.modal-table tbody tr:nth-child(even) {
  background-color: #f9f9f9;
}

.modal-table tbody tr:hover {
  background-color: #f0f0f0;
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
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: background-color 0.2s;
}

.modal-footer button:hover {
  background-color: #0056b3;
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
    <input type="date" id="fromDate" value="2026-03-15" />
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
        <button onclick="closeJobModal()">Close</button>
      </div>
    </div>
  </div>

 <script src="script.js"></script> 
<script>
$('#copperPlan').addClass('active');
  let spreadsheet = null;

  // Header ko 2-line title me convert karega:
  // Feb-26_W4_mtr      => "Feb-26 W4\nMtr"
  // Feb-26_W4_Drawing  => "Feb-26 W4\nDrawing"
  // etc.
  function makePrettyTitle(h) {
    if (h === 'isMica') return 'Is Mica';
    if (h === 'CondTypeTag') return 'Cond Type';
    if (h === 'NoOfStr') return 'Noof Str';
    if (h === 'StrDia') return 'Str Dia';

    const m = h.match(/^(.*)_(mtr|Drawing|Tinning|Bunching|Mica)$/i);
    if (m) {
      const period = (m[1] || '').replace(/_/g, ' '); // Feb-26_W4 -> Feb-26 W4
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

      // fixed base cols
      if (h === 'isMica') {
        return { title, type: 'numeric', width: '60px', mask: '0' };
      }
      if (h === 'CondTypeTag') {
        return { title, type: 'text', width: '80px' };
      }
      if (h === 'NoOfStr') {
        return { title, type: 'numeric', width: '60px', mask: '#,##0' };
      }
      if (h === 'StrDia') {
        return { title, type: 'numeric', width: '70px', mask: '#,##0.0000' };
      }
      if (/_mtr$/i.test(h)) {
        return { title, type: 'numeric', width: '90px', mask: '#,##0' };
      }
      if (/_Kgs$/i.test(h)) {
        return { title, type: 'numeric', width: '75px', mask: '#,##0' };
      }
      if (/_(Drawing|Tinning|Bunching|Mica)$/i.test(h)) {
        return { title, type: 'numeric', width: '80px', readOnly: true, filter: false };
      }
      // default
      return { title, type: 'text', width: '120px' };
    });
  }

function computeFooters(headers, rows) {
    const footerRow = new Array(headers.length).fill('');

    // optional label
    if (headers.length > 0) footerRow[0] = 'Total %';

    for (let c = 0; c < headers.length; c++) {
      const h = headers[c];

      if (/_(Drawing|Tinning|Bunching|Mica)$/i.test(h)) {
        let sum = 0;

        for (let r = 0; r < rows.length; r++) {
          const v = rows[r][c];

          // safe parse (handles "", null, numbers, numeric strings)
          const num = (v === null || v === undefined || v === '')
            ? 0
            : parseFloat(String(v).replace(/,/g, ''));

          if (!Number.isNaN(num)) sum += num;
        }

        footerRow[c] = (sum * 100).toFixed(1) + '%';
      }
    }

    return [footerRow]; // footers expects array of rows
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

      const headers = json.headers;
      const rows = json.rows;

      const columns = buildColumns(headers);

      // ✅ Build footer based on loaded data
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