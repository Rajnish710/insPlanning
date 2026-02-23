<?php 
include('../includes/dbcon45.php');
date_default_timezone_set('Asia/Kolkata');
$rows = array();
$sql = "SELECT id ,DeliveryDate ,JobNo ,Core ,CP ,Sqmm ,OrderQty ,OrdCutLength ,ord_NoofDrums ,CondGrade ,CondType ,InsuType ,NoOfStr ,StrDia ,PlanCutLen ,plan_NoofDrums, multiplyDrums,InsuColor,PlanningDate ,RequiredDate, McNo, DrumNo, Remark FROM [PlanningSys].[instru].[data] where isGiven = 0 AND isDelete = 0"; 
$run = sqlsrv_query($conn,$sql);
$x = 0;
while($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)){

    $InsuType = $row['InsuType'];
    $cleanedString = str_replace("/% /", "", $InsuType);

	$x++;
	$rows[] = array(
		'delDate'=>$row['DeliveryDate']->format('d-M-Y'),
		'jobNo'=>$row['JobNo'],
		'size'=>$row['Core'].$row['CP'].' X '.$row['Sqmm'],
		'ordQty'=>$row['OrderQty'],
		'ordCutLen'=>$row['OrdCutLength'].' X '.$row['ord_NoofDrums'],
		'condGrade'=>$row['CondGrade'],
		'condType'=>$row['CondType'],
		'insuType'=>$cleanedString,
		'insuColor'=>$row['InsuColor'],
		'noOfStr'=>$row['NoOfStr'],
		'StrDia'=>$row['StrDia'],
		'condSize'=>'=IF(OR(J'.$x.'="",I'.$x.'=""),"",I'.$x.'&"/"&J'.$x.')',
		'planCutLen'=>$row['PlanCutLen'],
		'noOfDrums'=>$row['plan_NoofDrums'],
		'multiply'=>$row['multiplyDrums'],
		'totalCoreLen'=>'=L'.$x.'*M'.$x.'*N'.$x.'',
		'mc'=>$row['McNo'],
		'planningDate'=>date('Y-m-d'),
		'reqDate'=>$row['DeliveryDate']->format('Y-m-d'),
		'drumNo'=>$row['DrumNo'],
		'remark'=>$row['Remark'],
		'iid'=>$row['id'],
		'ord_NoofDrums'=>$row['ord_NoofDrums']
	);
}


// echo '<pre>';
// print_r($rows);
// echo '</pre>';
echo json_encode($rows);

 ?>