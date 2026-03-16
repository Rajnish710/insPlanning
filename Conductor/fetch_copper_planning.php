<?php
// api_balance_report.php
// Returns JSON for jSpreadsheet: { headers: [...], rows: [ [...], ... ] }

header('Content-Type: application/json; charset=utf-8');
include '../includes/dbcon.php';

try {

    // Optional filter from query string
    // Example: api_balance_report.php?from=2026-03-01
    $fromDate = isset($_GET['from']) ? $_GET['from'] : '2026-03-15';
    // Basic validation (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
        $fromDate = '2026-03-01';
    }
    // Drawing: key = strDia
        // $drawingProdMap = [];
        // $sqlDrawing = "
        //     SELECT strDia, SUM(Length) AS Mtr
        //     FROM prod.Drawing
        //     GROUP BY strDia
        // ";
        // $stmtDrawing = sqlsrv_query($con, $sqlDrawing);
        // if ($stmtDrawing === false) {
        //     throw new Exception("Drawing prod SQL failed: " . print_r(sqlsrv_errors(), true));
        // }
        // while ($r = sqlsrv_fetch_array($stmtDrawing, SQLSRV_FETCH_ASSOC)) {
        //     $k= trim((string)$r['strDia']);
        //     $drawingProdMap[$k] = (float)($r['Mtr'] ?? 0);
        // }
        // sqlsrv_free_stmt($stmtDrawing);

    // --------- SQL BATCH (your optimized script) ----------
    // NOTE: Using $fromDate safely via parameterized query.
    // We cannot parameterize dynamic column names, but the date filter is safe as a parameter.
    $sql = "
SET NOCOUNT ON;

DECLARE @FromDate DATE = ?;

IF OBJECT_ID('tempdb..#week_dim_jobs') IS NOT NULL
    DROP TABLE #week_dim_jobs;

/*========================================================
  STEP 1: Source Data (Early Filtering + CP Logic)
========================================================*/
WITH src AS
(
    -- CONTROL
    SELECT
        a.insuStartDate,
        JobNo = LTRIM(RTRIM(a.JobNo)),
        NoOfStr = LTRIM(RTRIM(a.NoOfStr)),
        StrDia = LTRIM(RTRIM(a.StrDia)),
        PlanCutLen = LTRIM(RTRIM(a.PlanCutLen)),
        drums = LTRIM(RTRIM(a.drums)),
        a.isMica,
        a.CondType,
        CPMult = CAST(1 AS DECIMAL(18,4))
    FROM [PlanningSys].[control].[data] a
    WHERE a.isDelete = 0
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia))  NOT IN ('', '-', '- ', '0', '0.0')
      AND a.insuStartDate IS NOT NULL

    UNION ALL

    -- INSTRU
    SELECT
        a.insuStartDate,
        JobNo = LTRIM(RTRIM(a.JobNo)),
        NoOfStr = LTRIM(RTRIM(a.NoOfStr)),
        StrDia = LTRIM(RTRIM(a.StrDia)),
        PlanCutLen = LTRIM(RTRIM(a.PlanCutLen)),
        drums = LTRIM(RTRIM(a.drums)),
        a.isMica,
        a.CondType,
        CPMult = CAST(
                    CASE UPPER(LEFT(LTRIM(RTRIM(ISNULL(a.CP,''))),1))
                        WHEN 'C' THEN 1
                        WHEN 'P' THEN 2
                        WHEN 'T' THEN 3
                        WHEN 'Q' THEN 4
                        ELSE 1
                    END
                 AS DECIMAL(18,4))
    FROM [PlanningSys].[instru].[data] a
    WHERE a.isDelete = 0
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia))  NOT IN ('', '-', '- ', '0', '0.0')
      AND a.insuStartDate IS NOT NULL
),

