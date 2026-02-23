<?php 
include '../includes/dbcon45.php';

$rows = array();
$finalArr = array();
$returnArr = array();
// $data = [
//     "0.5" => [
//         "0" => ["P" => 24, "T" => 24, "Q" => 26],
//         "1" => ["P" => 12, "T" => 12, "Q" => 12]
//     ],
//     "0.63" => [
//         "0" => ["P" => 24, "T" => 24, "Q" => 26],
//         "1" => ["P" => 12, "T" => 12, "Q" => 12]
//     ],
//     "0.75" => [
//         "0" => ["P" => 24, "T" => 24, "Q" => 26],
//         "1" => ["P" => 12, "T" => 12.5, "Q" => 12.5]
//     ],
//     "1" => [
//         "0" => ["P" => 24, "T" => 25, "Q" => 26],
//         "1" => ["P" => 12.5, "T" => 12.5, "Q" => 12]
//     ],
//     "1.5" => [
//         "0" => ["P" => 24, "T" => 26, "Q" => 26],
//         "1" => ["P" => 12.5, "T" => 12.5, "Q" => 12]
//     ],
//     "2.5" => [
//         "0" => ["P" => 25, "T" => 26, "Q" => 26],
//         "1" => ["P" => 12.5, "T" => 12.5, "Q" => 12]
//     ],
//     "4" => [
//         "0" => ["P" => 25.5, "T" => 26, "Q" => 26],
//         "1" => ["P" => 12.5, "T" => 12.5, "Q" => 12]
//     ],
//     "6" => [
//         "0" => ["P" => 26, "T" => 26.5, "Q" => 26],
//         "1" => ["P" => 12, "T" => 12.5, "Q" => 12]
//     ],
//     "10" => [
//         "0" => ["P" => 26.5, "T" => 26.5, "Q" => 26],
//         "1" => ["P" => 12.5, "T" => 12.5, "Q" => 12]
//     ],
// ];

$drumCapArr = [
    '0.5' =>  [0 => 26.5, 1 => 12.5],
    '0.63' => [0 => 20.5, 1 => 12.5],
    '0.75' => [0 => 20.5, 1 => 12.5],
    '1' =>    [0 => 20.5, 1 => 12.5],
    '1.5' =>  [0 => 17.5, 1 => 12.5],
    '2.5' =>  [0 => 12.5, 1 => 9.5],
    '4' =>    [0 => 6, 1 => 6],
    '6' =>    [0 => 5, 1 => 5],
    '10' =>   [0 => 4, 1 => 4],
];
$jobs = $_POST['jobs'];
// $jobs = '';

$sql = "WITH givenDrums AS (
SELECT 
    iid,
    SUM(noofDrums) AS totalDrums
FROM [PlanningSys].[instru].[givenPlanning$] WHERE isProdComplete = 0 AND pairCode = 'A'
GROUP BY iid
)
SELECT
    d.id,
    DENSE_RANK() OVER (ORDER BY d.isBandM, d.isMica, d.CondType, d.InsuType, CONCAT(d.NoOfStr, '/', d.StrDia), CONCAT(d.InsuColor1,d.InsuColor2,d.InsuColor3,d.InsuColor4)) AS srNo,
    d.Sqmm,
    d.JobNo,
    CONCAT(d.Core, d.CP, ' X ', d.Sqmm) AS Size,
    d.NoOfStr,
    d.StrDia,
    d.isMica,
    d.CondType,
    d.InsuType,
    d.InsuColor1,
    d.InsuColor2,
    d.InsuColor3,
    d.InsuColor4,
    d.PairNo,
    d.OrdCutLength,
    d.planTol,
    d.PlanCutLen,
    d.drums - COALESCE(g.totalDrums, 0) AS drums,
    d.isBandM
FROM 
    [PlanningSys].[instru].[data$] d
LEFT JOIN 
    givenDrums g ON d.id = g.iid
