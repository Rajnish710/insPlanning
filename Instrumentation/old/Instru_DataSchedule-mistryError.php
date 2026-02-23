<?php
include('../includes/dbcon45.php');
date_default_timezone_set('Asia/Kolkata');
print_r(sqlsrv_client_info($conn));
print_r(sqlsrv_server_info($conn));
exit;
// Helper function
function calculateRequiredDate($siteDate, $plnSentTo) {
    if (!$siteDate || !$plnSentTo) return date('Y-m-d', strtotime('+5 days'));
    $origin1 = date_create($siteDate);
    $target1 = date_create($plnSentTo);                   
    $diff = date_diff($origin1, $target1)->format("%a");
    return ($target1 && $diff > 60) ? date('Y-m-d', strtotime($siteDate . ' -45 days'))
                                    : date('Y-m-d', strtotime($plnSentTo . ' -22 days'));
}

// Config
$numberOnCore = [
   "numbering on each core & tape", "numbering on insulation",
   "numbering on prmary insulation & tape", "Colour Coded With Numbring",
   "Numbering on tape & insulation", "Numbering on each core & on tape",
   "Colour code with numbering on insulation", "Colour code with numbering on tape & insulation"
];
$rsArr = ["Thousand" => 0.01, "Lacs" => 1, "Crore" => 100];
$logFile = "error.log";

