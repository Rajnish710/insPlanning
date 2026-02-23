<?php 
include('C:\xampp\htdocs\Inquiry\costing\js\jspreadsheetKey.php');
$title = "Production Pending";
include '../includes/header.php';
include '../includes/dbcon.php';
include '../includes/dbcon45.php';
?>
<script type="text/javascript">
let changed = function(worksheet, cell, x, y, newValue, oldValue) {
    if (x == 0) {
        let j = parseInt(y) + 1;
        // you can use table[0] instead of worksheet
        var data = worksheet.getColumnData(21);  
        var drNo = worksheet.getValue('V'+j);  
        worksheet.parent.ignoreEvents = true;
            for (var i = j; i >= 1; i--) {
                var newDrNo = worksheet.getValue('V'+i); 
                    if (newDrNo !== drNo) break;
                    worksheet.setValue('A'+i,newValue);
            }
            // upper side check
             for (var k = j; k <= data.length; k++) {
                var newDrNo = worksheet.getValue('V'+k); 
                    if (newDrNo !== drNo) break;
                    worksheet.setValue('A'+k,newValue);
            }
        worksheet.parent.ignoreEvents = false;
    }
}
</script>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
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
    td{
    	font-size: 13px !important;
    }
td.readonly{
        color: #363949 !important;
    }    
    .jss > tbody > tr > td.jss_number {
         text-align: center !important; 
    }
   </style> 
</head>
<body>
<div class="row mx-4 mb-2 d-flex align-items-center">
    <!-- Button trigger modal -->
    <button type="button" class="btn btn-primary me-2" style="width: 10%;" data-bs-toggle="modal" data-bs-target="#exampleModal">
    PDF
    </button>
    <!-- <input style="width: 20%;" type="text" id="mcNo" class="form-control me-2" placeholder="Enter MC Number">
    <button style="width: 10%;" type="button" class="btn btn-primary me-2" id="GetPdf">GET PDF</button> -->
    <button style="width: 10%;" class="btn btn-success me-2" id="markAsComplete">Save</button>
    
</div>
<div class="row mx-2">
    <div class="entry container-fluid" id="spreadsheet">
        Please Wait..........
    </div>
</div>
<br>
<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Palnning No</th>
                    <th>Machine No</th>
                    <th>PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $sql = "SELECT DISTINCT CAST(createdAt AS DATE) AS Date, planningNo, McNo FROM [PlanningSys].[instru].[givenPlanning] order by planningNo DESC";
                    $run = sqlsrv_query($con, $sql);
                    while($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)){
                 ?>
                <tr>
                    <td><?php echo $row['Date']->format('d-m-Y') ?></td>
                    <td><?php echo $row['planningNo'] ?></td>
                    <td><?php echo $row['McNo'] ?></td>
                    <td><button type="button" class="btn btn-sm btn-primary GetPdf" id="<?php echo $row['planningNo'] ?>" data-mc="<?php echo $row['McNo'] ?>">PDF</button></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
      </div>
      <!-- <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Save changes</button>
      </div> -->
    </div>
  </div>
</div>
</body>
</html>
<script type="text/javascript">
$('#instruProdPlanPage').addClass('active');
// function to get Data
function getJobList(jobs = null){
var w = $(window).width();
$.ajax({
type: "POST",
url: "getInstru_prodPending.php",
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
 { type: 'checkbox', name: 'chk',title: 'Is Complete', width:'90px' }, //A
{ type: 'text', name: 'id',title: 'ID', width:'0px',readonly:true }, //B
{ type: 'text', name: 'mcno',title: 'Mc No', width:'60px',readonly:true }, //C
{ type: 'text', name: 'planningNo',title: 'Plan No', width:'60px',readonly:true }, //D
{ type: 'text', name: 'date',title: 'Planning Date', width:'100px',readonly:true }, //E
{ type: 'text', name: 'JobNo',title: 'JobNo', width:'140px',readonly:true,align:'left' }, //F
{ type: 'text', name: 'Size',title: 'Size', width:'90px',readonly:true,align:'left' }, //G
{ type: 'text', name: 'condSize',title: 'Cond Size', width:'70px',readonly:true }, //H
{ type: 'text', name: 'isMica',title: 'Mica', width:'60px',readonly:true }, //I
{ type: 'text', name: 'CondType',title: 'Conductor Type', width:'190px',readonly:true,align:'left' }, //J
{ type: 'text', name: 'InsuType',title: 'Insu Type', width:'140px',readonly:true,align:'left' }, //K
{ type: 'text', name: 'color',title: 'Insu Color', width:'100px',readonly:true,align:'left' }, //L
{ type: 'text', name: 'PairNo',title: 'Pair No', width:'50px',readonly:true }, //M
{ type: 'text', name: 'cutLen',title: 'Cut Length', width:'80px',mask:'0',readonly:true }, //N
{ type: 'text', name: 'noofDrums',title: 'Drums', width:'70px' }, //O
{ type: 'text', name: 'totalCore',title: 'Total Core', width:'80px',mask:'0',readonly:true }, //P
{ type: 'text', name: 'TakeUp',title: 'TakeUp Drum', width:'80px',readonly:true }, //Q
{ type: 'text', name: 'drCnt',title: 'drum count', width:'0px' }, //R
{ type: 'text', name: 'nextTakeUp',title: 'next TakeUp', width:'0px' }, //S
{ type: 'text', name: 'drCnt1',title: 'drum count1', width:'0px' }, //T
{ type: 'text', name: 'nextTakeUp1',title: 'next TakeUp1', width:'0px' }, //U
{ type: 'text', name: 'cls',title: 'styleCol', width:'0px' } //V

],
columnSorting:false,
filters:true,
style: {
        // 'C:R': 'border-top:1px solid black;border-left:1px solid black',
    },
}],
toolbar:true,
includeHeadersOnDownload: true,
onchange: changed,
});
},
complete:function(data){
    var data = table[0].getData();

     data.forEach((e, i) => {
        const j = i + 1; 
        const rowRange = `${j}:${j}`;
        // Merge cells based on 'TakeUp' and 'PairNo'
        if (e.nextTakeUp && e.drCnt > 1) {
            table[0].setMerge(`P${j}`, 1, e.drCnt);
        }
        // Merge cells based on only 'TakeUp No'
        if (e.nextTakeUp1 && e.drCnt1 > 1) {
            table[0].setMerge(`Q${j}`, 1, e.drCnt1);
        }
        // Apply styles based on 'cls'
        const backgroundColor = e.cls === 'even' ? '#86b7fe38' : '#bdebbc4a';
        table[0].setStyle(rowRange, 'background-color', backgroundColor);
    });
},
error:function(err){
console.log(err);
}
});
}
$(document).ready(function(){
       // call function for get all remaining job list 
        getJobList();
   // Get Pdf for machine planning
    $(document).on("click",".GetPdf",function(){
        var planno = $(this).attr('id');
        var mc = $(this).data('mc');
        // alert(planno);
        // alert(mc);
            window.open('http://103.53.72.188:5560/InsPlanning/Instrumentation/insPlanPDF.php?mc='+mc+ '&planno='+planno, '_blank');
       
    }); 

   // save as complete
    $(document).on("click", "#markAsComplete", function(){
        let data = table[0].getData();
        console.log(table[0].getStyle());
    });


});

</script>

<?php
include '../includes/footer.php';
?>