<?php
include('../includes/dbcon45.php');
date_default_timezone_set('Asia/Kolkata');

// === CONFIG ===
$errorEmail = "alert@example.com"; // <<<< Replace with your alert email
$logFile = "error.log";
$logTime = date('Y-m-d H:i:s');
$logPrefix = "[$logTime] local.ERROR: ";

// === HELPER: Log + Email on Fatal ===
function handleCriticalError($message, $sendEmail = true) {
    global $logPrefix, $logFile, $errorEmail;
    $logMessage = $logPrefix . $message . PHP_EOL;
    error_log($logMessage, 3, $logFile);

    if ($sendEmail) {
        $subject = "URGENT: Planning Job Failure";
        $headers = "From: noreply@yourdomain.com\r\n";
        mail($errorEmail, $subject, $message, $headers);
    }
    die("Critical error occurred. Please check logs.");
}

// === DB CHECK ===
if (!$conn) handleCriticalError("Database connection failed.");

// === CONSTANTS ===
$condType = ['A', 'B', 'C', 'B/C', '50%B & 50%C'];
$sqmmArr = ['0.5', '0.75', '1', '1.5', '2.5', '4', '6', '10'];
$rsArr = ["Thousand" => 0.01, "Lacs" => 1, "Crore" => 100];
$numberOnCore = [
    "numbering on each core & tape",
    "numbering on insulation",
    "numbering on prmary insulation & tape",
    "Colour Coded With Numbring",
    "Numbering on tape & insulation",
    "Numbering on each core & on tape",
    "Colour code with numbering on insulation",
    "Colour code with numbering on tape & insulation"
];

// === SQL TO FETCH JOBS ===
$params = [];
$options = ["Scrollable" => SQLSRV_CURSOR_KEYSET];
$sql = "SELECT
    a.ordid, a.JobNo, a.Core, a.CorePair, a.SQMM, a.Remark, a.Qty, a.CableType,
    a.CableCat, a.Condtypep, ISNULL(e.Grade, d.condgrd) AS condgrd,
    CASE WHEN d.insgrd2 = '' THEN d.insgrd1 ELSE CONCAT(d.insgrd1, ' /', d.insper, '% /', d.insgrd2) END AS insGrade,
    a.BasicRate, CONVERT(VARCHAR(11), CONVERT(DATE, b.Plnsentto, 103), 106) AS Plnsentto,
    b.OfferNo, b.TolM, b.TolP, b.Value, b.RsSign, b.LD, b.LDASPER, b.LDSDate,
    CONVERT(VARCHAR(11), b.SiteDate, 106) AS SiteDate,
    a.ColorType, a.Col1, a.Col2, a.Col3, a.Col4,
    ISNULL(e.drums, c.NoofDrum) AS NoofDrum,
    c.CQty
FROM OrdDetail a
JOIN OrdMaster b ON a.OrdID = b.OrdID
JOIN CutLength c ON a.JobNo = c.JobNo
JOIN [backward_calc].[dbo].pvcGrade d ON a.JobNo = d.Job
LEFT JOIN (
    SELECT cutLenId, drums, Grade FROM [backward_calc].[dbo].condGrade WHERE isdelete = 0
) e ON e.cutLenId = c.IID
WHERE a.OrdID >= 1984990888
AND a.InsTypeP <> ''
AND a.CorePair <> 'C'
AND NOT EXISTS (SELECT 1 FROM [PlanningSys].[instru].[data] pd WHERE pd.JobNo = a.JobNo)
AND a.JobNo IN (SELECT JobNo FROM [RunningJobs].[dbo].[RunningJobs] WHERE isFinalComplete = 0)
ORDER BY b.SiteDate, a.JobNo";

$run = sqlsrv_query($conn, $sql, $params, $options);
if ($run === false) handleCriticalError("Main SQL failed: " . print_r(sqlsrv_errors(), true));