try {
    // Static data
    $condSizeMap = [];
    $sqlCondSize = "SELECT type, Size, A, B, C, [B/C], [50%B & 50%C] FROM [PlanningSys].[master].[condSize]";
    $runCondSize = sqlsrv_query($conn, $sqlCondSize);
    if (!$runCondSize) throw new Exception("Failed to fetch condSize data");
    while ($rowCond = sqlsrv_fetch_array($runCondSize, SQLSRV_FETCH_ASSOC)) {
        $type = $rowCond['type'];
        $size = $rowCond['Size'];
        unset($rowCond['type'], $rowCond['Size']);
        $condSizeMap[$type][$size] = $rowCond;
    }

    $colorData = [];
    $sqlColor = "SELECT JobNo, color_1, color_2, color_3, core FROM OrdColorIns";
    $runColor = sqlsrv_query($conn, $sqlColor);
    if (!$runColor) throw new Exception("Failed to fetch color data");
    while ($rowColor = sqlsrv_fetch_array($runColor, SQLSRV_FETCH_ASSOC)) {
        $jobNo = $rowColor['JobNo'];
        $colorData[$jobNo][] = $rowColor;
    }

    $sql = "SELECT 
    a.ordid,
    a.JobNo,
    a.Core,
    a.CorePair,
    a.SQMM,
    a.Remark,
    a.Qty,
    a.CableType,
    a.CableCat,
    a.Condtypep,
    ISNULL(e.Grade, d.condgrd) AS condgrd,
    CASE 
        WHEN d.insgrd2 = '' THEN d.insgrd1
        ELSE CONCAT(d.insgrd1, ' /', d.insper, '% /', d.insgrd2)
    END AS insGrade,
    a.BasicRate,
    CONVERT(VARCHAR(11), CONVERT(DATE, b.Plnsentto, 103), 106) AS Plnsentto,
    b.OfferNo,
    b.TolM,
    b.TolP,
    b.Value,
    b.RsSign,
    b.LD,
    b.LDASPER,
    b.LDSDate,
    CONVERT(VARCHAR(11), b.SiteDate, 120) AS SiteDate,
    a.ColorType,
    a.Col1,
    a.Col2,
    a.Col3,
    a.Col4,
    ISNULL(e.drums, c.NoofDrum) AS NoofDrum,
    c.CQty,
    COALESCE(qt.per, 2) AS planTol,
    CEILING(c.CQty * (1 + COALESCE(qt.per, 2) * 0.01)) AS planLen

FROM OrdDetail a

JOIN OrdMaster b 
    ON a.OrdID = b.OrdID

JOIN CutLength c 
    ON a.JobNo = c.JobNo

JOIN [backward_calc].[dbo].pvcGrade d 
    ON a.JobNo = d.Job

LEFT JOIN [backward_calc].[dbo].condGrade e 
    ON e.cutLenId = c.IID 
    AND e.isdelete = 0

LEFT JOIN [PlanningSys].[master].[qtyTol] qt 
    ON c.CQty BETWEEN qt.lenFrom AND qt.lenTo 
    AND qt.minus = b.TolM 
    AND qt.plus = b.TolP

WHERE 
    a.OrdID >= 1984990888
    AND a.InsTypeP <> ''
    AND a.CorePair <> 'C'
    AND NOT EXISTS (
        SELECT 1 
        FROM [PlanningSys].[instru].[data] pd 
        WHERE pd.JobNo = a.JobNo
    )
    AND a.JobNo IN (
        SELECT JobNo 
        FROM [RunningJobs].[dbo].[RunningJobs] 
        WHERE isFinalComplete = 0
    )

ORDER BY 
    b.SiteDate, 
    a.JobNo;
";

    $run = sqlsrv_query($conn, $sql);
    if (!$run) throw new Exception("Failed to execute main query: " . print_r(sqlsrv_errors(), true));

    $insertSql = "INSERT INTO [PlanningSys].[instru].[data] (
        DeliveryDate, JobNo, Core, CP, Sqmm, isMica, OrderQty, TolM, TolP, planTol,
        OrdCutLength, ord_NoofDrums, CondGrade, CondType, InsuType, NoOfStr, StrDia, PlanCutLen,
        plan_NoofDrums, multiplyDrums, InsuColor1, InsuColor2, InsuColor3, InsuColor4, PairNo,
        PlanningDate, RequiredDate, ordID, cableCat, enqNo, valueAmt, LdPer, LdTerm,
        createdAt, createdBy, isBandM, isNoPrintOnCore
    ) VALUES (" . implode(",", array_fill(0, 38, "?")) . ")";
    
    $stmt = sqlsrv_prepare($conn, $insertSql);
    if (!$stmt) throw new Exception("Failed to prepare insert statement");

    sqlsrv_begin_transaction($conn);

    while ($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)) {
        $isMica = ($row['Remark'] == 'Yes') ? 1 : 0;
        $isBandM = ($row['CableType'] == 'BHEL Unit Form' || $row['ColorType'] == 'Colour Coded with Band - Marking') ? 1 : 0;
        $isNoPrintOnCore = in_array($row['ColorType'], $numberOnCore) ? 1 : 0;

        $condgrd = $row['condgrd'];
        $sqmmVal = $row['SQMM'];
        $type = preg_match('/Stranded/', $row['Condtypep']) ? 'Stranded' : (preg_match('/Solid/', $row['Condtypep']) ? 'Solid' : '');
        $strDia = ($type && isset($condSizeMap[$type][$sqmmVal][$condgrd])) ? $condSizeMap[$type][$sqmmVal][$condgrd] : 0;

        preg_match('/([\d.]+)%/', $row['LD'], $matches);
        $ldPer = $matches[1] ?? 0;

        $offerVal = isset($rsArr[$row['RsSign']]) ? $rsArr[$row['RsSign']] * $row['Value'] : 0;
        $siteDate = $row['SiteDate'];
        $plnsentto = $row['Plnsentto'];
        $requiredDate = calculateRequiredDate($siteDate, $plnsentto);
        $noOfStr = ($type === 'Stranded') ? 7 : (($type === 'Solid') ? 1 : 0);

        $colors = $colorData[$row['JobNo']] ?? [];
        $coreCount = (int)$row['Core'];

       $baseParams = [
            $row['SiteDate'], $row['JobNo'], $row['Core'], $row['CorePair'], $row['SQMM'],
            $isMica, $row['Qty'], $row['TolM'], $row['TolP'], $row['planTol'],
            $row['CQty'], $row['NoofDrum'], $row['condgrd'], $row['Condtypep'], $row['insGrade'],
            $noOfStr, $strDia, $row['planLen'], $row['NoofDrum'], '',
            '', '', '', '', '',
            date('Y-m-d'), $requiredDate, $row['ordid'], $row['CableCat'], $row['OfferNo'],
            $offerVal, $ldPer, $row['LDASPER'], date('Y-m-d H:i:s'),
            'system',  // ✅ Fix applied here — createdBy
            $isBandM, $isNoPrintOnCore
        ];

        if ($isNoPrintOnCore || $isBandM) {
            if (!empty($colors)) {
                foreach ($colors as $color) {
                        $params = $baseParams;
                        $params[19] = 1;
                        $params[20] = $color['color_1'];
                        $params[21] = $color['color_2'];
                        $params[22] = $color['color_3'];
                        $params[23] = '';
                        $params[24] = $color['core'];
                        if (!sqlsrv_execute($stmt, $params)) {
                            throw new Exception("Insert failed for JobNo: {$row['JobNo']} - " . print_r(sqlsrv_errors(), true));
                        }
                }
            } else {
                for ($j = 1; $j <= $coreCount; $j++) {
                        $params = $baseParams;
                        $params[19] = 1;
                        $params[20] = $row['Col1'];
                        $params[21] = $row['Col2'];
                        $params[22] = $row['Col3'];
                        $params[23] = $row['Col4'];
                        $params[24] = $j;
                        if (!sqlsrv_execute($stmt, $params)) {
                            throw new Exception("Insert failed for JobNo: {$row['JobNo']} - " . print_r(sqlsrv_errors(), true));
                        }
                }
            }
        } else {
            if (!empty($colors)) {
                foreach ($colors as $color) {
                    $params = $baseParams;
                    $params[19] = 1;
                    $params[20] = $color['color_1'];
                    $params[21] = $color['color_2'];
                    $params[22] = $color['color_3'];
                    $params[23] = '';
                    $params[24] = 0;
                    if (!sqlsrv_execute($stmt, $params)) {
                        throw new Exception("Insert failed for JobNo: {$row['JobNo']} - " . print_r(sqlsrv_errors(), true));
                    }
                }
            } else {
                $params = $baseParams;
                $params[19] = $coreCount;
                $params[20] = $row['Col1'];
                $params[21] = $row['Col2'];
                $params[22] = $row['Col3'];
                $params[23] = $row['Col4'];
                $params[24] = 0;
                if (!sqlsrv_execute($stmt, $params)) {
                    throw new Exception("Insert failed for JobNo: {$row['JobNo']} - " . print_r(sqlsrv_errors(), true));
                }
            }
        }
    }

    sqlsrv_commit($conn);
    echo "Data inserted successfully!";

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    error_log(date('[Y-m-d H:i:s] ') . "ERROR: " . $e->getMessage() . "\n", 3, $logFile);
    die("An error occurred. Check error log for details.");
} finally {
    if ($conn) sqlsrv_close($conn);
}
