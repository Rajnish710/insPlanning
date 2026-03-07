<?php
// getDrawingPlanning.php
// AJAX endpoint for Drawing planning with total StrDiaNum
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

    // SQL Query for Drawing Planning
    $sql = "
DECLARE @FromDate date = ?;
DECLARE @Mon int = ?;
DECLARE @WeekNo int = ?;
DECLARE @Yr int = ?;

WITH src AS
(
    SELECT
        a.insuStartDate,
        JobNo      = LTRIM(RTRIM(a.JobNo)),
        NoOfStr    = LTRIM(RTRIM(a.NoOfStr)),
        StrDia     = LTRIM(RTRIM(a.StrDia)),
        PlanCutLen = LTRIM(RTRIM(a.PlanCutLen)),
        Drums      = LTRIM(RTRIM(a.drums)),
        CPMult     = CAST(1 AS DECIMAL(18,4))
    FROM [PlanningSys].[control].[data] a
    WHERE a.isDelete = 0
      AND a.insuStartDate >= @FromDate
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia))  NOT IN ('', '-', '- ', '0', '0.0')

    UNION ALL

    SELECT
        a.insuStartDate,
        JobNo      = LTRIM(RTRIM(a.JobNo)),
        NoOfStr    = LTRIM(RTRIM(a.NoOfStr)),
        StrDia     = LTRIM(RTRIM(a.StrDia)),
        PlanCutLen = LTRIM(RTRIM(a.PlanCutLen)),
        Drums      = LTRIM(RTRIM(a.drums)),
        CPMult     = CAST(
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
      AND a.insuStartDate >= @FromDate
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia))  NOT IN ('', '-', '- ', '0', '0.0')
),

calc AS
(
    SELECT
        s.JobNo,
        v.StrDiaNum,
        v.NoOfStrNum,
        v.Qty,
        Mtr = CAST(v.Qty * v.NoOfStrNum AS DECIMAL(18,4)),
        Weight = CAST(
                    v.StrDiaNum * v.StrDiaNum * 0.785 * v.NoOfStrNum * v.Qty * 0.0089
                 AS DECIMAL(18,4)),
        dt.Yr,
        dt.Mon,
        dt.WeekNo
    FROM src s
    CROSS APPLY
    (
        SELECT
            ShiftDate = DATEADD(DAY, -7, s.insuStartDate)
    ) d
    CROSS APPLY
    (
        SELECT
            Yr = YEAR(d.ShiftDate),
            Mon = MONTH(d.ShiftDate),
            WeekNo = CASE
                        WHEN DAY(d.ShiftDate) <= 21
                            THEN ((DAY(d.ShiftDate) - 1) / 7) + 1
                        ELSE 4
                     END
    ) dt
    CROSS APPLY
    (
        SELECT
            NoOfStrNum = TRY_CAST(NULLIF(s.NoOfStr, '-') AS INT),
            StrDiaNum  = TRY_CAST(NULLIF(s.StrDia, '-') AS DECIMAL(18,6)),
            Qty        = CAST(
                            ISNULL(TRY_CAST(NULLIF(s.PlanCutLen, '-') AS DECIMAL(18,4)), 0) *
                            ISNULL(TRY_CAST(NULLIF(s.Drums, '-') AS DECIMAL(18,4)), 0) *
                            s.CPMult
                         AS DECIMAL(18,4))
    ) v
    WHERE v.NoOfStrNum > 0
      AND v.StrDiaNum > 0
      AND dt.Mon = @Mon
      AND dt.WeekNo = @WeekNo
      AND dt.Yr = @Yr
)

SELECT
    JobNo,
    StrDiaNum,
    Mtr    = SUM(Mtr),
    Weight = SUM(Weight)
FROM calc
GROUP BY
    JobNo,
    StrDiaNum
ORDER BY
    StrDiaNum,
    JobNo;
";

    // Execute with parameters
    $params = [$fromDate, $mon, $weekNo, $yr];
    $stmt = sqlsrv_query($con, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception("Query failed: " . implode(", ", sqlsrv_errors()[0] ?? []));
    }

    // Fetch all rows and calculate total StrDiaNum
    $rows = [];
    $totalStrDiaNum = 0;
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $rows[] = $row;
        $totalStrDiaNum += (float)$row['StrDiaNum'];
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($con);

    // Success response
    echo json_encode([
        "ok" => true,
        "data" => $rows,
        "totalStrDiaNum" => number_format($totalStrDiaNum, 4, '.', ''),
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