/*========================================================
  STEP 2: Safe Type Conversion + First Week Mapping
========================================================*/
base AS
(
    SELECT
        s.insuStartDate,
        s.JobNo,
        NoOfStrNum = TRY_CAST(NULLIF(s.NoOfStr,'-') AS INT),
        StrDiaNum  = TRY_CAST(NULLIF(s.StrDia,'-')  AS DECIMAL(10,4)),
        PlanCutLenNum = ISNULL(TRY_CAST(NULLIF(s.PlanCutLen,'-') AS DECIMAL(18,4)),0),
        DrumsNum      = ISNULL(TRY_CAST(NULLIF(s.drums,'-') AS DECIMAL(18,4)),0),
        s.isMica,
        CondTypeTag =
            CASE
                WHEN s.CondType IS NULL THEN ''
                WHEN CHARINDEX('tin',LOWER(s.CondType))  > 0 THEN 'TIN'
                WHEN CHARINDEX('bare',LOWER(s.CondType)) > 0 THEN 'BARE'
                ELSE ''
            END,
        s.CPMult,
        WeekBaseDate = CASE
                         WHEN s.insuStartDate < @FromDate THEN @FromDate
                         ELSE s.insuStartDate
                       END
    FROM src s
),

/*========================================================
  STEP 3: Calculate Planning MTR + Week Bucketing
========================================================*/
metrics AS
(
    SELECT
        b.JobNo,
        b.NoOfStrNum,
        b.StrDiaNum,
        Mtr = CAST(b.PlanCutLenNum * b.DrumsNum * b.CPMult AS DECIMAL(18,4)),
        b.isMica,
        b.CondTypeTag,
        Yr  = YEAR(DATEADD(DAY,-7,b.WeekBaseDate)),
        Mon = MONTH(DATEADD(DAY,-7,b.WeekBaseDate)),
        WeekNo =
            CASE
                WHEN DAY(DATEADD(DAY,-7,b.WeekBaseDate)) <= 21
                    THEN (DAY(DATEADD(DAY,-7,b.WeekBaseDate))-1)/7 + 1
                ELSE 4
            END
    FROM base b
    WHERE b.NoOfStrNum > 0
      AND b.StrDiaNum  > 0
),

/*========================================================
  STEP 4: Job-Level Planning Aggregate
========================================================*/
job_plan AS
(
    SELECT
        Yr,Mon,WeekNo,
        NoOfStrNum,StrDiaNum,
        isMica,CondTypeTag,
        JobNo,
        JobTotalMtr = SUM(Mtr)
    FROM metrics
    GROUP BY
        Yr,Mon,WeekNo,
        NoOfStrNum,StrDiaNum,
        isMica,CondTypeTag,
        JobNo
),

/*========================================================
  STEP 5: Week-Level Demand
========================================================*/
week_demand AS
(
    SELECT
        Yr,Mon,WeekNo,
        NoOfStrNum,StrDiaNum,
        isMica,CondTypeTag,
        WeekDemandMtr = SUM(JobTotalMtr)
    FROM job_plan
    WHERE JobTotalMtr > 0
    GROUP BY
        Yr,Mon,WeekNo,
        NoOfStrNum,StrDiaNum,
        isMica,CondTypeTag
),

/*========================================================
  STEP 6: Final Production Aggregate
========================================================*/
prod AS
(
    SELECT
        NoOfStrNum  = [noOfStrands],
        StrDiaNum   = CAST([strDia] AS DECIMAL(10,4)),
        CondTypeTag = CASE WHEN [condType] IS NULL THEN '' ELSE [condType] END,
        isMica,
        ProducedMtr = SUM(ISNULL([Length], 0))
    FROM [PlanningSys].[prod].[Outward]
    GROUP BY
        [noOfStrands],
        CAST([strDia] AS DECIMAL(10,4)),
        CASE WHEN [condType] IS NULL THEN '' ELSE [condType] END,
        isMica
),

