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
SET NOCOUNT ON;

DECLARE @FromDate DATE = ?;
DECLARE @Mon INT = ?;
DECLARE @WeekNo INT = ?;
DECLARE @Yr INT = ?;

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
        CondTypeTag = ISNULL(a.CondType, ''),
        CPMult = CAST(1 AS DECIMAL(18,4))
    FROM [PlanningSys].[control].[data] a
    WHERE a.isDelete = 0
      AND a.insuStartDate IS NOT NULL
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
        CondTypeTag = ISNULL(a.CondType, ''),
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
      AND a.insuStartDate IS NOT NULL
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia))  NOT IN ('', '-', '- ', '0', '0.0')
),

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
				WHEN s.CondTypeTag LIKE '%tin%'  THEN 'TIN'
				WHEN s.CondTypeTag LIKE '%bare%' THEN 'BARE'
				ELSE ''
			END,
        s.CPMult,
        WeekBaseDate =
            CASE
                WHEN s.insuStartDate < @FromDate THEN @FromDate
                ELSE s.insuStartDate
            END
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
      AND b.StrDiaNum > 0
),

job_plan AS
(
    SELECT
        Yr,
        Mon,
        WeekNo,
        JobNo,
        NoOfStrNum,
        StrDiaNum,
        isMica,
        CondTypeTag,
        JobTotalMtr = SUM(Mtr),
        JobTotalWeight = SUM(Weight)
    FROM metrics
    GROUP BY
        Yr, Mon, WeekNo, JobNo,
        NoOfStrNum, StrDiaNum, isMica, CondTypeTag
),

week_demand AS
(
    SELECT
        Yr,
        Mon,
        WeekNo,
        NoOfStrNum,
        StrDiaNum,
        isMica,
        CondTypeTag,
        WeekDemandMtr = SUM(JobTotalMtr)
    FROM job_plan
    GROUP BY
        Yr, Mon, WeekNo,
        NoOfStrNum, StrDiaNum, isMica, CondTypeTag
),

prod AS
(
    SELECT
        NoOfStrNum  = [noOfStrands],
        StrDiaNum   = CAST([strDia] AS DECIMAL(10,4)),
        CondTypeTag = ISNULL([condType], ''),
        isMica,
        ProducedMtr = SUM(ISNULL([Length], 0))
    FROM [PlanningSys].[prod].[Outward]
    GROUP BY
        [noOfStrands],
        CAST([strDia] AS DECIMAL(10,4)),
        ISNULL([condType], ''),
        isMica
),

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
        ProducedMtr = ISNULL(p.ProducedMtr, 0),
        CumDemand =
            SUM(d.WeekDemandMtr) OVER
            (
                PARTITION BY d.NoOfStrNum, d.StrDiaNum, d.isMica, d.CondTypeTag
                ORDER BY d.Yr, d.Mon, d.WeekNo
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            )
    FROM week_demand d
    LEFT JOIN prod p
        ON  p.NoOfStrNum  = d.NoOfStrNum
        AND p.StrDiaNum   = d.StrDiaNum
        AND p.isMica      = d.isMica
        AND p.CondTypeTag = d.CondTypeTag
),

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
        ProducedMtr,
        WeekDemandMtr,
        WeekBalanceMtr =
            CAST(
                CASE
                    WHEN ProducedMtr >= CumDemand THEN 0
                    WHEN ProducedMtr <= (CumDemand - WeekDemandMtr) THEN WeekDemandMtr
                    ELSE CumDemand - ProducedMtr
                END
            AS DECIMAL(18,4)),
        WeekAllocatedMtr =
            CAST(
                CASE
                    WHEN ProducedMtr >= CumDemand THEN WeekDemandMtr
                    WHEN ProducedMtr <= (CumDemand - WeekDemandMtr) THEN 0
                    ELSE ProducedMtr - (CumDemand - WeekDemandMtr)
                END
            AS DECIMAL(18,4))
    FROM week_calc
),

