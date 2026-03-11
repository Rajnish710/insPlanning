<?php
// requirementPDF.php - Generate Requirement PDF (Drawing, Tinning, Bunching, Mica)
require_once '../package/TCPDF-main/tcpdf.php';
include '../includes/dbcon.php';

try {
    // Validate input
    $fromDate = $_GET['fromDate'] ?? '2026-03-15';
    $mon = (int)($_GET['mon'] ?? 0);
    $weekNo = (int)($_GET['weekNo'] ?? 0);
    $yr = (int)($_GET['yr'] ?? 0);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
        throw new Exception('Invalid date format');
    }

    // SQL Query - same as Drawing Planning with all required fields
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
        CondType   = ISNULL(a.CondType, ''),
        isMica     = 0,
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
        CondType   = ISNULL(a.CondType, ''),
        isMica     = ISNULL(a.isMica, 0),
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
        StrDiaNum = TRY_CAST(NULLIF(s.StrDia, '-') AS DECIMAL(18,6)),
        NoOfStrNum = TRY_CAST(NULLIF(s.NoOfStr, '-') AS INT),
        Qty = CAST(
                    ISNULL(TRY_CAST(NULLIF(s.PlanCutLen, '-') AS DECIMAL(18,4)), 0) *
                    ISNULL(TRY_CAST(NULLIF(s.Drums, '-') AS DECIMAL(18,4)), 0) *
                    s.CPMult
                 AS DECIMAL(18,4)),
        Weight = CAST(
                    TRY_CAST(NULLIF(s.StrDia, '-') AS DECIMAL(18,6)) * 
                    TRY_CAST(NULLIF(s.StrDia, '-') AS DECIMAL(18,6)) * 0.785 * 
                    TRY_CAST(NULLIF(s.NoOfStr, '-') AS INT) *
                    (ISNULL(TRY_CAST(NULLIF(s.PlanCutLen, '-') AS DECIMAL(18,4)), 0) *
                     ISNULL(TRY_CAST(NULLIF(s.Drums, '-') AS DECIMAL(18,4)), 0) *
                     s.CPMult) * 0.0089
                 AS DECIMAL(18,4)),
        s.CondType,
        s.isMica,
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
    WHERE TRY_CAST(NULLIF(s.NoOfStr, '-') AS INT) > 0
      AND TRY_CAST(NULLIF(s.StrDia, '-') AS DECIMAL(18,6)) > 0
      AND dt.Mon = @Mon
      AND dt.WeekNo = @WeekNo
      AND dt.Yr = @Yr
),

-- Aggregations by different groupings
drawing_agg AS (
    SELECT
        StrDiaNum,
        DrawingKgs = SUM(Weight)
    FROM calc
    GROUP BY StrDiaNum
),

tinning_agg AS (
    SELECT
        StrDiaNum,
        HasTinning = CASE WHEN CHARINDEX('tin', LOWER(CondType)) > 0 THEN 1 ELSE 0 END,
        TinningKgs = SUM(CASE WHEN CHARINDEX('tin', LOWER(CondType)) > 0 THEN Weight ELSE 0 END)
    FROM calc
    GROUP BY StrDiaNum, CASE WHEN CHARINDEX('tin', LOWER(CondType)) > 0 THEN 1 ELSE 0 END
),

bunching_agg AS (
    SELECT
        StrDiaNum,
        NoOfStrNum,
        BunchingKgs = SUM(CASE WHEN StrDiaNum > 0.37 THEN Weight ELSE 0 END)
    FROM calc
    GROUP BY StrDiaNum, NoOfStrNum
),

mica_agg AS (
    SELECT
        StrDiaNum,
        NoOfStrNum,
        MicaMtr = SUM(Qty)
    FROM calc
    WHERE isMica = 1
    GROUP BY StrDiaNum, NoOfStrNum
)

SELECT DISTINCT
    c.StrDiaNum,
    c.NoOfStrNum,
    d.DrawingKgs,
    t.TinningKgs,
    b.BunchingKgs,
    m.MicaMtr,
    CASE WHEN d.DrawingKgs > 0 OR t.TinningKgs > 0 THEN 0 ELSE 1 END AS SortOrder
