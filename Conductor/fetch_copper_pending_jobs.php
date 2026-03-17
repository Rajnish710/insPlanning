<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
include __DIR__ . '/../includes/dbcon.php';

try {
    $completionThreshold = 98.0;
    $requestedAll = isset($_GET['week']) && strtoupper((string)$_GET['week']) === 'ALL';
    $selectedYr = isset($_GET['yr']) ? (int)$_GET['yr'] : 0;
    $selectedMon = isset($_GET['mon']) ? (int)$_GET['mon'] : 0;
    $selectedWeekNo = isset($_GET['weekNo']) ? (int)$_GET['weekNo'] : 0;

    $weekSql = "
SET NOCOUNT ON;

DECLARE @FromDate DATE = CAST(GETDATE() AS DATE);

WITH src AS
(
    SELECT CAST(a.insuStartDate AS DATE) AS insuStartDate
    FROM [PlanningSys].[control].[data] a
    WHERE a.isDelete = 0
      AND a.insuStartDate IS NOT NULL
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia)) NOT IN ('', '-', '- ', '0', '0.0')

    UNION ALL

    SELECT CAST(a.insuStartDate AS DATE) AS insuStartDate
    FROM [PlanningSys].[instru].[data] a
    WHERE a.isDelete = 0
      AND a.insuStartDate IS NOT NULL
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia)) NOT IN ('', '-', '- ', '0', '0.0')
),
week_dim AS
(
    SELECT DISTINCT
        Yr = YEAR(WeekBaseDate),
        Mon = MONTH(WeekBaseDate),
        WeekNo =
            CASE
                WHEN DAY(WeekBaseDate) <= 21
                    THEN (DAY(WeekBaseDate) - 1) / 7 + 1
                ELSE 4
            END
    FROM
    (
        SELECT
            WeekBaseDate = CASE
                WHEN insuStartDate < @FromDate THEN @FromDate
                ELSE insuStartDate
            END
        FROM src
    ) mapped
)
SELECT
    Yr,
    Mon,
    WeekNo,
    SortKey = (Yr * 100 + Mon) * 10 + WeekNo,
    Label =
        LEFT(DATENAME(MONTH, DATEFROMPARTS(Yr, Mon, 1)), 3)
        + '-' + RIGHT(CONVERT(VARCHAR(4), Yr), 2)
        + ' W' + CAST(WeekNo AS VARCHAR(2))
