<?php
include('../includes/dbcon45.php');
date_default_timezone_set('Asia/Kolkata');

// Configure error logging
// ini_set('error_log', 'error.log');
// ini_set('log_errors', 1);

// Helper functions
function calculateRequiredDate($siteDate, $plnSentTo) {
    if (!$siteDate || !$plnSentTo) return date('Y-m-d', strtotime('+5 days'));
    $origin1 = date_create($siteDate);
    $target1 = date_create($plnSentTo);                   
    $diff = date_diff($origin1, $target1)->format("%a");

    return ($target1 && $diff > 60) ? date('Y-m-d', strtotime($siteDate . ' -45 days'))
                             : date('Y-m-d', strtotime($plnSentTo . ' -22 days'));
}

function extractLDPercentage($ld) {
    preg_match('/([\d.]+)%/', $ld, $matches);
    return $matches[1] ?? 0;
}

function calculateOfferValue($rsSign, $value, $rsArr) {
    return ($rsArr[$rsSign] ?? 0) * $value;
}

function prepareBatchInsert($data) {
    $columns = array_keys($data[0]);
    $values = [];
    
    foreach ($data as $row) {
        $escaped = array_map(function($v) {
            return is_numeric($v) ? $v : "'" . str_replace("'", "`", $v) . "'";
        }, $row);
        $values[] = '(' . implode(', ', $escaped) . ')';
    }
    
    return "INSERT INTO [PlanningSys].[instru].[data] (" . implode(', ', $columns) . ") VALUES " . implode(', ', $values);
}

// Configuration arrays
$config = [
    'condType' => ['A','B','C','B/C','50%B & 50%C'],
    'sqmmArr' => ['0.5','0.75','1','1.5','2.5','4','6','10'],
    'PTQArr' => ["P" => 2, "T" => 3, "Q" => 4],
    'rsArr' => ["Thousand" => 0.01, "Lacs" => 1, "Crore" => 100],
    'numberOnCore' => [
        "numbering on each core & tape",
        "numbering on insulation",
        "numbering on prmary insulation & tape",
        "Colour Coded With Numbring",
        "Numbering on tape & insulation",
        "Numbering on each core & on tape",
        "Colour code with numbering on insulation",
        "Colour code with numbering on tape & insulation"
    ]
];

// Pre-fetch condSize data
$condSizeCache = [];
$sqlCondSize = "SELECT type, Size, A, B, C, [B/C], [50%B & 50%C] FROM [PlanningSys].[master].[condSize]";
$result = sqlsrv_query($conn, $sqlCondSize);
while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $condSizeCache[$row['type']][$row['Size']] = $row;
}

// Main query optimization
$sql = <<<SQL
SELECT 
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
    d.condgrd,
    IIF(d.insgrd2 = '', d.insgrd1, CONCAT(d.insgrd1, ' /', d.insper, '% /', d.insgrd2)) AS insGrade,
    a.BasicRate,
    CONVERT(VARCHAR(11), CONVERT(DATE, b.Plnsentto, 103), 106) AS Plnsentto,
    b.OfferNo,
    b.TolM,
    b.TolP,
    b.Value,
    b.RsSign,
    b.LD,
    b.LDASPER,
    CONVERT(VARCHAR(11), b.LDSDate, 106) AS LDSDate,
    CONVERT(VARCHAR(11), b.SiteDate, 106) AS SiteDate,
    a.ColorType,
    a.Col1,
    a.Col2,
    a.Col3,
    a.Col4,
    c.NoofDrum,
    c.CQty,
    ISNULL(c.CQty*(1+t.per*0.01), CEILING(c.CQty * 1.02)) AS planLen,
    ISNULL(t.per, 2) AS planTol
FROM OrdDetail a
JOIN OrdMaster b
    ON a.OrdID = b.OrdID
JOIN CutLength c
    ON a.JobNo = c.JobNo
JOIN [backward_calc].[dbo].pvcGrade d
    ON a.JobNo = d.Job
LEFT JOIN [PlanningSys].[master].[qtyTol] t
    ON c.CQty BETWEEN t.lenFrom AND t.lenTo
    AND t.minus = b.TolM
    AND t.plus = b.TolP
WHERE 
    a.JobNo LIKE 'KPTL/17%'
    AND a.InsTypeP <> ''
    AND a.CorePair <> 'C'
ORDER BY 
    b.SiteDate,
    a.JobNo
SQL;

$result = sqlsrv_query($conn, $sql, [], ["Scrollable" => SQLSRV_CURSOR_KEYSET]);
if (!$result) die(print_r(sqlsrv_errors(), true));    

