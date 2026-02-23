<?php 
include '../includes/dbcon45.php';

$sql = "WITH givenDrums AS (
		    SELECT 
		        iid,
		        SUM(noofDrums) AS totalDrums
		    FROM [PlanningSys].[control].[givenPlanning] WHERE isProdComplete = 0
		    GROUP BY iid
		)
			SELECT
			    DISTINCT d.JobNo
			FROM 
			    [PlanningSys].[control].[data] d
			LEFT JOIN 
			    givenDrums g ON d.id = g.iid
			WHERE 
			    d.isDelete = 0 
			    AND d.drums - COALESCE(g.totalDrums, 0) > 0";
$run = sqlsrv_query($conn, $sql);
while ($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)) {
	$data[] = '<option value="'.$row['JobNo'].'">'.$row['JobNo'].'</option>';
}

echo json_encode($data);

 ?>