/*========================================================
  STEP 7: Attach Production + Running Demand
========================================================*/
week_calc AS
(
    SELECT
        d.Yr,
        d.Mon,
        d.WeekNo,
        d.NoOfStrNum,
        d.StrDiaNum,
        d.isMica,
        d.CondTypeTag,
        d.WeekDemandMtr,
        ProducedMtr = ISNULL(p.ProducedMtr,0),

        CumDemand =
            SUM(d.WeekDemandMtr) OVER
            (
                PARTITION BY d.NoOfStrNum,d.StrDiaNum,d.isMica,d.CondTypeTag
                ORDER BY d.Yr,d.Mon,d.WeekNo
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            )
    FROM week_demand d
    LEFT JOIN prod p
        ON  p.NoOfStrNum   = d.NoOfStrNum
        AND p.StrDiaNum    = d.StrDiaNum
        AND p.isMica       = d.isMica
        AND p.CondTypeTag  = d.CondTypeTag
),

/*========================================================
  STEP 8: Deduct Production FIFO from Earliest Weeks
========================================================*/
week_balance AS
(
    SELECT
        Yr,
        Mon,
        WeekNo,
        NoOfStrNum,
        StrDiaNum,
        isMica,
        CondTypeTag,
        BalanceMtr =
            CAST(
                CASE
                    WHEN ProducedMtr >= CumDemand THEN 0
                    WHEN ProducedMtr <= (CumDemand - WeekDemandMtr) THEN WeekDemandMtr
                    ELSE CumDemand - ProducedMtr
                END
            AS DECIMAL(18,4))
    FROM week_calc
)

/*========================================================
  STEP 9: Store for Dynamic Pivot
========================================================*/
SELECT
    Yr,
    Mon,
    WeekNo,
    PeriodKey =
        LEFT(DATENAME(MONTH,DATEFROMPARTS(Yr,Mon,1)),3)
        + '-' + RIGHT(CONVERT(VARCHAR(4),Yr),2)
        + '_W' + CAST(WeekNo AS VARCHAR(2)),
    SortKey = (Yr*100+Mon)*10+WeekNo,
    NoOfStrNum,
    StrDiaNum,
    isMica,
    CondTypeTag,
    BalanceMtr
INTO #week_dim_jobs
FROM week_balance
WHERE BalanceMtr > 0;

CREATE INDEX IX_temp
ON #week_dim_jobs(NoOfStrNum,StrDiaNum,isMica,CondTypeTag,SortKey)
INCLUDE (BalanceMtr,PeriodKey);

/*========================================================
  STEP 10: Dynamic Horizontal Pivot
========================================================*/
DECLARE @cols NVARCHAR(MAX), @sql NVARCHAR(MAX);