// Prepare insert statements
$insertData = [];
while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
    $isMica = ($row['Remark'] == 'Yes');
    $isBandM = ($row['CableType'] == 'BHEL Unit Form' || $row['ColorType'] == 'Colour Coded with Band - Marking');
    $isNoPrintOnCore = in_array($row['ColorType'], $config['numberOnCore']);

     // Calculate conductor data
    $condType = preg_match('/Stranded/', $row['Condtypep']) ? 'Stranded' : 'Solid';
    $noOfStr = $condType == 'Stranded' ? 7 : 1;
    $strDia = '';
    if (isset($condSizeCache[$condType][$row['SQMM']])) {
        $grade = $row['condgrd'];
        $strDia = $condSizeCache[$condType][$row['SQMM']][$grade] ?? '';
    }


$colorCount = [];
$sqlColor = "SELECT JobNo,1 as ColorCount,color_1,color_2,color_3,core from OrdColorIns where JobNo = ? order by core";
$resultColor = sqlsrv_query($conn,$sqlColor,[$row['JobNo']],array("Scrollable" => SQLSRV_CURSOR_KEYSET));
$count = sqlsrv_num_rows($resultColor);
if ($isNoPrintOnCore || $isBandM) {    
    if ($count) {
        $runColor = sqlsrv_query($conn, $sqlColor, [$row['JobNo']]);
        $i = 0;
        while ($rowColor = sqlsrv_fetch_array($runColor, SQLSRV_FETCH_ASSOC)) {
            $i++;
            for ($j=1; $j <= $config['PTQArr'][$row['CorePair']]; $j++) { 
                $colorCount[] = array('count'=>1,'pairNo'=>$i,'coreNo_Of_Pair'=>$j,'color'=>$rowColor["color_$j"]);  
            }
        }
    }else{
        for ($i=1; $i <= intval($row['Core']) ; $i++) {
            for ($j=1; $j <= $config['PTQArr'][$row['CorePair']]; $j++) { 
                $colorCount[] = array('count'=>1,'pairNo'=>$i,'coreNo_Of_Pair'=>$j,'color'=>$row["Col$j"]);  
            }
        }
    }
}else{ 
    if ($count) {
        $runColor = sqlsrv_query($conn, $sqlColor, [$row['JobNo']]);
        $i = 0;
        while ($rowColor = sqlsrv_fetch_array($runColor, SQLSRV_FETCH_ASSOC)) {
            $i++;
            for ($j=1; $j <= $config['PTQArr'][$row['CorePair']]; $j++) { 
                $colorCount[] = array('count'=>1,'pairNo'=>$i,'coreNo_Of_Pair'=>$j,'color'=>$rowColor["color_$j"]);  
            }
        }
    }else{
            for ($j=1; $j <= $config['PTQArr'][$row['CorePair']]; $j++) { 
                $colorCount[] = array('count'=>$row['Core'],'pairNo'=>0,'coreNo_Of_Pair'=>$j,'color'=>$row["Col$j"]);  
            }
        }
}

    // Build insert data
    foreach ($colorCount as $colorRow) {
        $insertData[] = [
            'DeliveryDate' => $row['SiteDate'],
            'JobNo' => $row['JobNo'],
            'Core' => $row['Core'],
            'CP' => $row['CorePair'],
            'Sqmm' => $row['SQMM'],
            'isMica' => $isMica,
            'OrderQty' => $row['Qty'],
            'TolM' => $row['TolM'],
            'TolP' => $row['TolP'],
            'planTol' => $row['planTol'],
            'OrdCutLength' => $row['CQty'],
            'ord_NoofDrums' => $row['NoofDrum'],
            'CondGrade' => $row['condgrd'],
            'CondType' => $row['Condtypep'],
            'InsuType' => $row['insGrade'],
            'NoOfStr' => $noOfStr,
            'StrDia' => $strDia,
            'PlanCutLen' => $row['planLen'],
            'plan_NoofDrums' => $row['NoofDrum'],
            'multiplyDrums' => $colorRow['count'],
            'InsuColor' => $colorRow['color'],
            'PairNo' => $colorRow['pairNo'],
            'CoreNo' => $colorRow['coreNo_Of_Pair'],
            'PlanningDate' => date('Y-m-d'),
            'RequiredDate' => calculateRequiredDate($row['SiteDate'], $row['Plnsentto']),
            'ordID' => $row['ordid'],
            'cableCat' => $row['CableCat'],
            'enqNo' => $row['OfferNo'],
            'valueAmt' => calculateOfferValue($row['RsSign'], $row['Value'], $config['rsArr']),
            'LdPer' => extractLDPercentage($row['LD']),
            'LdTerm' => $row['LDASPER'],
            'LdDate' => $row['LDSDate'],
            'createdAt' => date('Y-m-d H:i:s'),
            'isBandM' => (int)$isBandM,
            'isNoPrintOnCore' => (int)$isNoPrintOnCore
        ];
    }





}

// Batch insert
$chunks = array_chunk($insertData, 500); // Adjust chunk size as needed
foreach ($chunks as $chunk) {
    $insertSql = prepareBatchInsert($chunk);
    sqlsrv_query($conn, $insertSql);
}





// echo '<pre>';
// print_r($chunks);
// echo '</pre>';