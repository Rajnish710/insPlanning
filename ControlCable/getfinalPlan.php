<?php 
include '../includes/dbcon45.php';

$rows = array();
$finalArr = array();
$returnArr = array();
$drumCapArr = [
    "0.5" => [
        "0" => ["C" => 4],
        "1" => ["C" => 3]
    ],
    "0.75" => [
        "0" => ["C" => 4],
        "1" => ["C" => 3]
    ],
    "1" => [
        "0" => ["C" => 4],
        "1" => ["C" => 3]
    ],
    "1.5" => [
        "0" => ["C" => 3.15],
        "1" => ["C" => 2]
    ],
    "2.5" => [
        "0" => ["C" => 3.15],
        "1" => ["C" => 2]
    ],
    "4" => [
        "0" => ["C" => 2.1],
        "1" => ["C" => 1]
    ],
    "6" => [
        "0" => ["C" => 2],
        "1" => ["C" => 1]
    ],
    "10" => [
        "0" => ["C" => 2],
        "1" => ["C" => 1]
    ],
];

$jobs = $_POST['jobs'];
// $jobs = ['TOSHIBA/16/1','TOSHIBA/16/2'];
// $jobs = ['NEUMAN/4/1','NEUMAN/4/2'];
//$jobs = ['SHIPL/5/1'];

$sql = "
WITH givenDrums AS (
    SELECT 
        iid,
        SUM(noofDrums) AS totalDrums
    FROM [PlanningSys].[control].[givenPlanning] 
    WHERE isProdComplete = 0
    GROUP BY iid
),
baseData AS (
    SELECT
        d.*,
        DENSE_RANK() OVER (ORDER BY d.isMica, d.CondType, d.InsuType, CONCAT(d.NoOfStr, '/', d.StrDia), CONCAT(d.InsuColor1,d.InsuColor2,d.InsuColor3,d.InsuColor4,d.InsuColor5)) AS srNo,
        d.drums - COALESCE(g.totalDrums, 0) AS remainingDrums
    FROM 
        [PlanningSys].[control].[data] d
    LEFT JOIN 
        givenDrums g ON d.id = g.iid
    WHERE 
        d.isDelete = 0 
        AND d.drums - COALESCE(g.totalDrums, 0) > 0
";

// Add dynamic JobNo IN clause safely
$topRow = " TOP 100 ";
if (!empty($jobs)) {
    $topRow = "";
    $jobList = implode(",", array_map(function($job) {
        return "'" . addslashes($job) . "'";
    }, $jobs));
    $sql .= " AND d.JobNo IN ($jobList)";
}

$sql .= "
)
SELECT $topRow
    bd.id,
    bd.srNo,
    bd.Sqmm,
    bd.CP,
    bd.JobNo,
    CONCAT(bd.Core, bd.CP, ' X ', bd.Sqmm) AS Size,
    bd.NoOfStr,
    bd.StrDia,
    bd.isMica,
    bd.CondType,
    bd.InsuType,
    bd.InsuColor1,
    bd.InsuColor2,
    bd.InsuColor3,
    bd.InsuColor4,
    bd.InsuColor5,
    bd.PairNo,
    bd.OrdCutLength,
    bd.planTol,
    bd.PlanCutLen,
    bd.remainingDrums AS drums,
    SUM(bd.PlanCutLen * bd.remainingDrums) OVER (PARTITION BY bd.srNo, bd.PairNo) AS TotalPlanCutLenDrums
FROM 
    baseData bd
ORDER BY 
    bd.srNo, bd.PairNo, CAST(bd.Core as float) DESC,bd.remainingDrums DESC;
";
// echo $sql;
// exit;
$run = sqlsrv_query($conn, $sql);
$x = 0;
 // <-- ---------------------------------------------------------------- -->
 // <-- 1. Process First Array
 // <-- This loop only splits a single row into multiple rows when its quantity exceeds the drum capacity.
 // <-- ----------------------------------------------------------------   -->
