<?php
include('../package/jsKey.php');
$title = "Instru Finale Plan";
include '../includes/header.php';
include '../includes/dbcon45.php';
?>
<script type="text/javascript">
let changed = function(worksheet, cell, x, y, newValue, oldValue) {
    let j = parseInt(y) + 1;
    if (x == 0) {
        // you can use table[0] instead of worksheet
        var data = worksheet.getColumnData(17);  
        var drNo = worksheet.getValue('X'+j);  
        worksheet.parent.ignoreEvents = true;
            for (var i = j; i >= 1; i--) {
                var newDrNo = worksheet.getValue('X'+i); 
                    if (newDrNo !== drNo) break;
                    worksheet.setValue('A'+i,newValue);
            }
            // upper side check
             for (var k = j; k <= data.length; k++) {
                var newDrNo = worksheet.getValue('X'+k); 
                    if (newDrNo !== drNo) break;
                    worksheet.setValue('A'+k,newValue);
            }
        worksheet.parent.ignoreEvents = false;
    }
    if (x == 5 || x == 6 || x == 9) {
        var id = worksheet.getValue('B'+j);  
        var noOfDr = worksheet.getValue('F'+j);  
        var strDia = worksheet.getValue('G'+j);  
        var insType = worksheet.getValue('J'+j);  
        // console.log(id+'--'+noOfDr+'--'+strDia+'--'+insType);
        $.ajax({
                url: 'saveMissingGrade_db.php',
                type: 'POST',
                data: {id:id,noOfDr:noOfDr,strDia:strDia,insType:insType},
                success: function(response){
                    console.log(response);
                   } 
            });
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
   <style type="text/css">
   	 .jss > thead > tr > th {
        font-size: 16px !important;
        text-align: center !important;
        font-family: 'Times New Roman' !important;
        white-space: pre-line;
        background-color: #bb76df !important;
    }
/*    #spreadsheet tr:nth-child(even) td{
            background-color: #edf3ff;
        }*/
    td{
    	font-size: 13px !important;
    }
    td.readonly{
            color: #212529a6 !important;
        }
    #GetJobList,#saveForPlanning{
       /* padding: 2px !important;
        height: 32;*/
    }
    .select2-selection--multiple{
       height: 40px;
    }
    .select2-dropdown{
            margin-left: 14px;
            max-height: 200px;
            overflow-y: false;
            max-width: 300px;
    }
    .select2-results__option--selectable{
        font-family: emoji;
        padding: 3px 8px !important;
        border-radius: 5px;
        margin-left: 5px;
        margin-top: 5px;
    }
    .select2-selection__choice{
        background-color: #e2ffff38 !important;
        color: #495057 !important;
    }
    .select2-selection__choice__display{
        font-size: 18px;
        font-family: emoji;
    }
   </style> 
</head>
<body>
    <div class="row mx-2 mb-2">
        <select id="selectJobNo" multiple="multiple" style="width: 70%;"></select>
        <button type="button" class="btn btn-primary mx-1" id="GetJobList" style="width: 10%;">GetJob</button>
        <button class="btn btn-success mx-1" id="saveForPlanning" style="width: 10%;">Save</button> 
   </div>
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

$('#instruFinalePlanPage').addClass('active');
// function to get Data
function getJobList(jobs = null){
var w = $(window).width();
$.ajax({
type: "POST",
url: "getfinalPlan.php",
dataType: "json",
data:{jobs:jobs},
success: function (response) {
    // console.log(response);
table = jspreadsheet(document.getElementById('spreadsheet'), {   
worksheets: [{
data:response,
tableWidth: w*0.85+'px',
tableHeight: '600px',
tableOverflow: true,
// freezeColumns: 4,
columns: [
 { type: 'checkbox', name: 'chk',title: 'Give Plan.', width:'70px' }, //A
{ type: 'hidden', name: 'id',title: 'ID', width:'100px' }, //B
{ type: 'text', name: 'srNo',title: 'Club Seq.', width:'60px',readonly:true }, //C
{ type: 'text', name: 'JobNo',title: 'JobNo', width:'120px',readonly:true,align:'left' }, //D
{ type: 'text', name: 'Size',title: 'Cable Size', width:'70px',readonly:true,align:'left' }, //E
{ type: 'text', name: 'NoOfStr',title: 'NoOf Str', width:'65px' }, //F
{ type: 'text', name: 'StrDia',title: 'Str Dia', width:'50px' }, //G
{ type: 'text', name: 'isMica',title: 'Is Mica', width:'60px',readonly:true }, //H
{ type: 'text', name: 'CondType',title: 'Conductor Type', width:'180px',readonly:true,align:'left' }, //I
{ type: 'text', name: 'InsuType',title: 'Insu Type', width:'120px',align:'left' }, //J
{ type: 'text', name: 'InsuColor1',title: 'Col-1', width:'70px',readonly:true,align:'left' }, //K
{ type: 'text', name: 'InsuColor2',title: 'Col-2', width:'70px',readonly:true,align:'left' }, //K
{ type: 'text', name: 'InsuColor3',title: 'Col-3', width:'70px',readonly:true,align:'left' }, //K
{ type: 'hidden', name: 'InsuColor4',title: 'Col-4', width:'70px',readonly:true,align:'left' }, //K
{ type: 'text', name: 'PairNo',title: 'Pair No', width:'50px',readonly:true }, //L
{ type: 'text', name: 'OrdCutLength',title: 'Order CutL.', width:'70px',readonly:true }, //M
{ type: 'text', name: 'planTol',title: 'Tol %', width:'55px' }, //N
{ type: 'text', name: 'PlanCutLen',title: 'Plan CutL.', width:'70px',mask:'0',readonly:true }, //O
{ type: 'text', name: 'drums',title: 'Plan Drums', width:'70px',readonly:true }, //P
{ type: 'text', name: 'totalCore',title: 'Total Core', width:'70px',mask:'0',readonly:true }, //Q
{ type: 'text', name: 'DrumNo',title: 'Take Up', width:'60px',readonly:true,align:'left' }, //R
{ type: 'hidden', name: 'Sqmm',title: 'Sqmm', width:'100px' }, //S
{ type: 'hidden', name: 'jrCap',title: 'Remaining Cap', width:'100px' }, //T
{ type: 'hidden', name: 'cls',title: 'cls', width:'100px' }, //U
{ type: 'hidden', name: 'isBandM',title: 'isBandM', width:'90px' }, //U

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
    var data = table[0].getColumnData(23);
     data.forEach((e,i) =>{
        let j = parseInt(i) + 1;
        if (e == 'even') {
            table[0].setStyle(j+':'+j, 'background-color', '#86b7fe38');
        }else{
            table[0].setStyle(j+':'+j, 'background-color', '#bdebbc4a');
        }
     });
},
error:function(err){
console.log(err);
}
});
}


$(document).ready(function(){
  // initialize machime data
    $('#selectJobNo').select2({
        placeholder: "Select Jobs",
    });
    $.ajax({
        url: 'getJobList.php',
        type: 'GET',
        dataType: 'json',
        success: function (data) {
           $("#selectJobNo").html(data);
        }
    });
   // call function for get all remaining job list
        getJobList(); 
 // Get Job List
    $(document).on("click", "#GetJobList", function(){
        jspreadsheet.destroy(document.getElementById('spreadsheet'));
        var jobs = $("#selectJobNo").val();
            getJobList(jobs);
        
    });


});

$(document).on("click", "#saveForPlanning", function(){
    Swal.fire({
                title: 'Choose a Machine',
                html: `
                    <select id="selectOption" class="swal2-input">
                        <option value="" selected disabled>--select--</option>
                        <option value="80">80</option>
                        <option value="95">95</option>
                        <option value="45">45</option>
                        <option value="14">14</option>
                        <option value="50">50</option>
                    </select>
                `,
                showCancelButton: true,
                confirmButtonText: 'Submit',
                cancelButtonText: 'Cancel',
                preConfirm: () => {
                    const selectedValue = document.getElementById('selectOption').value;
                    if (!selectedValue) {
                        Swal.showValidationMessage('Please select a machine!');
                    }
                    return selectedValue;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    let data  = table[0].getData(false,true);
                    let arr = [];
                    let missingGrade = [];
                        data.forEach((e,i) => {
                            if (e['chk'] == 'true') {
                                // first check condSize,insuType and insuColor
                                if (e['NoOfStr'] == 0 || e['StrDia'] == 0 || e['InsuType'] == 0 || e['InsuColor1'] == 0 || e['InsuColor2'] == 0) {
                                    missingGrade.push({
                                    'rowNo':(i+1),
                                    'jobNo':e['JobNo']
                                });
                                }
                                var size = e['Size'];
                                var match = size.match(/\d+([A-Z])/);
                                // Color-1
                                arr.push({
                                    'DrumNo':e['DrumNo']+'A',
                                    'pairCode':'A',
                                    'PlanCutLen':e['PlanCutLen'],
                                    'PlanDrums':e['drums'],
                                    'pairNo':e['PairNo'],
                                    'color':e['InsuColor1'],
                                    'isMica':e['isMica'],
                                    'iid':e['id'],
                                    'mc':result.value
                                });
                                // Color-2
                                arr.push({
                                    'DrumNo':e['DrumNo']+'B',
                                    'pairCode':'B',
                                    'PlanCutLen':e['PlanCutLen'],
                                    'PlanDrums':e['drums'],
                                    'pairNo':e['PairNo'],
                                    'color':e['InsuColor2'],
                                    'isMica':e['isMica'],
                                    'iid':e['id'],
                                    'mc':result.value
                                });
                                 // Color-3
                                if (match[1] == 'T') {
                                    arr.push({
                                    'DrumNo':e['DrumNo']+'C',
                                    'pairCode':'C',
                                    'PlanCutLen':e['PlanCutLen'],
                                    'PlanDrums':e['drums'],
                                    'pairNo':e['PairNo'],
                                    'color':e['InsuColor3'],
                                    'isMica':e['isMica'],
                                    'iid':e['id'],
                                    'mc':result.value
                                });
                                }
                                 // Color-4
                                if (match[1] == 'Q') {
                                    arr.push({
                                    'DrumNo':e['DrumNo']+'D',
                                    'pairCode':'D',
                                    'PlanCutLen':e['PlanCutLen'],
                                    'PlanDrums':e['drums'],
                                    'pairNo':e['PairNo'],
                                    'color':e['InsuColor4'],
                                    'isMica':e['isMica'],
                                    'iid':e['id'],
                                    'mc':result.value
                                });
                                }
                                
                            }
                        });
                    // use ajax to save data   
                       if (missingGrade.length > 0) {
                         Swal.fire({
                                    position: 'center',
                                    icon: 'warning',
                                    text: "Some Details is Missing on RowNo: "+missingGrade[0]['rowNo']
                                })
                       }else{
                            $.ajax({
                                url: 'giveFinalPlanning_db.php',
                                type: 'POST',
                                data: {data:arr},
                                success: function(response){
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
                                }
                            });
                       } 
                        
                } 
        });
});
</script>

<?php
include '../includes/footer.php';
?>