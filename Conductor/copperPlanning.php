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
    #spreadsheet tr:nth-child(even) td{
            background-color: #edf3ff;
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
   </style> 
</head>
<body>
<div id="toolbar">
  <div class="toolbar-left">
    <label for="fromDate">From Date</label>
    <input type="date" id="fromDate" value="2026-03-01" />
    <button onclick="loadReport()">Load</button>
  </div>

  <div class="toolbar-right">
    <span id="status"></span>
  </div>
</div>

  <div id="sheet"></div>

  <script>
    let spreadsheet = null;
    function buildColumns(headers) {
      return headers.map(h => {
        // basic column typing
        if (h === 'isMica') {
          return { title: 'Is Mica', type: 'text', width: '60px' };
        }
        if (h === 'CondTypeTag') {
          return { title: 'Cond Type', type: 'text', width: '80px' };
        }
        if (h === 'NoOfStr') {
          return { title: 'Noof Str', type: 'numeric', width: '60px', mask: '#,##0' };
        }
        if (h === 'StrDia') {
          return { title: 'Str Dia', type: 'numeric', width: '70px', mask: '#,##0.000' };
        }
        if (h.toLowerCase().endsWith('_mtr')) {
            h = h.replace('_', ' ');
          return { title: h, type: 'numeric', width: '85px', mask: '#,##0' };
        }
        return { title: h, type: 'text', width: '120px' };
      });
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

        if (!json.ok) {
          throw new Error(json.error || 'Unknown error');
        }

        const headers = json.headers;
        const rows = json.rows;

        const columns = buildColumns(headers);

        // Destroy previous instance
        if (spreadsheet) {
          jspreadsheet.destroy(document.getElementById('sheet'));
          spreadsheet = null;
        }

        spreadsheet = jspreadsheet(document.getElementById('sheet'), {
        worksheets: [{   
          data: rows,
          tableWidth: w*0.86+'px',
          tableHeight: '700px',
          tableOverflow: true,
          columns: columns,
          freezeColumns: 4,   
          columnSorting: false,
          filters: true,
          }],
        includeHeadersOnDownload: true,
        });

        status.textContent = `Loaded: ${rows.length} rows, ${headers.length} columns`;
      } catch (e) {
        status.textContent = 'Error: ' + e.message;
        console.error(e);
      }
    }

    // Auto load on page open
    loadReport();
  </script>
</body>
</html>