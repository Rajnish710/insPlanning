<?php 
include('../includes/dbcon45.php');
include('../includes/dbcon.php');
$sql = "SELECT JobNo,CONCAT(NoOfStr,'/',StrDia) as Size,b.McNo,b.InsuColor,b.CondType,b.InsuType,b.PairNo,b.OrdCutLength,CONCAT(b.Core, b.CP, ' X ', b.Sqmm) AS CPsize,b.isMica,
b.isBandM,b.isNoPrintOnCore,a.cutLen,SUM(a.noofDrums) as PlanDrums,SUM(a.cutLen*a.noofDrums) as TotalCore from [PlanningSys].[instru].[givenPlanning] a join [PlanningSys].[instru].data b on a.iid = b.id where isProdComplete = 0 group by JobNo,CONCAT(NoOfStr,'/',StrDia),b.McNo,b.InsuColor,b.CondType,b.InsuType,b.PairNo,b.OrdCutLength,b.Core,b.CP,b.Sqmm,b.isMica,b.isBandM,b.isNoPrintOnCore,a.cutLen";
// echo $sql;
// exit;
$sr = 0;

$run = sqlsrv_query($conn,$sql);
while ($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)) {
	$sql1 = "SELECT SUM(FLOOR(a.PQty/b.CQty)) as actualDrums from Ins a join Ins_CutLength b on a.SrNo = b.Ins_ID where b.JobNo = '".$row['JobNo']."' and a.Colour = '".$row['InsuColor']."' AND b.CQty = '".$row['OrdCutLength']."'";

    $sr++;

    $InsuType = $row['InsuType'];
    $cleanedString = str_replace("/% /", "", $InsuType);
   
	$run1 = sqlsrv_query($conn,$sql1);
	$row1 = sqlsrv_fetch_array($run1, SQLSRV_FETCH_ASSOC);
	if ($row1['actualDrums'] < $row['PlanDrums']) {
		// $row['actualDrums'] = $row1['actualDrums'];
        $row['pendCore'] = '=(L'.$sr.'-N'.$sr.')*K'.$sr.'';
        $row['InsuType'] = $cleanedString;
	    $rows[] = $row;

	}else{
        $qry = "SELECT id FROM [PlanningSys].[instru].[data]  WHERE  JobNo = '".$row['JobNo']."' AND OrdCutLength='".$row['OrdCutLength']."' AND InsuColor='".$row['InsuColor']."'";
        $result = sqlsrv_query($con,$qry);
        $row3 = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

        $sql2 = "UPDATE [PlanningSys].[instru].[givenPlanning] SET isProdComplete='1' WHERE iid = '".$row3['id']."'";
        $run2 = sqlsrv_query($con,$sql2);
    }
}

echo json_encode($rows);

// echo '<pre>';
// print_r($rows);
// echo '</pre>';

// =(J5-L5)*I5 = (PlanDrums-actualDrums)*cutLen

 ?>