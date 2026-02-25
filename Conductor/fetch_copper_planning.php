<?php
// api_balance_report.php
// Returns JSON for jSpreadsheet: { headers: [...], rows: [ [...], ... ] }

header('Content-Type: application/json; charset=utf-8');
include '../includes/dbcon.php';

try {

    // Optional filter from query string
    // Example: api_balance_report.php?from=2026-03-01
    $fromDate = isset($_GET['from']) ? $_GET['from'] : '2026-03-01';
    // Basic validation (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
        $fromDate = '2026-03-01';
    }

    // --------- SQL BATCH (your optimized script) ----------
    // NOTE: Using $fromDate safely via parameterized query.
    // We cannot parameterize dynamic column names, but the date filter is safe as a parameter.
    $sql = "
SET NOCOUNT ON;

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
      AND a.insuStartDate >= ?
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia))  NOT IN ('', '-', '- ', '0', '0.0')

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
      AND a.insuStartDate >= ?
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia))  NOT IN ('', '-', '- ', '0', '0.0')
),

/*========================================================
  STEP 2: Safe Type Conversion (Single Pass)
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
        WeekShift = DATEADD(DAY,-7,s.insuStartDate)
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
        Yr  = YEAR(b.WeekShift),
        Mon = MONTH(b.WeekShift),
        WeekNo =
            CASE WHEN DAY(b.WeekShift) <= 21
                 THEN (DAY(b.WeekShift)-1)/7 + 1
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
  STEP 5: Production Aggregate
========================================================*/
prod AS
(
    SELECT JobNo, SUM(ProdMtr) ProdMtr
    FROM dbo.JobProduction
    GROUP BY JobNo
),

/*========================================================
  STEP 6: Balance Calculation
========================================================*/
job_balance AS
(
    SELECT
        j.Yr,j.Mon,j.WeekNo,
        j.NoOfStrNum,j.StrDiaNum,
        j.isMica,j.CondTypeTag,
        BalanceMtr =
            CASE
                WHEN j.JobTotalMtr - ISNULL(p.ProdMtr,0) < 0 THEN 0
                ELSE j.JobTotalMtr - ISNULL(p.ProdMtr,0)
            END
    FROM job_plan j
    LEFT JOIN prod p ON p.JobNo=j.JobNo
),

/*========================================================
  STEP 7: Week-Level Aggregate
========================================================*/
week_balance AS
(
    SELECT
        Yr,Mon,WeekNo,
        NoOfStrNum,StrDiaNum,
        isMica,CondTypeTag,
        BalanceMtr = SUM(BalanceMtr)
    FROM job_balance
    WHERE BalanceMtr > 0
    GROUP BY
        Yr,Mon,WeekNo,
        NoOfStrNum,StrDiaNum,
        isMica,CondTypeTag
)

/*========================================================
  STEP 8: Store for Dynamic Pivot
========================================================*/
SELECT
    Yr,Mon,WeekNo,
    PeriodKey =
        LEFT(DATENAME(MONTH,DATEFROMPARTS(Yr,Mon,1)),3)
        + '-' + RIGHT(CONVERT(VARCHAR(4),Yr),2)
        + '_W' + CAST(WeekNo AS VARCHAR(2)),
    SortKey = (Yr*100+Mon)*10+WeekNo,
    NoOfStrNum,StrDiaNum,isMica,CondTypeTag,
    BalanceMtr
INTO #week_dim_jobs
FROM week_balance;

CREATE INDEX IX_temp
ON #week_dim_jobs(NoOfStrNum,StrDiaNum,isMica,CondTypeTag,SortKey)
INCLUDE (BalanceMtr,PeriodKey);

/*========================================================
  STEP 9: Dynamic Horizontal Pivot (Balance Only)
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
    $params = [$fromDate, $fromDate];
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

    $headers = [];
    foreach ($fieldMeta as $f) {
        $headers[] = $f['Name'];
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($finalStmt, SQLSRV_FETCH_ASSOC)) {
        $out = [];
        foreach ($headers as $h) {
            $val = $row[$h];
            if ($val === null) $val = "";
            $out[] = $val;
        }
        $rows[] = $out;
    }

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