<?php 
include('../includes/dbcon45.php');
if (isset($_POST['noOfDr'])) {
	$sql = "UPDATE [PlanningSys].[instru].[data] set NoOfStr = '".$_POST['noOfDr']."', StrDia = '".$_POST['strDia']."', InsuType = '".$_POST['insType']."' 
	where id = '".$_POST['id']."'";
	$run = sqlsrv_query($conn, $sql);
	if ($run === false) {
		print_r(sqlsrv_errors());
	}

}



 ?>