job_seq AS
(
    SELECT
        jp.Yr,
        jp.Mon,
        jp.WeekNo,
        jp.JobNo,
        jp.NoOfStrNum,
        jp.StrDiaNum,
        jp.isMica,
        jp.CondTypeTag,
        jp.JobTotalMtr,
        jp.JobTotalWeight,
        wb.WeekDemandMtr,
        wb.WeekAllocatedMtr,
        wb.WeekBalanceMtr,
        JobCumMtr =
            SUM(jp.JobTotalMtr) OVER
            (
                PARTITION BY jp.Yr, jp.Mon, jp.WeekNo,
                             jp.NoOfStrNum, jp.StrDiaNum, jp.isMica, jp.CondTypeTag
                ORDER BY jp.JobNo
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            )
    FROM job_plan jp
    INNER JOIN week_balance wb
        ON  wb.Yr          = jp.Yr
        AND wb.Mon         = jp.Mon
        AND wb.WeekNo      = jp.WeekNo
        AND wb.NoOfStrNum  = jp.NoOfStrNum
        AND wb.StrDiaNum   = jp.StrDiaNum
        AND wb.isMica      = jp.isMica
        AND wb.CondTypeTag = jp.CondTypeTag
),

job_final AS
(
    SELECT
        Yr,
        Mon,
        WeekNo,
        JobNo,
        NoOfStrNum,
        StrDiaNum,
        isMica,
        CondTypeTag,
        JobTotalMtr,
        JobTotalWeight,

        ProdAllocatedMtr =
            CAST(
                CASE
                    WHEN WeekAllocatedMtr >= JobCumMtr THEN JobTotalMtr
                    WHEN WeekAllocatedMtr <= (JobCumMtr - JobTotalMtr) THEN 0
                    ELSE WeekAllocatedMtr - (JobCumMtr - JobTotalMtr)
                END
            AS DECIMAL(18,4)),

        BalanceMtr =
            CAST(
                CASE
                    WHEN WeekAllocatedMtr >= JobCumMtr THEN 0
                    WHEN WeekAllocatedMtr <= (JobCumMtr - JobTotalMtr) THEN JobTotalMtr
                    ELSE JobCumMtr - WeekAllocatedMtr
                END
            AS DECIMAL(18,4))
    FROM job_seq
)

SELECT
    jf.JobNo,
    NoOfStr = jf.NoOfStrNum,
    StrDia = CAST(jf.StrDiaNum AS DECIMAL(10,4)),
    jf.isMica,
    jf.CondTypeTag,

    RequiredMtr = CAST(jf.JobTotalMtr AS DECIMAL(18,4)),
    RequiredWeight = CAST(jf.JobTotalWeight AS DECIMAL(18,4)),

    ProdAllocatedMtr = CAST(jf.ProdAllocatedMtr AS DECIMAL(18,4)),
    ProdAllocatedWeight = CAST(
        CASE
            WHEN jf.JobTotalMtr = 0 THEN 0
            ELSE (jf.JobTotalWeight * jf.ProdAllocatedMtr / jf.JobTotalMtr)
        END
    AS DECIMAL(18,4)),

    BalanceMtr = CAST(jf.BalanceMtr AS DECIMAL(18,4)),
    BalanceWeight = CAST(
        CASE
            WHEN jf.JobTotalMtr = 0 THEN 0
            ELSE (jf.JobTotalWeight * jf.BalanceMtr / jf.JobTotalMtr)
        END
    AS DECIMAL(18,4)),

    ProdStatus =
        CASE
            WHEN jf.ProdAllocatedMtr <= 0 THEN 'Pending'
            WHEN jf.BalanceMtr <= 0 THEN 'Completed'
            ELSE 'Partial'
        END

FROM job_final jf
WHERE jf.Mon = @Mon
  AND jf.WeekNo = @WeekNo
  AND jf.Yr = @Yr
ORDER BY jf.StrDiaNum, jf.CondTypeTag, jf.isMica, jf.JobNo;
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