SELECT @cols =
    STUFF((
        SELECT
            ', SUM(CASE WHEN d.PeriodKey='''+PeriodKey+''' THEN d.BalanceMtr ELSE 0 END) AS ['+PeriodKey+'_mtr]'
        FROM (SELECT DISTINCT PeriodKey,SortKey FROM #week_dim_jobs) x
        ORDER BY x.SortKey
        FOR XML PATH(''),TYPE
    ).value('.','NVARCHAR(MAX)'),1,2,'');

SET @sql='
SELECT
    NoOfStrNum AS NoOfStr,
    CAST(StrDiaNum AS DECIMAL(10,4)) AS StrDia,
    isMica,
    CondTypeTag,
    '+@cols+'
FROM #week_dim_jobs d
GROUP BY NoOfStrNum,StrDiaNum,isMica,CondTypeTag
ORDER BY StrDiaNum,NoOfStrNum,isMica,CondTypeTag;';

EXEC sp_executesql @sql;
";

    // Run batch. There will be multiple result sets; final is the pivot output.
    $params = [$fromDate];
    $stmt = sqlsrv_query($con, $sql, $params, ["Scrollable" => SQLSRV_CURSOR_FORWARD]);
    if ($stmt === false) {
        throw new Exception("SQL failed: " . print_r(sqlsrv_errors(), true));
    }

    // Move to the last result set that contains columns
    // We'll keep advancing until we find a result set with fields.
    $finalStmt = $stmt;
    while (true) {
        $fieldMeta = sqlsrv_field_metadata($finalStmt);
        if ($fieldMeta !== false && count($fieldMeta) > 0) {
            break; // found the final set
        }
        if (!sqlsrv_next_result($finalStmt)) {
            break;
        }
    }

    $fieldMeta = sqlsrv_field_metadata($finalStmt);
    if ($fieldMeta === false || count($fieldMeta) === 0) {
        throw new Exception("No final result set returned.");
    }

// -------------------- BASE HEADERS (from SQL) --------------------
$baseHeaders = [];
foreach ($fieldMeta as $f) {
    $baseHeaders[] = $f['Name'];
}
$colPlan = [];  
$headers = [];

foreach ($baseHeaders as $h) {
    $isMtr = (bool)preg_match('/_mtr$/i', $h);
    $prefix = $isMtr ? substr($h, 0, -4) : null;

    $colPlan[] = [
        'name'   => $h,
        'isMtr'  => $isMtr,
        'prefix' => $prefix
    ];

    // keep original header
    $headers[] = $h;

    // add columns after each *_mtr
    if ($isMtr) {
        $headers[] = $prefix . '_Kgs';
        $headers[] = $prefix . '_Drawing';
        $headers[] = $prefix . '_Tinning';
        $headers[] = $prefix . '_Bunching';
        $headers[] = $prefix . '_Mica';
    }
}

// -------------------- CAPACITY MAP FETCH --------------------
$sqlCap = "SELECT * FROM master.StrDiaProcessCapacity";
$stmtCap = sqlsrv_query($con, $sqlCap);
if ($stmtCap === false) {
    throw new Exception("Capacity SQL failed: " . print_r(sqlsrv_errors(), true));
}

$capacityMap = []; 
while ($rowCap = sqlsrv_fetch_array($stmtCap, SQLSRV_FETCH_ASSOC)) {
    $raw = trim((string)$rowCap['StrDia']);
    // $f = is_numeric($raw) ? (float)$raw : 0.0;

    // $k4 = number_format($f, 4, '.', '');            // e.g. "0.3000"
    // $kTrim = rtrim(rtrim($k4, '0'), '.');           // e.g. "0.3"
    $capacityMap[$raw] = $rowCap;
}
sqlsrv_free_stmt($stmtCap);

// -------------------- SETTINGS --------------------
$noOfShift   = 2;
$perShiftHrs = 8;
$weekDays    = 7;

$mcCount = ['drawing'=>9, 'bunching'=>9, 'tinning'=>4, 'mica'=>11];

// -------------------- BUILD ROWS (with extra columns) --------------------
$rows = [];

while ($row = sqlsrv_fetch_array($finalStmt, SQLSRV_FETCH_ASSOC)) {

    // row-level fields for rules
    $noOfStr = isset($row['NoOfStr']) ? (int)$row['NoOfStr'] : 0;

    $strDiaRaw = $row['StrDia'] ?? 0;
    $strDiaF = is_numeric($strDiaRaw) ? (float)$strDiaRaw : (float)str_replace(',', '', (string)$strDiaRaw);

    $strDiaKey = number_format($strDiaF, 4, '.', '');
    $strDiaKeyTrim = rtrim(rtrim($strDiaKey, '0'), '.');

    $capRow = $capacityMap[trim((string)$strDiaRaw)] ?? null;

    $drawMtrHr  = $capRow ? (float)($capRow['DrawingMtrPerHr']  ?? 0) : 0.0;
    $bunchMtrHr = $capRow ? (float)($capRow['BunchingMtrPerHr'] ?? 0) : 0.0;
    $tinMtrHr   = $capRow ? (float)($capRow['TinningMtrPerHr']  ?? 0) : 0.0;
    $micaMtrHr  = $capRow ? (float)($capRow['MicaMtrPerHr']     ?? 0) : 0.0;

    $condType = strtoupper(trim((string)($row['CondTypeTag'] ?? '')));
    $isMica   = isset($row['isMica']) ? (int)$row['isMica'] : 0;

    $out = [];

    foreach ($colPlan as $c) {

        $h = $c['name'];
        $val = $row[$h] ?? "";
        if ($val === null) $val = "";

        // keep original value
        $out[] = $val;

        // if this is a *_mtr column, add 4 computed columns
        if ($c['isMtr']) {

            $qty = is_numeric($val) ? (float)$val : 0.0;

            // ---------------- Drawing (weekly capacity includes NoOfStr) ----------------
            $drawingWeekCap = $drawMtrHr * $mcCount['drawing'] * $noOfShift * $perShiftHrs * $weekDays;
            $drawingLoad = ($drawingWeekCap > 0) ? (($noOfStr * $qty)/ $drawingWeekCap) : 0;

            // ---------------- Tinning (only if CondTypeTag == 'TIN') ----------------
            if ($condType !== 'TIN') {
                $tinningLoad = 0;
            } else {
                $tinningWeekCap = $tinMtrHr * $mcCount['tinning'] * $noOfShift * $perShiftHrs * $weekDays;
                $tinningLoad = ($tinningWeekCap > 0) ? (($noOfStr * $qty ) / $tinningWeekCap) : 0;
            }

            // ---------------- Bunching (special rules) ----------------
            // Rule 1: NoOfStr==1 AND StrDia<0.31 => 0
            // Rule 2: Bunching capacity formula me NoOfStr multiply nahi hoga
            if ($noOfStr === 1 && $strDiaF < 0.31) {
                $bunchingLoad = 0;
            } else {
                $bunchingWeekCap = $bunchMtrHr * $mcCount['bunching'] * $noOfShift * $perShiftHrs * $weekDays;
                $bunchingLoad = ($bunchingWeekCap > 0) ? ($qty / $bunchingWeekCap) : 0;
            }

            // ---------------- Mica (only if isMica == 1) ----------------
            if ($isMica !== 1) {
                $micaLoad = 0;
            } else {
                $micaWeekCap = $micaMtrHr * $mcCount['mica'] * $noOfShift * $perShiftHrs * $noOfStr * $weekDays;
                $micaLoad = ($micaWeekCap > 0) ? ($qty / $micaWeekCap) : 0;
            }
            // Weight calculation: StrDia*StrDia*0.785*NoOfStr*Mtr*0.0089
            $weight = $strDiaF * $strDiaF * 0.785 * $noOfStr * $qty * 0.0089;
            // $weightFormatted = number_format((int)round($weight), 0, '', ',');
            $out[] = $weight;
            // Add in requested order: Drawing, Tinning, Bunching, Mica
            $out[] = round($drawingLoad, 6);
            $out[] = round($tinningLoad, 6);
            $out[] = round($bunchingLoad, 6);
            $out[] = round($micaLoad, 6);

        }
    }

    $rows[] = $out;
}

// Add one blank editable row at the end for manual entry.
// if (!empty($headers)) {
//     $lastRow = [];

//     foreach ($headers as $header) {
//         if (preg_match('/_(Drawing|Tinning|Bunching|Mica)$/i', $header, $match)) {
//             switch (strtolower($match[1])) {
//                 case 'drawing':
//                     $lastRow[] = 1;   // Drawing logic
//                     break;

//                 case 'tinning':
//                     $lastRow[] = 2;   // Tinning logic
//                     break;

//                 case 'bunching':
//                     $lastRow[] = 3;   // Bunching logic
//                     break;

//                 case 'mica':
//                     $lastRow[] = 4;   // Mica logic
//                     break;

//                 default:
//                     $lastRow[] = '';
//                     break;
//             }
//         } else {
//             $lastRow[] = '';
//         }
//     }

//     $rows[] = $lastRow;
// }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($con);

    echo json_encode([
        "ok" => true,
        "headers" => $headers,
        "rows" => $rows,
        "from" => $fromDate
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
