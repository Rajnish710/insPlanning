<?php
session_start();
$user = $_SESSION['username'] ?? '';
include('../includes/dbcon45.php');
date_default_timezone_set('Asia/Kolkata');
if (isset($_POST['data'])) {
try {
sqlsrv_begin_transaction($conn);

$qry = "SELECT COALESCE(MAX(planningNo), 0) + 1 as planningNo from [PlanningSys].[control].[givenPlanning]";
$result = sqlsrv_query($conn,$qry);
$res = $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
foreach ($_POST['data'] as $key => $val) {
$sql1 = "INSERT INTO [PlanningSys].[control].[givenPlanning]
([iid], [cutLen], [noofDrums], [drumNo],[PairNo], [isMica] ,[color] ,[planningNo], [McNo], [createdAt], [createdBy])
VALUES
('".$val['iid']."',
'".$val['PlanCutLen']."',
'".$val['PlanDrums']."',
'".$val['DrumNo']."',
'".$val['pairNo']."',
'".$val['isMica']."',
'".$val['color']."',
'".$res['planningNo']."',
'".$val['mc']."',
'".date('Y-m-d H:i:s')."',
'".$user."')";
$run1 = sqlsrv_query($conn,$sql1);
if ($run1 === false) {
	throw new Exception("Error: " . print_r(sqlsrv_errors(), true));
}
}
sqlsrv_commit($conn);
	echo 'ok';

} catch (Exception $e) {
sqlsrv_rollback($conn);
echo "Error: " . $e->getMessage();
}
}
?>