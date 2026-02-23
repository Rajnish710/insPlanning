<?php
include('jsKey.php');
$title = "Instru Finale Plan";
include '../includes/header.php';
include '../includes/dbcon45.php';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Instru Cable</title>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.6.0/dist/jszip.min.js"></script>
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
    <div class="row">
        <div class="col">
            <button class="btn btn-success m-3" id="saveForPlanning">Save for Planning</button> 
        </div>
        <div class="col-auto">
            <div class="d-flex m-3">
                <span class="input-group-text" style ="background-color:#d3c4d98f; color: #25022c">MCNO</span>
                <input type="text" class="form-control" id="mcno"><a class="btn btn-primary ms-2 mcnoPDF" target="_blank">PDF</a> 
            </div>
        </div>
   </div>
   <div class="row cblePlan mx-2">
        <div class="entry container-fluid" id="spreadsheet">
            Please Wait..........
        </div>
    </div><br>
</body>
</html>
<script type="text/javascript">

$('#ControlFinalePlanPage').addClass('active');

// PDF
$(document).on('change','#mcno',function(){
    var mcno = $(this).val();
    $('.mcnoPDF').attr('href', 'insPlanPDF.php?id=' + mcno);
});

	// jspreadsheet.setExtensions({ formula,render });
$(document).ready(function(){
var w = $(window).width();
var enqNo = 2224;
$.ajax({
type: "POST",
url: "getfinalPlan.php",
dataType: "json",
data:{enqNo:enqNo},
success: function (response) {
table = jspreadsheet(document.getElementById('spreadsheet'), {   
worksheets: [{
data:response,
tableWidth: w*0.85+'px',
tableHeight: '600px',
tableOverflow: true,
freezeColumns: 4,
columns: [
 { type: 'checkbox', name: 'chk',title: 'Give To Planning', width:'90px' }, //A
{ type: 'text', name: 'id',title: 'ID', width:'0px' }, //B
{ type: 'text', name: 'srNo',title: 'srNo', width:'0px',readonly:true }, //C
{ type: 'text', name: 'McNo',title: 'M/c', width:'70px',readonly:true }, //D
{ type: 'text', name: 'JobNo',title: 'JobNo', width:'130px',readonly:true }, //E
{ type: 'text', name: 'CPsize',title: 'Cable Size', width:'90px',readonly:true }, //F
{ type: 'text', name: 'CondType',title: 'Conductor Type', width:'200px',readonly:true,align:'left' }, //G
{ type: 'text', name: 'InsuType',title: 'Insu Type', width:'150px',readonly:true }, //H
{ type: 'text', name: 'PairNo',title: 'Pair No', width:'50px',readonly:true }, //I
{ type: 'text', name: 'InsuColor',title: 'Insu Color', width:'120px',readonly:true }, //J
{ type: 'text', name: 'size',title: 'Cond Size', width:'90px',readonly:true }, //K
{ type: 'text', name: 'isMica',title: 'Is Mica', width:'60px',readonly:true }, //L
{ type: 'text', name: 'PlanCutLen',title: 'Plan CutLength', width:'100px',readonly:true }, //M
{ type: 'text', name: 'noOfDrums',title: 'Plan Drums', width:'70px' }, //N
{ type: 'text', name: 'totalCore',title: 'Total Core', width:'70px',readonly:true }, //O
{ type: 'text', name: 'DrumNo',title: 'DrumNo', width:'100px' }, //P
{ type: 'text', name: 'isBandM',title: 'Band Mark', width:'70px' }, //Q
{ type: 'text', name: 'isNoPrintOnCore',title: 'No. Print', width:'70px' }, //R
{ type: 'text', name: 'Sqmm',title: 'Sqmm', width:'0px' }, //R

],
columnSorting:false,
filters:true,
}],
toolbar:true,
includeHeadersOnDownload: true,
// onchange: changed,
});
},
complete:function(data){

},
error:function(err){
console.log(err);
}
});
});

$(document).on("click", "#saveForPlanning", function(){
    var data = table[0].getData();
    let arr = [];
    data.forEach((e,i) =>{
        if (e['chk']) {           
            arr.push({
                'iid':e['id'],
                'cutLen':e['PlanCutLen'],
                'noOfDrums':e['noOfDrums'],
                'DrumNo':e['DrumNo']
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
        url: "giveFinalPlanning_db.php",
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