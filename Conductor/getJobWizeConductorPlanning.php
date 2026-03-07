<?php
// getJobWizeConductorPlanning.php
// AJAX endpoint for job-wise planning data
header('Content-Type: application/json; charset=utf-8');
include '../includes/dbcon.php';

try {
    // Validate input
    $fromDate = $_POST['fromDate'] ?? '2026-03-15';
    $mon = (int)($_POST['mon'] ?? 0);
    $weekNo = (int)($_POST['weekNo'] ?? 0);
    $yr = (int)($_POST['yr'] ?? 0);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
        throw new Exception('Invalid date format');
    }

    // Optimized SQL Query
    $sql = "
DECLARE @FromDate date = ?;
DECLARE @Mon int = ?;
DECLARE @WeekNo int = ?;
DECLARE @Yr int = ?;

WITH src AS
(
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
      AND a.insuStartDate >= @FromDate
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia))  NOT IN ('', '-', '- ', '0', '0.0')

    UNION ALL

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
                        WHEN 'C' THEN 1 WHEN 'P' THEN 2 WHEN 'T' THEN 3 WHEN 'Q' THEN 4
                        ELSE 1 END AS DECIMAL(18,4))
    FROM [PlanningSys].[instru].[data] a
    WHERE a.isDelete = 0
      AND a.insuStartDate >= @FromDate
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia))  NOT IN ('', '-', '- ', '0', '0.0')
),

base AS
(
    SELECT
        s.JobNo,
        NoOfStrNum = TRY_CAST(NULLIF(s.NoOfStr,'-') AS INT),
        StrDiaNum  = TRY_CAST(NULLIF(s.StrDia,'-')  AS DECIMAL(10,4)),
        PlanCutLenNum = ISNULL(TRY_CAST(NULLIF(s.PlanCutLen,'-') AS DECIMAL(18,4)),0),
        DrumsNum = ISNULL(TRY_CAST(NULLIF(s.drums,'-') AS DECIMAL(18,4)),0),
        s.isMica,
        CondTypeTag = CASE
            WHEN s.CondType IS NULL THEN ''
            WHEN CHARINDEX('tin',LOWER(s.CondType)) > 0 THEN 'TIN'
            WHEN CHARINDEX('bare',LOWER(s.CondType)) > 0 THEN 'BARE'
            ELSE '' END,
        s.CPMult,
        WeekShift = DATEADD(DAY,-7,s.insuStartDate)
    FROM src s
),

metrics AS
(
    SELECT
        b.JobNo,
        b.NoOfStrNum,
        b.StrDiaNum,
        Mtr = CAST(b.PlanCutLenNum * b.DrumsNum * b.CPMult AS DECIMAL(18,4)),
        Weight = CAST(
                    b.StrDiaNum * b.StrDiaNum * 0.785 * b.NoOfStrNum * 
                    (b.PlanCutLenNum * b.DrumsNum * b.CPMult) * 0.0089 
                 AS DECIMAL(18,4)),
        b.isMica,
        b.CondTypeTag,
        Yr  = YEAR(b.WeekShift),
        Mon = MONTH(b.WeekShift),
        WeekNo = CASE WHEN DAY(b.WeekShift) <= 21 
                      THEN (DAY(b.WeekShift)-1)/7 + 1 
                      ELSE 4 END
    FROM base b
    WHERE b.NoOfStrNum > 0 AND b.StrDiaNum > 0
),

job_plan AS
(
    SELECT
        Yr, Mon, WeekNo, JobNo,
        NoOfStrNum, StrDiaNum, isMica, CondTypeTag,
        JobTotalMtr = SUM(Mtr),
        JobTotalWeight = SUM(Weight)
    FROM metrics
    GROUP BY Yr, Mon, WeekNo, JobNo, NoOfStrNum, StrDiaNum, isMica, CondTypeTag
)

SELECT
    jp.JobNo,
    NoOfStr = jp.NoOfStrNum,
    StrDia = FORMAT(jp.StrDiaNum, 'N4'),
    jp.isMica,
    jp.CondTypeTag,
    Mtr = jp.JobTotalMtr,
    Weight = jp.JobTotalWeight
FROM job_plan jp
WHERE jp.Mon = @Mon AND jp.WeekNo = @WeekNo AND jp.Yr = @Yr
ORDER BY jp.StrDiaNum, jp.CondTypeTag, jp.isMica;
";

    // Execute with parameters
    $params = [$fromDate, $mon, $weekNo, $yr];
    $stmt = sqlsrv_query($con, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception("Query failed: " . implode(", ", sqlsrv_errors()[0] ?? []));
    }

    // Fetch all rows
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($con);

    // Success response
    echo json_encode([
        "ok" => true,
        "data" => $rows,
        "count" => count($rows),
        "period" => "M{$mon} W{$weekNo} {$yr}"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
