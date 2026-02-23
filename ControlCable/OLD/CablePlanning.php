<?php 
include('jsKey.php');
$title = "Instru Cable Plan";
include '../includes/header.php';
include '../includes/dbcon45.php';
?>
<script type="text/javascript">
    let changed = function(worksheet, cell, x, y, newValue, oldValue) {
var isfire = $("#isAddMaster").val();
if (x == 16 || x == 19 || x == 20) {
var row = table[0].getRowData(y);
// console.log(row);
var data = {
    iid: row['iid'],
    ord_NoofDrums: row['ord_NoofDrums'],
    noOfStr: row['noOfStr'],
    strDia: row['StrDia'],
    condGrade: row['condGrade'],
    insuType: row['insuType'],
    planCutLen: row['planCutLen'],
    noOfDrums: row['noOfDrums'],
    insuColor: row['insuColor'],
    mc: row['mc'],
    planningDate: row['planningDate'],
    reqDate: row['reqDate'],
    drumNo: row['drumNo'],
    remark: row['remark']
};

if (data['strDia'] == '' || data['insuColor'] == '') {
    Swal.fire({
      icon: "error",
      title: "Oops...",
      text: "Either Conductor Size & Insulation Color Missing!"
    });
}else{
    if (data['mc'] != '') {
        $.ajax({
            url: 'control_data_db.php',
            type: 'POST',
            data: data,
            success: function(response) {
                if (response != 'yes') {
                    console.log(response);
                }else{
                    console.log(response);
                }
            },
            error: function(error) {
                console.log('Error:', error);
            }
        });
    }
}
 
}
}
</script>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Instru Cable</title>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css">
    <!-- <script src="https://cdn.jsdelivr.net/npm/jszip@3.6.0/dist/jszip.min.js"></script> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
   <style type="text/css">
   	 .jss > thead > tr > th {
        font-size: 16px !important;
        text-align: center !important;
        font-family: 'Times New Roman' !important;
        white-space: pre-line;
        background-color: #c8edbf !important;
    }
/*    #spreadsheet tr:nth-child(even) td{
            background-color: #edf3ff;
        }*/
    td{
    	font-size: 13px !important;
    }
    td.readonly{
            color: #363949 !important;
        }    

   </style> 
</head>
<body>
    <div class="row mx-2">
        <div class="entry container-fluid" id="spreadsheet">
                Please Wait..........
        </div>
    </div><br>

</body>
</html>
<script type="text/javascript">

$('#ControlCablePlanPage').addClass('active');

// jspreadsheet.setExtensions({ formula,render });
$(document).ready(function(){
var w = $(window).width();
var enqNo = 2224;
$.ajax({
type: "POST",
url: "getCableData.php",
dataType: "json",
data:{enqNo:enqNo},
success: function (response) {
table = jspreadsheet(document.getElementById('spreadsheet'), {   
worksheets: [{
data:response,
tableWidth: w*0.85+'px',
// tableWidth:'100%',
// tableHeight: '100vh',
tableHeight: '700px',
tableOverflow: true,
freezeColumns: 3,
allowManualInsertRow: false,
allowManualInsertColumn: false,
columns: [
{ type: 'text', name: 'delDate',title: 'Delivery Date', width:'100px',readonly:true }, //A
{ type: 'text', name: 'jobNo',title: 'JobNo', width:'120px',readonly:true }, //B
{ type: 'text', name: 'size',title: 'Size', width:'90px',readonly:true }, //C
{ type: 'text', name: 'ordQty',title: 'Order Qty', width:'75px',readonly:true }, //D
{ type: 'text', name: 'ordCutLen',title: 'Ord Cut Length', width:'90px',readonly:true }, //E
{ type: 'text', name: 'condGrade',title: 'Cond. Grade', width:'80px' }, // F
{ type: 'text', name: 'condType',title: 'Conductor Type', width:'200px',align:'left',readonly:true }, //G
{ type: 'text', name: 'insuType',title: 'Insu Type', width:'100px' }, //H
{ type: 'text', name: 'noOfStr',title: 'No Of Strand', width:'70px' }, //I
{ type: 'text', name: 'StrDia',title: 'Strand Dia', width:'70px',mask:'0.000' }, //J
{ type: 'text', name: 'condSize',title: 'Cond Size', width:'80px',readonly:true }, // K
{ type: 'text', name: 'planCutLen',title: 'Plan CutLength', width:'100px' }, //L
{ type: 'text', name: 'noOfDrums',title: 'Plan Drums', width:'70px' },//M
{ type: 'text', name: 'multiply',title: 'Multiply', width:'0px',readonly:true }, //N
{ type: 'text', name: 'totalCoreLen',title: 'Total Core', width:'70px',readonly:true }, //O
{ type: 'text', name: '',title: 'Band Mark', width:'70px' }, //P
{ type: 'text', name: '',title: 'No. Print', width:'70px' }, //Q
{ type: 'text', name: 'insuColor',title: 'Insu Color', width:'90px' }, //R
{ type: 'autocomplete', name: 'mc', title: 'M/c', width:'70px', source:['14','45','50','80','95','101','33'] }, //S
{ type: 'calendar', name: 'planningDate',options: { format:'DD-MMM-YYYY' },title: 'Planning Date', width:'100px' }, //T
{ type: 'calendar', name: 'reqDate',options: { format:'DD-MMM-YYYY' },title: 'Req. Date', width:'100px' }, //U
// { type: 'text', name: 'reqDate',title: 'Req. Date', width:'100px' },
{ type: 'text', name: 'drumNo',title: 'Drum No', width:'0px' }, //V
{ type: 'text', name: 'remark',title: 'Remark', width:'150px' }, //W
{ type: 'text', name: 'iid',title: 'iid', width:'0px',readonly:true }, //X
{ type: 'text', name: 'ord_NoofDrums',title: 'ord_NoofDrums', width:'0px',readonly:true }, //Y
],
columnSorting:false,
filters:true,
}],
toolbar:true,
includeHeadersOnDownload: true,
onchange: changed,
});
},
complete:function(data){

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