FROM (
    -- Get unique StrDia + NoOfStr combinations for BUNCHING & MICA
    SELECT DISTINCT
        StrDiaNum,
        NoOfStrNum
    FROM calc
) c
LEFT JOIN drawing_agg d ON c.StrDiaNum = d.StrDiaNum
LEFT JOIN tinning_agg t ON c.StrDiaNum = t.StrDiaNum AND t.HasTinning = 1
LEFT JOIN bunching_agg b ON c.StrDiaNum = b.StrDiaNum AND c.NoOfStrNum = b.NoOfStrNum
LEFT JOIN mica_agg m ON c.StrDiaNum = m.StrDiaNum AND c.NoOfStrNum = m.NoOfStrNum
ORDER BY c.StrDiaNum, SortOrder, c.NoOfStrNum;
";

    // Execute with parameters
    $params = [$fromDate, $mon, $weekNo, $yr];
    $stmt = sqlsrv_query($con, $sql, $params);
    
    if ($stmt === false) {
        throw new Exception("Query failed: " . implode(", ", sqlsrv_errors()[0] ?? []));
    }

    // Fetch data
    $requirements = [];
    $processedStrDia = [];  // Track which StrDia we've shown Drawing/Tinning for

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $noOfStrNum = (int)$row['NoOfStrNum'];    
        $strDiaNum = (float)$row['StrDiaNum'];
        $drawingKgs = (float)($row['DrawingKgs'] ?? 0);
        $tinningKgs = (float)($row['TinningKgs'] ?? 0);
        $bunchingKgs = (float)($row['BunchingKgs'] ?? 0);
        $micaMtr = (float)($row['MicaMtr'] ?? 0);
        
        // Only add if at least one requirement exists
        if ($drawingKgs > 0 || $tinningKgs > 0 || $bunchingKgs > 0 || $micaMtr > 0) {
            
            // For Drawing & Tinning, show only for first NoOfStr of this StrDia
            $showDrawingTinning = !in_array($strDiaNum, $processedStrDia);
            
            $requirements[] = [
                'StrDiaNum' => $strDiaNum,
                'NoOfStrNum' => $noOfStrNum,
                'DrawingKgs' => $showDrawingTinning ? $drawingKgs : 0,
                'TinningKgs' => $showDrawingTinning ? $tinningKgs : 0,
                'BunchingKgs' => $bunchingKgs,
                'MicaMtr' => $micaMtr
            ];
            
            // Mark this StrDia as processed for Drawing/Tinning
            if ($showDrawingTinning) {
                $processedStrDia[] = $strDiaNum;
            }
        }
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($con);

    // Create PDF - LANDSCAPE
    $pdf = new TCPDF('L');
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->AddPage();

    // Build HTML content
    $html = '
    <style>
        body { font-family: helvetica; margin: 0; padding: 0; }
        .header-section {
            margin-bottom: 8px;
            padding: 6px 0;
        }
        .title { 
            font-size: 16px; 
            font-weight: bold; 
            color: #1e5082; 
            text-align: center; 
            margin: 0;
            padding: 4px 0 2px 0;
            line-height: 1.2;
        }
        .subtitle { 
            font-size: 9px; 
            color: #505050; 
            text-align: center; 
            margin: 0;
            padding: 2px 0 0 0;
            line-height: 1.1;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .header-main {
            background-color: #1e5082;
            color: white;
            font-weight: bold;
            text-align: center;
            vertical-align: middle !important;
            border: 1px solid #000000;
            padding: 5px 2px;
            height: 10mm;
            line-height: 1.3;
        }
        .header-main-drawing {
            background-color: #4682c8;
        }
        .header-main-tinning {
            background-color: #8c64b4;
        }
        .header-main-bunching {
            background-color: #be7850;
        }
        .header-main-mica {
            background-color: #78aa50;
        }
        .header-sub {
            color: white;
            font-weight: bold;
            font-size: 8px;
            text-align: center;
            vertical-align: middle !important;
            border: 1.5px solid #000000;
            padding: 3px 2px;
            height: 7mm;
            line-height: 1.2;
        }
        .header-sub-drawing {
            background-color: #6b9dd8;
        }
        .header-sub-tinning {
            background-color: #b094ca;
        }
        .header-sub-bunching {
            background-color: #d9996b;
        }
        .header-sub-mica {
            background-color: #98c261;
        }
        .size-cell {
            text-align: center;
            vertical-align: middle;
            border: 1px solid #000000;
            padding: 3px 2px;
            font-weight: bold;
            font-size: 9px;
            background-color: #e8f0f8;
            height: 7.5mm;
            line-height: 1.3;
        }
        .strand-cell {
            text-align: center;
            vertical-align: middle;
            border: 1px solid #000000;
            padding: 3px 2px;
            font-weight: bold;
            font-size: 9px;
            background-color: #e8f0f8;
            height: 7.5mm;
            line-height: 1.3;
        }
        .data-cell {
            text-align: right;
            vertical-align: middle;
            border: 1px solid #000000;
            padding: 3px 3px;
            font-size: 9px;
            height: 7.5mm;
            line-height: 1.3;
        }
        .row-even {
            background-color: #ffffff;
        }
        .row-odd {
            background-color: #f5f9fc;
        }
    </style>
    
    <div class="header-section">
        <div class="title">MATERIAL REQUIREMENT PLANNING</div>
        <div class="subtitle">Month ' . $mon . ' | Week ' . $weekNo . ' | Year ' . $yr . ' | Date: ' . date('d-m-Y') . '</div>
    </div>
    
    <table class="data-table">
        <!-- Header Row 1: Main Categories -->
        <tr>
            <td class="header-main" rowspan="2" style="width: 5%;">NoOf<br>Str</td>
            <td class="header-main" rowspan="2" style="width: 5%;">Str<br>Dia</td>
            <td class="header-main header-main-drawing" colspan="3" style="width: 23%;">DRAWING (Kgs)</td>
            <td class="header-main header-main-tinning" colspan="3" style="width: 23%;">TINNING (Kgs)</td>
            <td class="header-main header-main-bunching" colspan="3" style="width: 23%;">BUNCHING (Kgs)</td>
            <td class="header-main header-main-mica" colspan="3" style="width: 23%;">MICA (Mtr)</td>
        </tr>
        <!-- Header Row 2: Sub-headers -->
        <tr>
            <td class="header-sub header-sub-drawing">Previous</td>
            <td class="header-sub header-sub-drawing">Current</td>
            <td class="header-sub header-sub-drawing">Total</td>
            <td class="header-sub header-sub-tinning">Previous</td>
            <td class="header-sub header-sub-tinning">Current</td>
            <td class="header-sub header-sub-tinning">Total</td>
            <td class="header-sub header-sub-bunching">Previous</td>
            <td class="header-sub header-sub-bunching">Current</td>
            <td class="header-sub header-sub-bunching">Total</td>
            <td class="header-sub header-sub-mica">Previous</td>
            <td class="header-sub header-sub-mica">Current</td>
            <td class="header-sub header-sub-mica">Total</td>
        </tr>';
    
    // Data rows
    $rowCount = 0;

    foreach ($requirements as $req) {
        $rowClass = ($rowCount % 2 == 0) ? 'row-even' : 'row-odd';
        
        $Strands = $req['NoOfStrNum'];
        $size = number_format($req['StrDiaNum'], 3, '.', '');
        
        // Add values with proper formatting
        $drawingKgs = $req['DrawingKgs'] > 0 ? number_format((float)$req['DrawingKgs'], 0, '.', ',') : '0';
        $tinningKgs = $req['TinningKgs'] > 0 ? number_format((float)$req['TinningKgs'], 0, '.', ',') : '0';
        $bunchingKgs = $req['BunchingKgs'] > 0 ? number_format((float)$req['BunchingKgs'], 0, '.', ',') : '0';
        $micaMtr = $req['MicaMtr'] > 0 ? number_format((float)$req['MicaMtr'], 0, '.', ',') : '0';
        
        $html .= '
        <tr class="' . $rowClass . '">
            <td class="strand-cell">' . $Strands . '</td>
            <td class="size-cell">' . $size . '</td>
            <td class="data-cell">0</td>
            <td class="data-cell">' . $drawingKgs . '</td>
            <td class="data-cell">' . $drawingKgs . '</td>
            <td class="data-cell">0</td>
            <td class="data-cell">' . $tinningKgs . '</td>
            <td class="data-cell">' . $tinningKgs . '</td>
            <td class="data-cell">0</td>
            <td class="data-cell">' . $bunchingKgs . '</td>
            <td class="data-cell">' . $bunchingKgs . '</td>
            <td class="data-cell">0</td>
            <td class="data-cell">' . $micaMtr . '</td>
            <td class="data-cell">' . $micaMtr . '</td>
        </tr>';
        
        $rowCount++;
    }
    
    $html .= '
    </table>';
    
    // Write HTML to PDF
    $pdf->writeHTML($html, true, false, true, false, '');

    // Output PDF
    $pdf->Output("Requirement_M{$mon}_W{$weekNo}_{$yr}.pdf", 'I');

} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}
?>
