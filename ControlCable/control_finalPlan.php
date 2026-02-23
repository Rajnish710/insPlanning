<?php
include('C:\xampp\htdocs\Inquiry\costing\js\jspreadsheetKey.php');
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
        var drNo = worksheet.getValue('Y'+j);  
        worksheet.parent.ignoreEvents = true;
            for (var i = j; i >= 1; i--) {
                var newDrNo = worksheet.getValue('Y'+i); 
                    if (newDrNo !== drNo) break;
                    worksheet.setValue('A'+i,newValue);
            }
            // upper side check
             for (var k = j; k <= data.length; k++) {
                var newDrNo = worksheet.getValue('Y'+k); 
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
  const isInvalidVal = (v) => {
    if (v === 0 || v === "0") return true;
    if (typeof v === "string" && v.trim() === "") return true;
    return false;
};
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

$('#ControlCablePlanPage').addClass('active');
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
 { type: 'checkbox', name: 'chk',title: 'Give Plan.', width:'60px' }, //A
{ type: 'hidden', name: 'id',title: 'ID', width:'100px' }, //B
{ type: 'text', name: 'srNo',title: 'Club Seq.', width:'60px',readonly:true }, //C
{ type: 'text', name: 'JobNo',title: 'JobNo', width:'120px',readonly:true,align:'left' }, //D
{ type: 'text', name: 'Size',title: 'Cable Size', width:'65px',readonly:true,align:'left' }, //E
{ type: 'text', name: 'NoOfStr',title: 'NoOf Str', width:'65px' }, //F
{ type: 'text', name: 'StrDia',title: 'Str Dia', width:'50px' }, //G
{ type: 'text', name: 'isMica',title: 'Is Mica', width:'55px',readonly:true }, //H
{ type: 'text', name: 'CondType',title: 'Conductor Type', width:'180px',readonly:true,align:'left' }, //I
{ type: 'text', name: 'InsuType',title: 'Insu Type', width:'120px',align:'left' }, //J
{ type: 'text', name: 'InsuColor1',title: 'Col-1', width:'70px',readonly:true,align:'left' }, //K
{ type: 'text', name: 'InsuColor2',title: 'Col-2', width:'70px',readonly:true,align:'left' }, //L
{ type: 'text', name: 'InsuColor3',title: 'Col-3', width:'70px',readonly:true,align:'left' }, //M
{ type: 'text', name: 'InsuColor4',title: 'Col-4', width:'70px',readonly:true,align:'left' }, //N
{ type: 'text', name: 'InsuColor5',title: 'Col-5', width:'70px',readonly:true,align:'left' }, //O
{ type: 'text', name: 'PairNo',title: 'Pair No', width:'50px',readonly:true }, //P
{ type: 'text', name: 'OrdCutLength',title: 'Order CutL.', width:'70px',readonly:true }, //Q
{ type: 'text', name: 'planTol',title: 'Tol %', width:'55px' }, //R
{ type: 'text', name: 'PlanCutLen',title: 'Plan CutL.', width:'70px',mask:'0',readonly:true }, //S
{ type: 'text', name: 'drums',title: 'Plan Drm', width:'65px',readonly:true }, //T
{ type: 'text', name: 'totalCore',title: 'Total Core', width:'70px',mask:'0',readonly:true }, //U
{ type: 'text', name: 'DrumNo',title: 'Take Up', width:'60px',readonly:true,align:'left' }, //V
{ type: 'hidden', name: 'Sqmm',title: 'Sqmm', width:'100px' }, //W
{ type: 'hidden', name: 'jrCap',title: 'Remaining Cap', width:'100px' }, //X
{ type: 'hidden', name: 'cls',title: 'cls', width:'100px' }, //Y

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
    var data = table[0].getColumnData(24);
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

$(document).on("click", "#saveForPlanning", function () {
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
        if (!result.isConfirmed) return; // short-circuit on cancel

        const mc = result.value;
        const data = table[0].getData(false, true);
        const arr = [];

        // Loop with short-circuit style
        for (let i = 0; i < data.length; i++) {
            const e = data[i];

            // Only process checked rows
            if (String(e['chk']) !== 'true') continue;

            const rowNo = i + 1;
            // 1) Validate NoOfStr, StrDia, InsuType
            if (isInvalidVal(e['NoOfStr']) || isInvalidVal(e['StrDia']) || isInvalidVal(e['InsuType'])) {
                Swal.fire({
                    position: 'center',
                    icon: 'warning',
                    text: `Some details are missing on Row No: ${rowNo} (Job: ${e['JobNo'] || ''})`
                });
                return; // short-circuit: stop further processing
            }

            // 2) Determine cores from Size (assuming "X C Y" pattern like "4 C 25")
            let cores = 0;
            if (e['Size']) {
                const sizeStr = String(e['Size']);
                const corePart = sizeStr.split('C')[0].trim();
                const parsed = parseInt(corePart, 10);
                if (!isNaN(parsed)) {
                    cores = parsed;
                }
            }

            // Fallback if parsing fails
            if (!cores) {
                // If you want to treat this as error instead:
                // Swal.fire({ ... }); return;
                cores = 6; // so it goes to the "else" branch
            }

            if (cores <= 5) {
                // 3) For up to 5 cores, check every InsuColor1..5
                const alphabets = ['', 'A', 'B', 'C', 'D', 'E'];
                for (let k = 1; k <= cores; k++) {
                    const colorKey = 'InsuColor' + k;
                    const color = e[colorKey];
                    if (!color) {
                        Swal.fire({
                            position: 'center',
                            icon: 'warning',
                            text: `Insulation Color is missing on Row No: ${rowNo} (Job: ${e['JobNo'] || ''})`
                        });
                        return; // short-circuit on first missing color
                    }
                    const drumNoWithAlphabet = e['DrumNo'] + alphabets[k];
                    arr.push({
                        'DrumNo': drumNoWithAlphabet,
                        'PlanCutLen': e['PlanCutLen'],
                        'PlanDrums': e['drums'],
                        'pairNo': e['PairNo'],
                        'color': color,
                        'isMica': e['isMica'],
                        'iid': e['id'],
                        'mc': mc
                    });
                }
            } else {
                // 4) More than 5 cores: use InsuColor1 only (as per your original logic)
                const color = e['InsuColor1'];
                if (!color) {
                    Swal.fire({
                        position: 'center',
                        icon: 'warning',
                        text: `Insulation Color is missing on Row No: ${rowNo} (Job: ${e['JobNo'] || ''})`
                    });
                    return; // short-circuit
                }

                arr.push({
                    'DrumNo': e['DrumNo']+'-',
                    'PlanCutLen': e['PlanCutLen'],
                    'PlanDrums': e['drums'],
                    'pairNo': e['PairNo'],
                    'color': color,
                    'isMica': e['isMica'],
                    'iid': e['id'],
                    'mc': mc
                });
            }
        }

        // If no rows were selected
        if (!arr.length) {
            Swal.fire({
                position: 'center',
                icon: 'info',
                text: 'No rows selected for planning.'
            });
            return;
        }

        // 5) Finally, AJAX save
        $.ajax({
            url: 'giveFinalPlanning_db.php',
            type: 'POST',
            data: { data: arr }, // if needed, use JSON.stringify(arr) on PHP side
            success: function (response) {
                if (response == 'ok') {
                    Swal.fire({
                        position: 'center',
                        icon: 'success',
                        title: "Success",
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    console.log(response);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
            }
        });
    });
});
</script>

<?php
include '../includes/footer.php';
?>