while ($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)) {
    $job = $row['JobNo'];

    // Begin TRY-CATCH block via SQL Server
    if (!sqlsrv_query($conn, "BEGIN TRY BEGIN TRANSACTION")) {
        handleCriticalError("Could not start transaction for JobNo: $job");
        continue;
    }

    try {
        $planTol = 2;
        $planLen = ceil($row['CQty'] * 1.02);

        $tolSQL = "SELECT per, CEILING(? * (1 + per * 0.01)) AS newLen FROM [PlanningSys].[master].[qtyTol]
                   WHERE ? BETWEEN lenFrom AND lenTo AND minus = ? AND plus = ?";
        $tolRes = sqlsrv_query($conn, $tolSQL, [$row['CQty'], $row['CQty'], $row['TolM'], $row['TolP']], $options);
        if ($tolRes && sqlsrv_has_rows($tolRes)) {
            $tolRow = sqlsrv_fetch_array($tolRes, SQLSRV_FETCH_ASSOC);
            $planTol = $tolRow['per'];
            $planLen = $tolRow['newLen'];
        }

        $type = '';
        $noOfStr = 0;
        $strDia = 0;
        if (stripos($row['Condtypep'], 'Stranded') !== false) {
            $type = 'Stranded'; $noOfStr = 7;
        } elseif (stripos($row['Condtypep'], 'Solid') !== false) {
            $type = 'Solid'; $noOfStr = 1;
        }

        if ($type && in_array($row['condgrd'], $condType) && in_array($row['SQMM'], $sqmmArr)) {
            $csSQL = "SELECT [{$row['condgrd']}] AS grd FROM [PlanningSys].[master].[condSize] WHERE type = ? AND Size = ?";
            $csRes = sqlsrv_query($conn, $csSQL, [$type, $row['SQMM']]);
            if ($csRes && ($csRow = sqlsrv_fetch_array($csRes, SQLSRV_FETCH_ASSOC))) {
                $strDia = $csRow['grd'];
            }
        }

        $siteDate = new DateTime($row['SiteDate']);
        $plnSent = new DateTime($row['Plnsentto']);
        $daysDiff = $siteDate->diff($plnSent)->days;
        $insuReqDate = ($daysDiff > 60) ? $siteDate->modify('-45 days')->format('Y-m-d') : $siteDate->modify('-22 days')->format('Y-m-d');

        $ldWeekPer = (preg_match('/([\d.]+)%/', $row['LD'], $match)) ? (float)$match[1] : 0;
        $offerVal = isset($rsArr[$row['RsSign']]) ? $rsArr[$row['RsSign']] * $row['Value'] : 0;

        $isMica = ($row['Remark'] === 'Yes') ? 1 : 0;
        $isBandM = ($row['CableType'] === 'BHEL Unit Form' || $row['ColorType'] === 'Colour Coded with Band - Marking') ? 1 : 0;
        $isNoPrintOnCore = in_array($row['ColorType'], $numberOnCore) ? 1 : 0;

        $drawingCap = $tinningCap = $bunchCap = $insuCap = 0;
        $capSQL = "SELECT TOP 1 * FROM [PlanningSys].[master].[insuCapacity] ORDER BY ABS(? - size)";
        $capRes = sqlsrv_query($conn, $capSQL, [$row['SQMM']]);
        if ($capRes && ($capRow = sqlsrv_fetch_array($capRes, SQLSRV_FETCH_ASSOC))) {
            $mtr = $isMica ? $capRow['insuCapMica'] : $capRow['insuCapCore'];
            $drawingCap = round((540 / $capRow['drawKgs']) * $capRow['weight'], 4);
            $tinningCap = round((540 / $capRow['TinKgs']) * $capRow['weight'], 4);
            $bunchCap = round((540 / $capRow['bunchKgs']) * $capRow['weight'], 4);
            $insuCap = round((540 / $mtr) * 1000, 4);
        }

        $baseData = [
            'DeliveryDate' => $row['SiteDate'],
            'JobNo' => $job,
            'Core' => $row['Core'],
            'CP' => $row['CorePair'],
            'Sqmm' => $row['SQMM'],
            'isMica' => $isMica,
            'OrderQty' => $row['Qty'],
            'TolM' => $row['TolM'],
            'TolP' => $row['TolP'],
            'planTol' => $planTol,
            'OrdCutLength' => $row['CQty'],
            'ord_NoofDrums' => $row['NoofDrum'],
            'CondGrade' => $row['condgrd'],
            'CondType' => $row['Condtypep'],
            'InsuType' => $row['insGrade'],
            'NoOfStr' => $noOfStr,
            'StrDia' => $strDia,
            'PlanCutLen' => $planLen,
            'plan_NoofDrums' => $row['NoofDrum'],
            'PlanningDate' => date('Y-m-d'),
            'RequiredDate' => $insuReqDate,
            'ordID' => $row['ordid'],
            'cableCat' => $row['CableCat'],
            'enqNo' => $row['OfferNo'],
            'valueAmt' => $offerVal,
            'LdPer' => $ldWeekPer,
            'LdTerm' => $row['LDASPER'],
            'createdAt' => date('Y-m-d H:i:s'),
            'createdBy' => '',
            'drawingCap' => $drawingCap,
            'tinningCap' => $tinningCap,
            'bunchCap' => $bunchCap,
            'insuCap' => $insuCap,
            'isBandM' => $isBandM,
            'isNoPrintOnCore' => $isNoPrintOnCore
        ];

        insertPlanningJob($conn, $baseData, $row, $isNoPrintOnCore, $isBandM);

        sqlsrv_query($conn, "COMMIT TRANSACTION");
    } catch (Throwable $e) {
        sqlsrv_query($conn, "ROLLBACK TRANSACTION");
        handleCriticalError("Rolled back JobNo $job: " . $e->getMessage());
    }
}

