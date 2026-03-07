<?php
// drawingPlanPDF.php - Generate Drawing Planning PDF using TCPDF
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

    // SQL Query for Drawing Planning - grouped by StrDiaNum
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

    // Fetch and group data by StrDiaNum
    $dataByStrDia = [];
    $strDiaTotals = [];
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $strDia = $row['StrDiaNum'];
        
        if (!isset($dataByStrDia[$strDia])) {
            $dataByStrDia[$strDia] = [];
            $strDiaTotals[$strDia] = ['Mtr' => 0, 'Weight' => 0];
        }
        
        $dataByStrDia[$strDia][] = $row;
        $strDiaTotals[$strDia]['Mtr'] += (float)$row['Mtr'];
        $strDiaTotals[$strDia]['Weight'] += (float)$row['Weight'];
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($con);

    // Create PDF
    $pdf = new TCPDF();
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->AddPage();

    // Title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'Drawing Planning Report', 0, 1, 'C');
    
    // Period and date
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, "Period: Month {$mon} - Week {$weekNo} - {$yr}", 0, 1, 'L');
    $pdf->Cell(0, 6, "Generated: " . date('d-m-Y H:i:s'), 0, 1, 'L');
    $pdf->Ln(3);

    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(41, 128, 185);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(40, 6, 'Job No', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'Str Dia', 1, 0, 'C', true);
    $pdf->Cell(35, 6, 'Mtr', 1, 0, 'R', true);
    $pdf->Cell(35, 6, 'Weight', 1, 1, 'R', true);

    // Table data - grouped by StrDiaNum
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    
    $rowCount = 0;
    foreach ($dataByStrDia as $strDia => $jobs) {
        $isFirstRow = true;
        
        foreach ($jobs as $row) {
            if ($rowCount % 2 == 0) {
                $pdf->SetFillColor(240, 240, 240);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell(40, 6, $row['JobNo'], 1, 0, 'L', true);
            $pdf->Cell(30, 6, number_format((float)$row['StrDiaNum'], 4), 1, 0, 'C', true);
            $pdf->Cell(35, 6, number_format((float)$row['Mtr'], 2), 1, 0, 'R', true);
            $pdf->Cell(35, 6, number_format((float)$row['Weight'], 2), 1, 1, 'R', true);
            
            $rowCount++;
            $isFirstRow = false;
        }
        
        // StrDiaNum subtotal row
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(200, 220, 240);
        
        $pdf->Cell(40, 6, 'SUBTOTAL', 1, 0, 'R', true);
        $pdf->Cell(30, 6, number_format($strDia, 4), 1, 0, 'C', true);
        $pdf->Cell(35, 6, number_format($strDiaTotals[$strDia]['Mtr'], 2), 1, 0, 'R', true);
        $pdf->Cell(35, 6, number_format($strDiaTotals[$strDia]['Weight'], 2), 1, 1, 'R', true);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Ln(2);
    }

    // Output PDF
    // $pdf->Output("Drawing_Plan_M{$mon}_W{$weekNo}_{$yr}.pdf", 'D');
    $pdf->Output("Drawing_Plan_M{$mon}_W{$weekNo}_{$yr}.pdf", 'I');

} catch (Exception $e) {
    http_response_code(500);
    die('Error: ' . $e->getMessage());
}
?>
