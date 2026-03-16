<?php
header('Content-Type: application/json; charset=utf-8');
include __DIR__ . '/../includes/dbcon.php';

try {
    $completionThreshold = 95.0;

    $sql = "
SET NOCOUNT ON;

WITH src AS
(
    SELECT
        JobNo = LTRIM(RTRIM(a.JobNo)),
        NoOfStr = LTRIM(RTRIM(a.NoOfStr)),
        StrDia = LTRIM(RTRIM(a.StrDia)),
        Size = CONCAT(
            LTRIM(RTRIM(ISNULL(a.Core, ''))),
            LTRIM(RTRIM(ISNULL(a.CP, ''))),
            ' X ',
            LTRIM(RTRIM(ISNULL(a.Sqmm, '')))
        ),
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
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia)) NOT IN ('', '-', '- ', '0', '0.0')

    UNION ALL

    SELECT
        JobNo = LTRIM(RTRIM(a.JobNo)),
        NoOfStr = LTRIM(RTRIM(a.NoOfStr)),
        StrDia = LTRIM(RTRIM(a.StrDia)),
        Size = CONCAT(
            LTRIM(RTRIM(ISNULL(a.Core, ''))),
            LTRIM(RTRIM(ISNULL(a.CP, ''))),
            ' X ',
            LTRIM(RTRIM(ISNULL(a.Sqmm, '')))
        ),
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
      AND LTRIM(RTRIM(a.JobNo)) <> ''
      AND LTRIM(RTRIM(a.NoOfStr)) NOT IN ('', '-', '- ', '0', '0.0')
      AND LTRIM(RTRIM(a.StrDia)) NOT IN ('', '-', '- ', '0', '0.0')
),
base AS
(
    SELECT
        s.JobNo,
        NoOfStrNum = TRY_CAST(NULLIF(s.NoOfStr, '-') AS INT),
        StrDiaNum = TRY_CAST(NULLIF(s.StrDia, '-') AS DECIMAL(10,4)),
        PlanCutLenNum = ISNULL(TRY_CAST(NULLIF(s.PlanCutLen, '-') AS DECIMAL(18,4)), 0),
        DrumsNum = ISNULL(TRY_CAST(NULLIF(s.Drums, '-') AS DECIMAL(18,4)), 0),
        IsMica = CAST(ISNULL(s.isMica, 0) AS INT),
        s.Size,
        s.CondTypeTag,
        s.CPMult
    FROM src s
),
job_plan AS
(
    SELECT
        b.JobNo,
        NoOfStrNum = MAX(b.NoOfStrNum),
        StrDiaNum = MAX(b.StrDiaNum),
        IsMica = MAX(b.IsMica),
        Size = MAX(b.Size),
        CondTypeTag = MAX(b.CondTypeTag),
        RequiredMtr = CAST(SUM(b.PlanCutLenNum * b.DrumsNum * b.CPMult) AS DECIMAL(18,4))
    FROM base b
    WHERE b.NoOfStrNum > 0
      AND b.StrDiaNum > 0
    GROUP BY b.JobNo
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
        INNER JOIN job_plan jp
            ON jp.JobNo = LTRIM(RTRIM(I.JOBNo))
        WHERE I.JobTransfer IS NULL
           OR LTRIM(RTRIM(I.JobTransfer)) = ''

        UNION ALL

        SELECT
            EffectiveJobNo = LTRIM(RTRIM(I.JobTransfer)),
            PQty = ISNULL(I.PQty, 0)
        FROM TRADEZ.dbo.Ins I
        INNER JOIN job_plan jp
            ON jp.JobNo = LTRIM(RTRIM(I.JobTransfer))
        WHERE I.JobTransfer IS NOT NULL
          AND LTRIM(RTRIM(I.JobTransfer)) <> ''
    ) p
    GROUP BY p.EffectiveJobNo
)
SELECT
    [Job No] = jp.JobNo,
    [Size] = jp.Size,
    [No Of Str] = jp.NoOfStrNum,
    [Str Dia] = CAST(jp.StrDiaNum AS DECIMAL(10,4)),
    [Is Mica] = CASE WHEN jp.IsMica = 1 THEN 'Yes' ELSE 'No' END,
    [Cond Type] = jp.CondTypeTag,
    [Required Mtr] = CAST(jp.RequiredMtr AS DECIMAL(18,2)),
    [Prod Mtr] = CAST(ISNULL(pr.ProdMtr, 0) AS DECIMAL(18,2)),
    [Balance Mtr] = CAST(
        CASE
            WHEN ISNULL(pr.ProdMtr, 0) >= jp.RequiredMtr THEN 0
            ELSE jp.RequiredMtr - ISNULL(pr.ProdMtr, 0)
        END
    AS DECIMAL(18,2)),
    [Calculated Weight] = CAST(
        (
            jp.StrDiaNum * jp.StrDiaNum * 0.785 * jp.NoOfStrNum *
            CASE
                WHEN ISNULL(pr.ProdMtr, 0) >= jp.RequiredMtr THEN 0
                ELSE jp.RequiredMtr - ISNULL(pr.ProdMtr, 0)
            END * 0.0089
        )
    AS DECIMAL(18,2))
FROM job_plan jp
LEFT JOIN job_prod pr
    ON pr.EffectiveJobNo = jp.JobNo
WHERE jp.RequiredMtr > 0
  AND (
        CASE
            WHEN jp.RequiredMtr = 0 THEN 100
            ELSE (ISNULL(pr.ProdMtr, 0) * 100.0) / jp.RequiredMtr
        END
      ) < ?
ORDER BY jp.StrDiaNum, jp.NoOfStrNum, jp.CondTypeTag, jp.JobNo;
";

    $params = [$completionThreshold];
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
        'count' => count($rows),
        'completionThreshold' => $completionThreshold
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