while ($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)) {
    $cp = $row['CP'];
    unset($row['CP']);
    $nearestSqmm = findNearestKey($drumCapArr, $row['Sqmm']);
    $carryFrwDrum = $row['drums'];
    $drumcap = $drumCapArr[$nearestSqmm][$row['isMica']][$cp] * 1000;
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

        $rows[] = $row;

    } while ($carryFrwDrum > 0);
}
 // <-- ---------------------------------------------------------------- -->
 // <-- 2. Process Second Array
 // <-- This loop splits AND assign drumNO.
 // <-- ----------------------------------------------------------------   -->
$finalArr = [];
for ($i = 0; $i < count($rows); $i++) {
    if (isset($rows[$i + 1]) && $rows[$i]['srNo'] == $rows[$i + 1]['srNo']) {

        $requiredCapacity = $rows[$i]['jrCap'];
        $actualUsed = $rows[$i]['PlanCutLen'] * $rows[$i]['drums'];

        $isPairDifferent = $rows[$i]['PairNo'] !== $rows[$i + 1]['PairNo'];
        // $isNextQtySufficient = $requiredCapacity >= $rows[$i + 1]['TotalPlanCutLenDrums'];
        $isNextQtySufficient = ($requiredCapacity - $actualUsed) >= $rows[$i + 1]['TotalPlanCutLenDrums'];

        // Calculate remaining capacity (balance)
        $balanceQty = ($isPairDifferent && !$isNextQtySufficient) ? 0 : ($requiredCapacity - $actualUsed);

        // How many full lengths can fit into the balance
        $fitDrumsInBalance = floor($balanceQty / $rows[$i + 1]['PlanCutLen']);

        if ($fitDrumsInBalance > 0) {
            if ($rows[$i + 1]['drums'] > $fitDrumsInBalance) {
                // Split the next row
                $splitRow = $rows[$i + 1];
                $splitRow['drums'] = $fitDrumsInBalance;

                // Reduce drums in the original next row
                $rows[$i + 1]['drums'] -= $fitDrumsInBalance;

                // Insert the split row before the next row
                array_splice($rows, $i + 1, 0, [$splitRow]);

                // Re-process this row with updated structure
                $i--;
                continue;

            } else {
                // Carry forward remaining capacity and drum number
                $rows[$i + 1]['jrCap'] = $requiredCapacity - $actualUsed;
                $rows[$i + 1]['DrumNo'] = $rows[$i]['DrumNo'];
            }
        }

        // Append current processed row
        $finalArr[] = $rows[$i];

    } else {
        // Append unmatched row as-is
        $finalArr[] = $rows[$i];
    }
}
 // <-- ---------------------------------------------------------------- -->
 // <-- 3. Process Third Array
 // <-- This loop dedect drumno for highlight
 // <-- ----------------------------------------------------------------   -->
$drumCounter = 1;
$previousDrumNo = $finalArr[0]['DrumNo'] ?? null;  // Avoid undefined index
$currentRowClass = 'odd';
$returnArr = [];

foreach ($finalArr as $index => $row) {
    unset($row['TotalPlanCutLenDrums']); // Clean unwanted key

    // Detect drum number change
    if ($row['DrumNo'] !== $previousDrumNo) {
        $drumCounter++;
        $previousDrumNo = $row['DrumNo'];
        $currentRowClass = ($currentRowClass === 'even') ? 'odd' : 'even';
    }

    // Merge enhanced data into row
    $returnArr[] = array_merge($row, [
        'DrumNo'     => $drumCounter,
        'cls'        => $currentRowClass,
        'chk'        => '0',
        'totalCore'  => '=S' . ($index + 1) . '*T' . ($index + 1),
        'PlanCutLen' => '=Q' . ($index + 1) . '*(1+R' . ($index + 1) . '*0.01)',
    ]);
}
// Output the final array
// echo '<pre>';
// print_r($rows);
// echo '</pre>';

echo json_encode($returnArr);
?>
