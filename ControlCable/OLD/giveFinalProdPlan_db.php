<?php
session_start();
$user = $_SESSION['username'];
include('../includes/dbcon.php');
date_default_timezone_set('Asia/Kolkata');
if (isset($_POST['data'])) {
try {
sqlsrv_begin_transaction($con);


foreach ($_POST['data'] as $key => $val) {


$qry = "SELECT id FROM [PlanningSys].[instru].[data]  WHERE  JobNo = '".$val['jobno']."' AND OrdCutLength='".$val['ordCutLen']."' AND InsuColor='".$val['color']."'";
$result = sqlsrv_query($con,$qry);
$row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);

$sql1 = "UPDATE [PlanningSys].[instru].[givenPlanning] SET isProdComplete='1' WHERE iid = '".$row['id']."'";
$run1 = sqlsrv_query($con,$sql1);

if ($run1 === false) {
	throw new Exception("Error: " . print_r(sqlsrv_errors(), true));
}
}
sqlsrv_commit($con);
	echo 'ok';

} catch (Exception $e) {
sqlsrv_rollback($con);
echo "Error: " . $e->getMessage();
}
}
?>