WHERE 
    d.isDelete = 0 
    AND d.drums - COALESCE(g.totalDrums, 0) > 0
";
if (!empty($jobs)) {
   $jobList = implode(",", array_map(function($job) {
        return "'" . addslashes($job) . "'";
    }, $jobs));
    $sql .= " AND JobNo IN($jobList)";
}
$sql .= " ORDER BY srNo, PairNo, drums desc";
$run = sqlsrv_query($conn, $sql);
$x = 0;

// Process the data into the first array
while ($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)) {
    $nearestSqmm = findNearestKey($drumCapArr, $row['Sqmm']);
    $carryFrwDrum = $row['drums'];
    $drumcap = $drumCapArr[$nearestSqmm][$row['isMica']] * 1000;
    $cutLenPerDrum = floor($drumcap / $row['PlanCutLen']);

    $cleanedString = str_replace("/% /", "", $row['InsuType']);

    do {
        if ($carryFrwDrum > $cutLenPerDrum) {
            $pdrum = $cutLenPerDrum;
            $carryFrwDrum -= $cutLenPerDrum;
        } else {
            $pdrum = $carryFrwDrum;
            $carryFrwDrum = 0;
        }
        $x++;
        $row['drums'] = $pdrum;
        $row['DrumNo'] = $x; // Assign current drum number
        $row['InsuType'] = $cleanedString;
        $row['jrCap'] = $drumcap;
        $row['isMica'] = $row['isMica'] ? 'Mica' : '';
        $row['isBandM'] = $row['isBandM'] ? 'B' : '';

        $rows[] = $row;

    } while ($carryFrwDrum > 0);
}
// echo '<pre>';
// print_r($rows);
// echo '</pre>';
// exit;
// Process the second array
foreach ($rows as $key => $row) {
    if (isset($rows[$key + 1]) && $rows[$key]['srNo'] == $rows[$key + 1]['srNo']) {
        $finalArr[] = $rows[$key];

        $req = $rows[$key]['jrCap'];
        $act = $rows[$key]['PlanCutLen'] * $rows[$key]['drums'];
        $balQty = $req - $act;
        $nextLen = floor($balQty / $rows[$key + 1]['PlanCutLen']);

        if ($nextLen > 0) {
            if ($rows[$key + 1]['drums'] > $nextLen) {
                $rows[$key + 1]['drums'] -= $nextLen;
                $finalArr[] = array_merge($rows[$key + 1], [
                    'drums' => $nextLen,
                    'DrumNo' => $rows[$key]['DrumNo'],
                ]);
            } else {
                $rows[$key + 1]['jrCap'] = $rows[$key]['jrCap'] - $rows[$key]['PlanCutLen'] * $rows[$key]['drums'];
                $rows[$key + 1]['DrumNo'] = $rows[$key]['DrumNo'];
            }
        }

    } else {
        $finalArr[] = $rows[$key];
    }
}

// process Third array
$dr = 1;
$prevDrumNo = $finalArr[0]['DrumNo'];
$lastChangeRow = 'odd';
foreach ($finalArr as $key => $row) {
   if ($row['DrumNo'] != $prevDrumNo) {
        $dr++;
        $prevDrumNo = $row['DrumNo'];
        $lastChangeRow = $lastChangeRow == 'even' ? 'odd' : 'even';
    }
    $returnArr[] = array_merge($row,
                               [
                                'DrumNo' => $dr.$row['isBandM'],
                                'cls' => $lastChangeRow,
                                'chk' => '',
                                 'totalCore' => '=R'.($key+1).'*S'.($key+1).'',
                                 'PlanCutLen' => '=P'.($key+1).'*(1+Q'.($key+1).'*0.01)'
                               ]
                              );   
}
// Output the final array
// echo '<pre>';
// print_r($finalArr);
// echo '</pre>';

echo json_encode($returnArr);
?>
