<?php
include('C:\xampp\htdocs\Inquiry\costing\js\jspreadsheetKey.php');
$title = "Copper Planning";
include '../includes/header.php';
include '../includes/dbcon45.php';
?>
<script type="text/javascript">

</script>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- <title>Instru Cable</title> -->
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css">
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
    td.readonly{
            color: #212529a6 !important;
        }
    
   </style> 
</head>
<body>
    <!-- <div class="row mx-2 mb-2">
        <select id="selectJobNo" multiple="multiple" style="width: 70%;"></select>
        <button type="button" class="btn btn-primary mx-1" id="GetJobList" style="width: 10%;">GetJob</button>
        <button class="btn btn-success mx-1" id="saveForPlanning" style="width: 10%;">Save</button> 
   </div> -->
   <div class="row cblePlan mx-2">
        <div class="entry container-fluid" id="spreadsheet">
            Please Wait..........
        </div>
    </div><br>
</body>
</html>
<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script type="text/javascript">

$('#copperPlan').addClass('active');
// function to get Data
$(document).ready(function(){
var w = $(window).width();
$.ajax({
type: "POST",
url: "getCuData.php",
dataType: "json",
// data:{jobs:jobs},
success: function (response) {
    // console.log(response);
table = jspreadsheet(document.getElementById('spreadsheet'), {   
worksheets: [{
data:response,
tableWidth: w*0.8+'px',
tableHeight: '600px',
tableOverflow: true,
// freezeColumns: 4,
columns: [
 { type: 'text', name: 'MonthName',title: 'Month', width:'110px',align:'left' }, //A
{ type: 'text', name: 'WeekNo',title: 'WeekNo', width:'80px' }, //C
{ type: 'calendar', name: 'WeekStart',title: 'WeekStart', width:'120px', options: {  format: 'DD-MMM-YYYY' } },
{ type: 'calendar', name: 'WeekEnd',title: 'WeekEnd', width:'120px', options: {  format: 'DD-MMM-YYYY' } },
{ type: 'text', name: 'NoOfStr',title: 'NoOf Strand', width:'80px' }, //D
{ type: 'text', name: 'StrDia',title: 'Strand Dia', width:'70px',mask:'0.000' }, //E
{ type: 'text', name: 'isMica',title: 'isMica', width:'70px' }, //F
{ type: 'text', name: 'CondTypeTag',title: 'Cond Type', width:'70px',align:'left' }, //G
{ type: 'text', name: 'TotalMtr',title: 'TotalMtr', width:'100px',mask:'0' }, //H
{ type: 'text', name: 'Kgs',title: 'Kgs', width:'80px',mask:'#,##0' }, //H

],
// columnSorting:false,
filters:true,
}],
// toolbar:true,
includeHeadersOnDownload: true,
// onchange: changed,
});
},
complete:function(data){
    console.log('Done');
},
error:function(err){
console.log(err);
}
});
});

</script>

<?php
include '../includes/footer.php';
?>