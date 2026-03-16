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
  </style>
</head>
<body>
<div id="toolbar">
  <div class="toolbar-left">
    <button type="button" onclick="loadPendingJobs()">Reload</button>
  </div>
  <div class="toolbar-right">
    <span id="status"></span>
  </div>
</div>

<div id="pendingSheet"></div>
<script src="copperPendingJobs.js"></script>
</body>
</html>
