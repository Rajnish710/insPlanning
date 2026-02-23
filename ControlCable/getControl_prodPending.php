<?php 
// if (isset($_POST['jobs'])) {
if (true) {

include('../includes/dbcon45.php');
$rows = array();

$sql = "WITH givenTbl AS (
    SELECT 
    gp1.planningNo,
    gp1.drumNo,
    gp1.color,
    gp1.pairNo,
    SUM(gp1.cutLen * gp1.noofDrums) AS totalCore,
    COUNT(gp1.drumNo) AS drCnt,
    drcnt1.drumCount AS drCnt1
FROM 
    [PlanningSys].[control].givenPlanning AS gp1
LEFT JOIN (
    SELECT 
        planningNo, 
        drumNo,
        color,
        COUNT(drumNo) AS drumCount
    FROM 
        [PlanningSys].[control].givenPlanning
    GROUP BY 
        planningNo, drumNo, color
) AS drcnt1
ON 
    gp1.planningNo = drcnt1.planningNo AND gp1.drumNo = drcnt1.drumNo
GROUP BY 
    gp1.planningNo, gp1.drumNo, gp1.color, gp1.pairNo, drcnt1.drumCount
)
SELECT 
g.id,
g.mcno,
g.planningNo,
FORMAT(g.createdAt,'dd-MMM-yy') as date,
d.JobNo,
CONCAT(d.Core, d.CP, ' X ', d.Sqmm) AS Size,
CONCAT(d.NoOfStr, '/', d.StrDia) AS condSize,
g.isMica,
d.CondType,
d.InsuType,
c.color,
d.PairNo,
g.cutLen,
g.noofDrums,
c.totalCore,
c.drCnt,
c.drCnt1,
g.drumNo as TakeUp,
LAG(CONCAT(g.drumNo,'_',g.pairNo)) OVER (ORDER BY g.color,g.planningNo,TRY_CAST(LEFT(g.drumNo, PATINDEX('%[^0-9]%', g.drumNo) - 1) AS INT),g.pairNo,g.cutLen) AS nextTakeUp
FROM 
[PlanningSys].[control].[data] d
JOIN 
[PlanningSys].[control].givenPlanning g ON d.id = g.iid
JOIN 
givenTbl c ON c.planningNo = g.planningNo AND c.drumNo = g.drumNo AND c.pairNo = g.pairNo
WHERE g.isProdComplete  = 0";

$run = sqlsrv_query($conn, $sql); 
$lastChangeRow = 'odd';
while($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)){
    $xx = explode("_", $row['nextTakeUp']);
    $row['nextTakeUp1'] = $row['TakeUp'] != $xx[0];
    $row['nextTakeUp'] = ($row['TakeUp'].'_'.$row['PairNo'] != $row['nextTakeUp']);
    $row['chk'] = '';
    $row['InsuType'] = str_replace("/% /", "", $row['InsuType']);
    $lastChangeRow = $row['nextTakeUp1'] ? ($lastChangeRow == 'even' ? 'odd' : 'even') : $lastChangeRow; 
    $row['cls'] = $lastChangeRow;
    $rows[] = $row;
}

// echo '<pre>';
// print_r($rows);
// echo '</pre>';

echo json_encode($rows);

}
 ?>