<?php 
include '../includes/dbcon45.php';

$rows = array();

$sql = "WITH base_raw AS (
    SELECT
        a.insuStartDate,
        a.NoOfStr,
        StrDiaNum = TRY_CAST(NULLIF(a.StrDia,'-') AS decimal(10,4)),
        Mtr = CAST(b.cutLen * b.noOfDrums AS decimal(18,4)),
        b.isMica,
        CondTypeTag =
            CASE
                WHEN a.CondType IS NULL THEN ''
                WHEN LOWER(a.CondType) LIKE '%tin%'  THEN 'TIN'
                WHEN LOWER(a.CondType) LIKE '%bare%' THEN 'BARE'
                ELSE ''
            END
    FROM [PlanningSys].[control].[data] a
    JOIN [PlanningSys].[control].[givenPlanning] b
        ON a.id = b.iid
    WHERE b.isProdComplete = 0
      AND TRY_CAST(NULLIF(a.StrDia,'-') AS decimal(10,4)) IS NOT NULL

    UNION ALL

    SELECT
        a.insuStartDate,
        a.NoOfStr,
        StrDiaNum = TRY_CAST(NULLIF(a.StrDia,'-') AS decimal(10,4)),
        Mtr = CAST(b.cutLen * b.noOfDrums AS decimal(18,4)),
        b.isMica,
        CondTypeTag =
            CASE
                WHEN a.CondType IS NULL THEN ''
                WHEN LOWER(a.CondType) LIKE '%tin%'  THEN 'TIN'
                WHEN LOWER(a.CondType) LIKE '%bare%' THEN 'BARE'
                ELSE ''
            END
    FROM [PlanningSys].[instru].[data] a
    JOIN [PlanningSys].[instru].[givenPlanning] b
        ON a.id = b.iid
    WHERE b.isProdComplete = 0
      AND TRY_CAST(NULLIF(a.StrDia,'-') AS decimal(10,4)) IS NOT NULL
),
base AS (
    SELECT
        PlanDate = DATEADD(DAY, -7, r.insuStartDate),
        r.NoOfStr,
        r.StrDiaNum,
        r.Mtr,
        r.isMica,
        r.CondTypeTag,
        Yr  = YEAR(DATEADD(DAY, -7, r.insuStartDate)),
        Mon = MONTH(DATEADD(DAY, -7, r.insuStartDate)),
        WeekNo =
            CASE
                WHEN DAY(DATEADD(DAY, -7, r.insuStartDate)) <= 21
                     THEN ((DAY(DATEADD(DAY, -7, r.insuStartDate)) - 1) / 7) + 1
                ELSE 4
            END
    FROM base_raw r
)
SELECT
    MonthName = CONCAT(
                    DATENAME(MONTH, DATEFROMPARTS(b.Yr, b.Mon, 1)),
                    '-', RIGHT(CONVERT(varchar(4), b.Yr), 2)
                ),
    b.WeekNo,
    WeekStart = DATEFROMPARTS(b.Yr, b.Mon,
                    CASE b.WeekNo
                        WHEN 1 THEN 1
                        WHEN 2 THEN 8
                        WHEN 3 THEN 15
                        ELSE 22
                    END),
    WeekEnd   = DATEFROMPARTS(b.Yr, b.Mon,
                    CASE b.WeekNo
                        WHEN 1 THEN 7
                        WHEN 2 THEN 14
                        WHEN 3 THEN 21
                        ELSE DAY(EOMONTH(DATEFROMPARTS(b.Yr, b.Mon, 1)))
                    END),
    b.NoOfStr,

    -- only numeric size (merged)
    StrDia = CAST(b.StrDiaNum AS decimal(10,4)),

    b.isMica,
    b.CondTypeTag,
    TotalMtr = SUM(b.Mtr),
    Kgs = CAST(
            (b.StrDiaNum * b.StrDiaNum * 0.785 * b.NoOfStr * 8.9 * SUM(b.Mtr) / 1000.0)
            AS decimal(18,0)
          )
FROM base b
GROUP BY
    b.Yr, b.Mon, b.WeekNo,
    b.NoOfStr, b.StrDiaNum,
    b.isMica, b.CondTypeTag
ORDER BY
    b.Yr ASC,
    b.Mon ASC,
    b.WeekNo ASC,
    b.StrDiaNum ASC,
    b.NoOfStr ASC;
";


$run = sqlsrv_query($conn, $sql);
while ($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)) {
    $row['WeekStart'] = $row['WeekStart']->format('Y-m-d');
    $row['WeekEnd'] = $row['WeekEnd']->format('Y-m-d');
    $rows[] = $row;
}


echo json_encode($rows);
?>
