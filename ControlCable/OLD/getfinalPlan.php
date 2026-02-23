<?php
$title = "Instru Cable Plan";
include '../includes/dbcon45.php';
date_default_timezone_set('Asia/Kolkata');
$logMessage = sprintf("\n\n[%s] local.INFO: ", date('Y-m-d H:i:s'));
	// error_log($logMessage.$key.'--'.$act, 3, "error.log");
$rows = array();
$finalArr = array();
$drumCapArr = [
0.5 =>  [0 => 30, 1 => 25],
0.75 => [0 => 25, 1 => 20],
1 =>    [0 => 25, 1 => 20],
1.5 =>  [0 => 18, 1 => 12],
2.5 =>  [0 => 12, 1 => 10],
4 =>    [0 => 6, 1 => 6],
6 =>    [0 => 5, 1 => 5],
10 =>   [0 => 4, 1 => 4],
];

$sql = "WITH FirstTable AS (
    SELECT
        id,
        DENSE_RANK() OVER (ORDER BY McNo, isMica, CondType, InsuType, CONCAT(NoOfStr,'/',StrDia), InsuColor) AS srNo,
        McNo,
        JobNo,
        CONCAT(Core, CP,' X ',Sqmm) AS CPsize,
        Sqmm,
        CondType,
        InsuType,
        PairNo,
        InsuColor,
        CONCAT(NoOfStr,'/',StrDia) AS size,
        isMica,
        isBandM,
		isNoPrintOnCore,
        PlanCutLen,
        (plan_NoofDrums * multiplyDrums) AS noOfDrums
    FROM 
        [PlanningSys].[instru].[data]
    WHERE 
        isGiven = 1 AND isDelete = 0
),
SecondTableAggregated AS (
    SELECT 
        iid, 
        SUM(noOfDrums) AS SumNoOfDrums
    FROM 
        [PlanningSys].[instru].[givenPlanning]
    GROUP BY 
        iid
)
SELECT 
    f.id,
    f.srNo,
    f.McNo,
    f.JobNo,
    f.CPsize,
    f.Sqmm,
    f.CondType,
    f.InsuType,
    f.PairNo,
    f.InsuColor,
    f.size,
    f.isMica,
    f.isBandM,
	f.isNoPrintOnCore,
    f.PlanCutLen,
    (f.noOfDrums - ISNULL(s.SumNoOfDrums, 0)) AS noOfDrums
FROM 
    FirstTable f
LEFT JOIN 
    SecondTableAggregated s 
    ON f.id = s.iid
    WHERE (f.noOfDrums - ISNULL(s.SumNoOfDrums, 0)) <> 0
ORDER BY 
    f.McNo, f.isMica, f.CondType, f.InsuType, f.size, f.InsuColor;
";

// echo $sql;
// exit;
$run = sqlsrv_query($conn,$sql);
$srNo = 0;
while($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)){
	$carryFrwDrum = $row['noOfDrums'];
	$drumcap = $drumCapArr[$row['Sqmm']][$row['isMica']]*1000;
	$cutLenPerDrum = floor($drumcap / $row['PlanCutLen']);

    $string = $row['InsuType'];
    $cleanedString = str_replace("/% /", "", $string);

	do {
	if ($carryFrwDrum > $cutLenPerDrum) {
		$pdrum = $cutLenPerDrum;
		$carryFrwDrum = $carryFrwDrum - $cutLenPerDrum;
	} else {
		$pdrum = $carryFrwDrum;
		$carryFrwDrum = 0;
	}
$srNo++;
	$row['noOfDrums'] = $pdrum;
	$row['DrumNo'] = 'Drum-'.$srNo;
	$row['chk'] = '';
    $row['BandMark'] = '';
    $row['NoPrint'] = '';
    $row['InsuType'] = $cleanedString;
    $row['totalCore'] = '=M'.$srNo.'*N'.$srNo.'';
    
$rows[] = $row;
} while ($carryFrwDrum > 0);
}
// second array
foreach ($rows as $key => $row) {
if (isset($rows[$key + 1])) {
		$finalArr[] = $rows[$key];
	if ($rows[$key]['srNo'] == $rows[$key + 1]['srNo']) {
		$req = $drumCapArr[$row['Sqmm']][$row['isMica']]*1000;
		$act = $rows[$key]['PlanCutLen']*$rows[$key]['noOfDrums'];
		$balQty = $req - $act;
		$nextLen = floor($balQty / $rows[$key + 1]['PlanCutLen']);
		if ($nextLen > 0) {
			if ($rows[$key + 1]['noOfDrums'] > $nextLen) {
				$rows[$key + 1]['noOfDrums'] = $rows[$key + 1]['noOfDrums'] - $nextLen;
				$finalArr[] = array(
                    'id' => $rows[$key + 1]['id'],
                    'srNo' => $rows[$key + 1]['srNo'],
                    'McNo' => $rows[$key + 1]['McNo'],
                    'JobNo' => $rows[$key + 1]['JobNo'],
                    'CPsize' => $rows[$key + 1]['CPsize'],
                    'Sqmm' => $rows[$key + 1]['Sqmm'],
                    'CondType' => $rows[$key + 1]['CondType'],
                    'InsuType' =>  $rows[$key + 1]['InsuType'],
                    'PairNo' => $rows[$key + 1]['PairNo'],
                    'InsuColor' => $rows[$key + 1]['InsuColor'],
                    'size' => $rows[$key + 1]['size'],
                    'isMica' => $rows[$key + 1]['isMica'],
                    'PlanCutLen' => $rows[$key + 1]['PlanCutLen'],
                    'noOfDrums' => $nextLen,
                    'DrumNo' => $rows[$key]['DrumNo'],
                    'isBandM' => $rows[$key + 1]['isBandM'],
                    'isNoPrintOnCore' => $rows[$key + 1]['isNoPrintOnCore'],
                    'chk' => '',
                    'totalCore' => $rows[$key + 1]['totalCore'],
                    'BandMark' => '',
                    'NoPrint' => ''
                    );
			}else{
				$rows[$key + 1]['DrumNo'] = $rows[$key]['DrumNo'];
			}
		}
	}
}else{
	$finalArr[] = $rows[$key];
}
}
// Third Array
$x = 0;
$groupedData = [];
foreach ($finalArr as $row) {	
    $key = $row['id'] . '-' . $row['DrumNo'] . '-' . $row['PlanCutLen'];
    if (!isset($groupedData[$key])) {
    	$x++;
    	$groupedData[$key] = $row;
    } else {
        $groupedData[$key]['noOfDrums'] += $row['noOfDrums'];
    }
}
$groupedData = array_values($groupedData);
// echo '<pre>';
// 	print_r($finalArr);
// echo '</pre>';
echo json_encode($groupedData);
?>