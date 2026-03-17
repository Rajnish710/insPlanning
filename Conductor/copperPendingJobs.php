<?php
include('../key.php');
$title = "Copper Pending Jobs";
include('../includes/header.php');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Copper Pending Jobs</title>
  <style type="text/css">
    .jss > thead > tr > th {
      font-size: 16px !important;
      text-align: center !important;
      font-family: 'Times New Roman' !important;
      white-space: pre-line;
      background-color: #bb76df !important;
    }

    td {
      font-size: 13px !important;
    }

    .jss tfoot td,
    .jss_footer {
      background-color: #c5e935 !important;
      font-weight: 600;
      color: #333 !important;
      font-family: monospace !important;
    }

    #toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 18px;
      margin-bottom: 15px;
      background: #f8f9fb;
      border: 1px solid #e2e6ea;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    #toolbar label {
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #44556b;
    }

    #weekSelect {
      min-width: 180px;
      padding: 8px 38px 8px 12px;
      border: 1px solid #b8c7d9;
      border-radius: 8px;
      background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
      color: #18324a;
      font-size: 14px;
      font-weight: 600;
      outline: none;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 1px 2px rgba(24, 50, 74, 0.08);
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      background-image:
        linear-gradient(45deg, transparent 50%, #355c7d 50%),
        linear-gradient(135deg, #355c7d 50%, transparent 50%),
        linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
      background-position:
        calc(100% - 18px) calc(50% - 3px),
        calc(100% - 12px) calc(50% - 3px),
        0 0;
      background-size: 6px 6px, 6px 6px, 100% 100%;
      background-repeat: no-repeat;
    }

    #weekSelect:hover {
      border-color: #7aa5cc;
    }

    #weekSelect:focus {
      border-color: #007bff;
      box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
    }

    #toolbar button {
      padding: 8px 16px;
      border-radius: 6px;
      border: none;
      background: linear-gradient(180deg, #1580ff 0%, #005dc1 100%);
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 2px 4px rgba(0, 93, 193, 0.2);
    }

    #toolbar button:hover {
      background: linear-gradient(180deg, #0f73e6 0%, #004f9f 100%);
    }

    #status {
      font-size: 13px;
      font-weight: 500;
      color: #555;
      text-align: right;
    }
  </style>
</head>
<body>
<div id="toolbar">
  <div class="toolbar-left">
    <label for="weekSelect">Week</label>
    <select id="weekSelect"></select>
    <button type="button" onclick="loadPendingJobs()">Reload</button>
  </div>
  <div class="toolbar-right">
    <span id="status"></span>
  </div>
</div>

<div id="pendingSheet"></div>
<script src="copperPendingJobs.js?v=<?php echo rawurlencode((string)filemtime(__DIR__ . '/copperPendingJobs.js')); ?>"></script>
</body>
</html>
