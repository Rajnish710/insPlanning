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
            url: 'instru_data_db.php',
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
<button class="btn btn-success m-3" id="saveChecked">Mark As Complete</button> 
    <div class="row cblePlan mx-2">
        <div class="entry container-fluid" id="spreadsheet">
                Please Wait..........
        </div>
    </div><br>
</body>
</html>
<script type="text/javascript">

$('#ControlProdPlanPage').addClass('active');

$(document).ready(function(){

jspreadsheet.destroy(document.getElementById('spreadsheet'));
    $('#spinner').html('<span class="spinner-border spinner-border-md mx-2"></span><p>Loading . . .</p>');
    var w = $(window).width(); 
        
$.ajax({
    type: "POST",
    url: "getProdPending.php",
    dataType: "json",
    data:{data:'1'},
    success: function (response) {

        $('#spinner').html('');
            // console.log(response);
    // (response[0]['inqno'][11] == 'hide') ? $("#saveData").hide() : $("#saveData").show();
    table = jspreadsheet(document.getElementById('spreadsheet'), {
        // tabs: true,
        toolbar: true,
    worksheets: [{
    data:response,
    tableOverflow: true,
    // tableWidth: '1850px',
    tableWidth: w*0.84+'px',
    allowManualInsertRow: false,
    allowManualInsertColumn: false,
    tableHeight: '600px',
    // freezeColumns: 2,
    columnDrag: false,

    columns: [
        { type: 'checkbox', name: 'chk',title: 'Check', width:'70px' }, //A
        { type: 'text', title: 'JobNo',name: 'JobNo', width:'120px' },//B
        { type: 'text', name: 'CPsize',title: 'Cable Size', width:'80px',readonly:true }, //F
        { type: 'text', title: 'Mc No',name: 'McNo', width:'55px' },//B
        { type: 'text', title: 'Cond Size',name: 'Size', width:'80px'}, //C
        { type: 'text', title: 'Insu Color',name: 'InsuColor', width:'110px'}, //D
        { type: 'text', title: 'Cond Type',name: 'CondType', width:'190px',align:'left' }, //E
        { type: 'text', title: 'Insu Type',name: 'InsuType', width:'100px' }, //F
        { type: 'text', title: 'Pair No',name: 'PairNo', width:'50px' }, //G
        { type: 'text', title: 'Ord CutLen',name: 'OrdCutLength', width:'90px' }, //H
        { type: 'text', title: 'cutLen',name: 'cutLen', width:'70px' }, //I
        { type: 'text', title: 'Plan Drums',name: 'PlanDrums', width:'70px' }, //J
        { type: 'text', title: 'Total Core',name: 'TotalCore', width:'70px'}, //K
        { type: 'text', title: 'Actual Drums',name: 'actualDrums', width:'80px'}, //
        { type: 'text', title: 'Pending Core',name: 'pendCore', width:'80px'}, //
        { type: 'text', title: 'Drum No',name: '', width:'90px'}, //
        { type: 'text', title: 'Is Mica', name: 'isMica', width:'70px' }, //P
        { type: 'text', title: 'Band Mark', name: 'isBandM', width:'70px' }, //P
        { type: 'text', title: 'No. Print', name: 'isNoPrintOnCore', width:'70px' },
    ],
 
    filters: true,
 columnSorting:false,
}],
includeHeadersOnDownload: true,

    });
 },
  complete:function(response){

  },
  error:function(response){
    console.log(response);
  }     
       
    });   
});

// if check then upadate Flag

$(document).on("click", "#saveChecked", function(){
    var data = table[0].getData();
    let arr = [];
    data.forEach((e,i) =>{
        if (e['chk']) {           
            arr.push({
                'jobno':e['JobNo'],
                'ordCutLen':e['OrdCutLength'],
                'color':e['InsuColor']
            });
        }
    });
     // Check if arr is empty
     if (arr.length === 0) {
        alert("Please check at least one item before submitting.");
    } else {
        // Continue with your submit or further processing logic
        console.log(arr);  // Or submit data to your backend
    }
    $.ajax({
        type: "POST",
        url: "giveFinalProdPlan_db.php",
        data:{data:arr},
        success: function (response) {
            if (response == 'ok') {
                Swal.fire({
                    position: 'center',
                    icon: 'success',
                    title: "Success",
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.reload();  // Refresh the page
                });

            }else{
                console.log(response);
            }
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