FROM week_dim
ORDER BY SortKey ASC;
";

    $weekStmt = sqlsrv_query($con, $weekSql);
    if ($weekStmt === false) {
        throw new Exception('Week query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $weeks = [];
    while ($weekRow = sqlsrv_fetch_array($weekStmt, SQLSRV_FETCH_ASSOC)) {
        $weeks[] = [
            'yr' => (int)$weekRow['Yr'],
            'mon' => (int)$weekRow['Mon'],
            'weekNo' => (int)$weekRow['WeekNo'],
            'sortKey' => (int)$weekRow['SortKey'],
            'label' => $weekRow['Label']
        ];
    }
    sqlsrv_free_stmt($weekStmt);

    if (empty($weeks)) {
        echo json_encode([
            'ok' => true,
            'headers' => [],
            'rows' => [],
            'weeks' => [],
            'count' => 0,
            'completionThreshold' => $completionThreshold
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $weekKeys = array_map(
        static function ($week) {
            return $week['yr'] . '-' . $week['mon'] . '-' . $week['weekNo'];
        },
        $weeks
    );
    $requestedKey = $selectedYr . '-' . $selectedMon . '-' . $selectedWeekNo;
    $applyWeekFilter = !$requestedAll
        && $selectedYr > 0
        && $selectedMon > 0
        && $selectedWeekNo > 0
        && in_array($requestedKey, $weekKeys, true);

    $sql = "
SET NOCOUNT ON;

DECLARE @FromDate DATE = CAST(GETDATE() AS DATE);
DECLARE @ApplyWeekFilter BIT = ?;
DECLARE @Yr INT = ?;
DECLARE @Mon INT = ?;
DECLARE @WeekNo INT = ?;
DECLARE @CompletionThreshold DECIMAL(5,2) = ?;

WITH src AS
(
    SELECT
        insuStartDate = CAST(a.insuStartDate AS DATE),
        JobNo = LTRIM(RTRIM(a.JobNo)),
        NoOfStr = LTRIM(RTRIM(a.NoOfStr)),
        StrDia = LTRIM(RTRIM(a.StrDia)),
        Size = CONCAT(
            LTRIM(RTRIM(ISNULL(a.Core, ''))),
            LTRIM(RTRIM(ISNULL(a.CP, ''))),
            ' X ',
            LTRIM(RTRIM(ISNULL(a.Sqmm, '')))
        ),
        OrderQty = LTRIM(RTRIM(ISNULL(a.OrderQty, ''))),
        PlanCutLen = LTRIM(RTRIM(a.PlanCutLen)),
        Drums = LTRIM(RTRIM(a.drums)),
        a.isMica,
        CondTypeTag =
            CASE
                WHEN a.CondType IS NULL THEN ''
                WHEN CHARINDEX('tin', LOWER(a.CondType)) > 0 THEN 'TIN'
                WHEN CHARINDEX('bare', LOWER(a.CondType)) > 0 THEN 'BARE'
                ELSE ''
            END,
        CPMult = CAST(1 AS DECIMAL(18,4))
    FROM [PlanningSys].[control].[data] a
    WHERE a.isDelete = 0
      AND a.insuStartDate IS NOT NULL
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia)) NOT IN ('', '-', '- ', '0', '0.0')

    UNION ALL

    SELECT
        insuStartDate = CAST(a.insuStartDate AS DATE),
        JobNo = LTRIM(RTRIM(a.JobNo)),
        NoOfStr = LTRIM(RTRIM(a.NoOfStr)),
        StrDia = LTRIM(RTRIM(a.StrDia)),
        Size = CONCAT(
            LTRIM(RTRIM(ISNULL(a.Core, ''))),
            LTRIM(RTRIM(ISNULL(a.CP, ''))),
            ' X ',
            LTRIM(RTRIM(ISNULL(a.Sqmm, '')))
        ),
        OrderQty = LTRIM(RTRIM(ISNULL(a.OrderQty, ''))),
        PlanCutLen = LTRIM(RTRIM(a.PlanCutLen)),
        Drums = LTRIM(RTRIM(a.drums)),
        a.isMica,
        CondTypeTag =
            CASE
                WHEN a.CondType IS NULL THEN ''
                WHEN CHARINDEX('tin', LOWER(a.CondType)) > 0 THEN 'TIN'
                WHEN CHARINDEX('bare', LOWER(a.CondType)) > 0 THEN 'BARE'
                ELSE ''
            END,
        CPMult = CAST(
                    CASE UPPER(LEFT(LTRIM(RTRIM(ISNULL(a.CP, ''))), 1))
                        WHEN 'C' THEN 1
                        WHEN 'P' THEN 2
                        WHEN 'T' THEN 3
                        WHEN 'Q' THEN 4
                        ELSE 1
                    END
                 AS DECIMAL(18,4))
    FROM [PlanningSys].[instru].[data] a
    WHERE a.isDelete = 0
      AND a.insuStartDate IS NOT NULL
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia)) NOT IN ('', '-', '- ', '0', '0.0')
),
base AS
(
    SELECT
        ActualInsuStartDate = s.insuStartDate,
        s.JobNo,
        NoOfStrNum = TRY_CAST(NULLIF(s.NoOfStr, '-') AS INT),
        StrDiaNum = TRY_CAST(NULLIF(s.StrDia, '-') AS DECIMAL(10,4)),
        PlanCutLenNum = ISNULL(TRY_CAST(NULLIF(s.PlanCutLen, '-') AS DECIMAL(18,4)), 0),
        DrumsNum = ISNULL(TRY_CAST(NULLIF(s.Drums, '-') AS DECIMAL(18,4)), 0),
        IsMica = CAST(ISNULL(s.isMica, 0) AS INT),
        s.Size,
        s.OrderQty,
        s.CondTypeTag,
        s.CPMult,
        WeekBaseDate = CASE
            WHEN s.insuStartDate < @FromDate THEN @FromDate
            ELSE s.insuStartDate
        END
    FROM src s
),
metrics AS
(
    SELECT
        b.ActualInsuStartDate,
        b.JobNo,
        b.NoOfStrNum,
        b.StrDiaNum,
        b.IsMica,
        b.Size,
        b.OrderQty,
        b.CondTypeTag,
        Mtr = CAST(b.PlanCutLenNum * b.DrumsNum * b.CPMult AS DECIMAL(18,4)),
        Yr = YEAR(b.WeekBaseDate),
        Mon = MONTH(b.WeekBaseDate),
        WeekNo =
            CASE
                WHEN DAY(b.WeekBaseDate) <= 21
                    THEN (DAY(b.WeekBaseDate) - 1) / 7 + 1
                ELSE 4
            END
    FROM base b
    WHERE b.NoOfStrNum > 0
      AND b.StrDiaNum > 0
),
job_plan AS
(
    SELECT
        m.Yr,
        m.Mon,
        m.WeekNo,
        m.JobNo,
        m.NoOfStrNum,
        m.StrDiaNum,
        m.IsMica,
        m.Size,
        m.OrderQty,
        m.CondTypeTag,
        InsuStartDate = MIN(m.ActualInsuStartDate),
        JobTotalMtr = CAST(SUM(m.Mtr) AS DECIMAL(18,4))
    FROM metrics m
    GROUP BY
        m.Yr,
        m.Mon,
        m.WeekNo,
        m.JobNo,
        m.NoOfStrNum,
        m.StrDiaNum,
        m.IsMica,
        m.Size,
        m.OrderQty,
        m.CondTypeTag
),
src_jobs AS
(
    SELECT DISTINCT
        JobNo
    FROM job_plan
),
job_prod AS
(
    SELECT
        p.EffectiveJobNo,
        ProdMtr = CAST(SUM(p.PQty) AS DECIMAL(18,4))
    FROM
    (
        SELECT
            EffectiveJobNo = LTRIM(RTRIM(I.JOBNo)),
            PQty = ISNULL(I.PQty, 0)
        FROM TRADEZ.dbo.Ins I
        INNER JOIN src_jobs sj
            ON sj.JobNo = LTRIM(RTRIM(I.JOBNo))
        WHERE I.JobTransfer IS NULL
           OR LTRIM(RTRIM(I.JobTransfer)) = ''

        UNION ALL

        SELECT
            EffectiveJobNo = LTRIM(RTRIM(I.JobTransfer)),
            PQty = ISNULL(I.PQty, 0)
        FROM TRADEZ.dbo.Ins I
        INNER JOIN src_jobs sj
            ON sj.JobNo = LTRIM(RTRIM(I.JobTransfer))
        WHERE I.JobTransfer IS NOT NULL
          AND LTRIM(RTRIM(I.JobTransfer)) <> ''

    ) p
    GROUP BY p.EffectiveJobNo
),
job_week_calc AS
(
    SELECT
        jp.Yr,
        jp.Mon,
        jp.WeekNo,
        jp.JobNo,
        jp.NoOfStrNum,
        jp.StrDiaNum,
        jp.IsMica,
        jp.Size,
        jp.OrderQty,
        jp.CondTypeTag,
        jp.InsuStartDate,
        jp.JobTotalMtr,
        ProducedMtr = ISNULL(pr.ProdMtr, 0),
        CumJobDemand =
            SUM(jp.JobTotalMtr) OVER
            (
                PARTITION BY jp.JobNo
                ORDER BY jp.Yr, jp.Mon, jp.WeekNo, jp.StrDiaNum, jp.NoOfStrNum, jp.IsMica, jp.CondTypeTag
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            )
    FROM job_plan jp
    LEFT JOIN job_prod pr
        ON pr.EffectiveJobNo = jp.JobNo
),
job_week_balance AS
(
    SELECT
        jwc.Yr,
        jwc.Mon,
        jwc.WeekNo,
        jwc.JobNo,
        jwc.NoOfStrNum,
        jwc.StrDiaNum,
        jwc.IsMica,
        jwc.Size,
        jwc.OrderQty,
        jwc.CondTypeTag,
        jwc.InsuStartDate,
        RequiredMtr = CAST(jwc.JobTotalMtr AS DECIMAL(18,4)),
        ProdAllocatedMtr = CAST(
            CASE
                WHEN jwc.ProducedMtr >= jwc.CumJobDemand THEN jwc.JobTotalMtr
                WHEN jwc.ProducedMtr <= (jwc.CumJobDemand - jwc.JobTotalMtr) THEN 0
                ELSE jwc.ProducedMtr - (jwc.CumJobDemand - jwc.JobTotalMtr)
            END
        AS DECIMAL(18,4)),
        BalanceMtr = CAST(
            CASE
                WHEN jwc.ProducedMtr >= jwc.CumJobDemand THEN 0
                WHEN jwc.ProducedMtr <= (jwc.CumJobDemand - jwc.JobTotalMtr) THEN jwc.JobTotalMtr
                ELSE jwc.CumJobDemand - jwc.ProducedMtr
            END
        AS DECIMAL(18,4))
    FROM job_week_calc jwc
)
SELECT
    [Job No] = jwb.JobNo,
    [Size] = jwb.Size,
    [OrderQty] = jwb.OrderQty,
    [No Of Str] = jwb.NoOfStrNum,
    [Str Dia] = CAST(jwb.StrDiaNum AS DECIMAL(10,4)),
    [Is Mica] = CASE WHEN jwb.IsMica = 1 THEN 'Yes' ELSE '--' END,
    [Cond Type] = jwb.CondTypeTag,
    [Required Mtr] = CAST(jwb.RequiredMtr AS DECIMAL(18,2)),
    [Prod Mtr] = CAST(jwb.ProdAllocatedMtr AS DECIMAL(18,2)),
    [Balance Mtr] = CAST(
        jwb.BalanceMtr
    AS DECIMAL(18,2)),
    [Calculated Weight] = CAST(
        (
            jwb.StrDiaNum * jwb.StrDiaNum * 0.785 * jwb.NoOfStrNum *
            jwb.BalanceMtr * 0.0089
        )
    AS DECIMAL(18,2)),
    [insuStartDate] = CONVERT(VARCHAR(10), jwb.InsuStartDate, 23)
FROM job_week_balance jwb
WHERE jwb.RequiredMtr > 0
  AND jwb.BalanceMtr > 0
  AND (
        @ApplyWeekFilter = 0
        OR (jwb.Yr = @Yr AND jwb.Mon = @Mon AND jwb.WeekNo = @WeekNo)
      )
  AND (
        CASE
            WHEN jwb.RequiredMtr = 0 THEN 100
            ELSE (jwb.ProdAllocatedMtr * 100.0) / jwb.RequiredMtr
        END
      ) < @CompletionThreshold
ORDER BY jwb.Yr, jwb.Mon, jwb.WeekNo, jwb.StrDiaNum, jwb.NoOfStrNum, jwb.CondTypeTag, jwb.JobNo;
";

    $params = [$applyWeekFilter ? 1 : 0, $selectedYr, $selectedMon, $selectedWeekNo, $completionThreshold];
    $stmt = sqlsrv_query($con, $sql, $params);
    if ($stmt === false) {
        throw new Exception('Query failed: ' . print_r(sqlsrv_errors(), true));
    }

    $headers = [];
    $meta = sqlsrv_field_metadata($stmt);
    if ($meta !== false) {
        foreach ($meta as $field) {
            $headers[] = $field['Name'];
        }
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC)) {
        $rows[] = $row;
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($con);

    echo json_encode([
        'ok' => true,
        'headers' => $headers,
        'rows' => $rows,
        'weeks' => $weeks,
        'count' => count($rows),
        'completionThreshold' => $completionThreshold,
        'selectedWeek' => $applyWeekFilter ? [
            'yr' => $selectedYr,
            'mon' => $selectedMon,
            'weekNo' => $selectedWeekNo
        ] : null,
        'period' => $applyWeekFilter
            ? date('M', mktime(0, 0, 0, $selectedMon, 1, $selectedYr))
                . '-' . substr((string)$selectedYr, -2)
                . ' W' . $selectedWeekNo
            : 'All Weeks'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    restore_error_handler();
}
?>