// === INSERT FUNCTION ===
function insertPlanningJob($conn, $baseData, $row, $isNoPrintOnCore, $isBandM) {
    $job = $row['JobNo'];
    $query = "SELECT JobNo, 1 AS ColorCount, color_1, color_2, color_3, core FROM OrdColorIns WHERE JobNo = ?";
    $res = sqlsrv_query($conn, $query, [$job], ["Scrollable" => SQLSRV_CURSOR_KEYSET]);
    $inserted = false;

    if ($isNoPrintOnCore || $isBandM) {
        if ($res && sqlsrv_has_rows($res)) {
            while ($r = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
                $data = array_merge($baseData, [
                    'multiplyDrums' => $r['ColorCount'],
                    'InsuColor1' => $r['color_1'],
                    'InsuColor2' => $r['color_2'],
                    'InsuColor3' => $r['color_3'],
                    'PairNo' => $r['core']
                ]);
                executeInsert($conn, $data);
                $inserted = true;
            }
        } else {
            for ($j = 1; $j <= intval($row['Core']); $j++) {
                $data = array_merge($baseData, [
                    'multiplyDrums' => 1,
                    'InsuColor1' => $row['Col1'],
                    'InsuColor2' => $row['Col2'],
                    'InsuColor3' => $row['Col3'],
                    'InsuColor4' => $row['Col4'],
                    'PairNo' => $j
                ]);
                executeInsert($conn, $data);
            }
            $inserted = true;
        }
    } else {
        if ($res && sqlsrv_has_rows($res)) {
            while ($r = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
                $data = array_merge($baseData, [
                    'multiplyDrums' => $r['ColorCount'],
                    'InsuColor1' => $r['color_1'],
                    'InsuColor2' => $r['color_2'],
                    'InsuColor3' => $r['color_3'],
                    'PairNo' => 0
                ]);
                executeInsert($conn, $data);
            }
            $inserted = true;
        } else {
            $data = array_merge($baseData, [
                'multiplyDrums' => $row['Core'],
                'InsuColor1' => $row['Col1'],
                'InsuColor2' => $row['Col2'],
                'InsuColor3' => $row['Col3'],
                'InsuColor4' => $row['Col4'],
                'PairNo' => 0
            ]);
            executeInsert($conn, $data);
            $inserted = true;
        }
    }

    if (!$inserted) throw new Exception("No color data inserted for JobNo $job");
}

function executeInsert($conn, $data) {
    $columns = array_keys($data);
    $placeholders = implode(', ', array_map(fn($c) => "[$c]", $columns));
    $params = array_values($data);
    $sql = "INSERT INTO [PlanningSys].[instru].[data] ($placeholders) VALUES (" . implode(',', array_fill(0, count($params), '?')) . ")";
    $stmt = sqlsrv_prepare($conn, $sql, $params);
    if (!$stmt || !sqlsrv_execute($stmt)) {
        throw new Exception("Insert failed: " . print_r(sqlsrv_errors(), true));
    }
}

echo "Data processing complete. Check logs for any failures.";
?>
