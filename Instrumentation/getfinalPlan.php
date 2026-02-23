<?php 
include '../includes/dbcon45.php';

$rows = array();
$finalArr = array();
$returnArr = array();
$drumCapArr = [
    "0.5" => [
        "0" => ["P" => 24, "T" => 24, "Q" => 26],
        "1" => ["P" => 12, "T" => 12, "Q" => 12]
    ],
    "0.63" => [
        "0" => ["P" => 24, "T" => 24, "Q" => 26],
        "1" => ["P" => 12, "T" => 12, "Q" => 12]
    ],
    "0.75" => [
        "0" => ["P" => 18, "T" => 18, "Q" => 20],
        "1" => ["P" => 12, "T" => 12.5, "Q" => 12.5]
    ],
    "1" => [
        "0" => ["P" => 18, "T" => 20, "Q" => 20],
        "1" => ["P" => 12.5, "T" => 12, "Q" => 12]
    ],
    "1.5" => [
        "0" => ["P" => 17.5, "T" => 16, "Q" => 16.5],
        "1" => ["P" => 12.5, "T" => 12, "Q" => 12]
    ],
    "2.5" => [
        "0" => ["P" => 12.5, "T" => 12, "Q" => 12],
        "1" => ["P" => 8, "T" => 9, "Q" => 9]
    ],
    "4" => [
        "0" => ["P" => 6, "T" => 6, "Q" => 6],
        "1" => ["P" => 6, "T" => 6, "Q" => 6]
    ],
    "6" => [
        "0" => ["P" => 5, "T" => 5, "Q" => 5],
        "1" => ["P" => 4.5, "T" => 5, "Q" => 4.5]
    ],
    "10" => [
        "0" => ["P" => 4, "T" => 4, "Q" => 4],
        "1" => ["P" => 4, "T" => 4, "Q" => 4]
    ],
];

$jobs = $_POST['jobs'];
// $jobs = ['ACC/2/2','ACC/2/3','ACC/2/4'];
// $jobs = ['NEUMAN/4/1','NEUMAN/4/2'];
// $jobs = ['NAYARA/12/22'];

$sql = "
WITH givenDrums AS (
    SELECT 
        iid,
        SUM(noofDrums) AS totalDrums
    FROM [PlanningSys].[instru].[givenPlanning] 
    WHERE isProdComplete = 0 AND pairCode = 'A'
    GROUP BY iid
),
baseData AS (
    SELECT
        d.*,
        DENSE_RANK() OVER (ORDER BY d.isBandM, d.isMica, d.CondType, d.InsuType, CONCAT(d.NoOfStr, '/', d.StrDia), CONCAT(d.InsuColor1,d.InsuColor2,d.InsuColor3,d.InsuColor4)) AS srNo,
        d.drums - COALESCE(g.totalDrums, 0) AS remainingDrums
    FROM 
        [PlanningSys].[instru].[data] d
    LEFT JOIN 
        givenDrums g ON d.id = g.iid
    WHERE 
        d.isDelete = 0 
        AND d.drums - COALESCE(g.totalDrums, 0) > 0
";

// Add dynamic JobNo IN clause safely
$topRow = " TOP 1000 ";
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
    bd.PairNo,
    bd.OrdCutLength,
    bd.planTol,
    bd.PlanCutLen,
    bd.remainingDrums AS drums,
    bd.isBandM,
    bd.insuStartDate,
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
    $row['insuStartDate'] = $row['insuStartDate'] ? $row['insuStartDate']->format('Y-m-d') : '';
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
        $row['isBandM'] = $row['isBandM'] ? 'B' : '';

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
        'DrumNo'     => $drumCounter . $row['isBandM'],
        'cls'        => $currentRowClass,
        'chk'        => '0',
        'totalCore'  => '=R' . ($index + 1) . '*S' . ($index + 1),
        'PlanCutLen' => '=P' . ($index + 1) . '*(1+Q' . ($index + 1) . '*0.01)',
    ]);
}
// Output the final array
// echo '<pre>';
// print_r($rows);
// echo '</pre>';

echo json_encode($returnArr);
?>
