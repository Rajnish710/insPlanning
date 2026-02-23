<?php
// session_start();
// Include the PDF class
require_once "../package/TCPDF-main/tcpdf.php";
// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF
{
    //Page header
    public function Header()
    {
        /*$this->SetFont('times', 'B', 16);
        $this->SetY(13);
        $this->SetX(2);
        $this->Cell(0,10,'Travelling Voucher',0,0,'C');*/
    }

    // Page footer
    public function Footer()
    {
        $this->SetY(-10);
        // Set font
        $this->SetFont('helvetica', 'b', 8);
        $this->SetTextColor(153, 0, 0);
        /*$this->Cell(0, 10, '**This Is Computer Generated Report Signature Is Required**', 0, false, 'C', 0, '', 0, false, 'M', 'M');*/
        // Page number
        $this->Cell(0, 11, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// $pageLayout = array(120, 43);
// create new PDF document
$pdf = new MYPDF('L', 'mm', 'A4',  true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Nicola Asuni');
$pdf->SetTitle('Insu Plan');
$pdf->SetSubject('TCPDF Tutorial');
$pdf->SetKeywords('TCPDF, PDF, example, test, guide');



// set margins
$pdf->SetMargins(3, 3, 3); //left, top, right
$pdf->SetHeaderMargin(3);


// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 6); // 3 bottom margin where page break auto
$pdf->AddPage();
// Logo          
// date_default_timezone_set('Asia/Kolkata');

include '../includes/dbcon45.php';
$curDate = date('d-M-Y');
$mcno = $_GET['mc'];
$planno = $_GET['planno'];

$imagePath = '../img/sepl_2.png';
$x = 3;
$y = 3;
$width = 16;
$height = 16;
$pdf->Image($imagePath, $x, $y, $width, $height);

// Set font and positioning
$pdf->SetFont("helvetica", "UB", 15); // Set font

$pdf->SetY(10); 
$pdf->SetX(110); 
$pdf->Cell(50, 10, 'Control Insulation Planning', 0, 1, 'C');

$pdf->SetFont("times", "A", 13);
$pdf->SetY(5); 
$pdf->SetX(250); 
$pdf->Cell(30, 5, 'Date: '.$curDate, 0, 1, 'L');

$pdf->SetFont("times", "B", 13);
$pdf->SetY(10); 
$pdf->SetX(250); 
$pdf->Cell(30, 5, 'Planning No: '.$planno, 0, 1, 'L');

$pdf->SetFont("times", "B", 13);
$pdf->SetY(16); 
$pdf->SetX(250); 
$pdf->Cell(30, 5, 'Machine No: '.$mcno, 0, 1, 'L');

$pdf->SetY(18); 
$pdf->SetX(3); 
$pdf->Cell(30, 5, '______________________________________________________________________________________________________________________________', 0, 1, 'L');




$tbl = '
        <style>
        table {
            padding: 3px;
        }
        td {
            border: 0.2px solid black;
        }
        th {
            text-align:center;
            border: 0.3px solid gray;
            background-color: #dddddd;
        }
        .bigtext{
            font-size:9px; 
            text-align:left;
        }
        .smalltext{
            font-size:10px; 
            text-align:center;
        }
    </style>
';

$tbl .= '<table style="width:100%; padding: 3px;">';
        $tbl .= '<thead><tr>';
            $tbl .= '<th width="90px">JobNo</th>';
            $tbl .= '<th width="50px">Size</th>';
            $tbl .= '<th width="53px">Cond Size</th>';
            $tbl .= '<th width="55px">Mica</th>';
            $tbl .= '<th width="100px">Cond Type</th>';
            $tbl .= '<th width="90px">Insu Type</th>';
            $tbl .= '<th width="70px">Insu Color</th>';
            $tbl .= '<th width="35px">Pair No</th>';
            $tbl .= '<th width="55px">Order CutLength</th>';
            $tbl .= '<th width="33px">Tol %</th>';
            $tbl .= '<th width="55px">Plan CutLength</th>';
            $tbl .= '<th width="40px">Plan Drums</th>';
            $tbl .= '<th width="55px">Total Core</th>';
            $tbl .= '<th width="45px">TakeUp Drum</th>';
        $tbl .= '</tr></thead>';



        $sql = "WITH givenTbl AS (
    SELECT 
    gp1.planningNo,
    gp1.drumNo,
    gp1.color,
    gp1.pairNo,
    SUM(gp1.cutLen * gp1.noofDrums) AS totalCore,
    COUNT(gp1.drumNo) AS drCnt,
    drcnt1.drumCount AS drCnt1
FROM 
    [PlanningSys].[control].givenPlanning AS gp1
LEFT JOIN (
    SELECT 
        planningNo, 
        drumNo,
        color,
        COUNT(drumNo) AS drumCount
    FROM 
        [PlanningSys].[control].givenPlanning
    GROUP BY 
        planningNo, drumNo, color
) AS drcnt1
ON 
    gp1.planningNo = drcnt1.planningNo AND gp1.drumNo = drcnt1.drumNo
GROUP BY 
    gp1.planningNo, gp1.drumNo, gp1.color, gp1.pairNo, drcnt1.drumCount
)
SELECT 
g.id,
g.mcno,
g.planningNo,
FORMAT(g.createdAt,'dd-MMM-yy') as date,
d.JobNo,
CONCAT(d.Core, d.CP, ' X ', d.Sqmm) AS Size,
CONCAT(d.NoOfStr, '/', d.StrDia) AS condSize,
g.isMica,
d.CondType,
d.InsuType,
c.color,
d.PairNo,
d.OrdCutLength,
d.planTol,
g.cutLen,
g.noofDrums,
c.totalCore,
c.drCnt,
c.drCnt1,
g.drumNo as TakeUp,
LAG(CONCAT(g.drumNo,'_',g.pairNo)) OVER (ORDER BY g.color,g.planningNo,TRY_CAST(LEFT(g.drumNo, PATINDEX('%[^0-9]%', g.drumNo) - 1) AS INT),g.pairNo,g.cutLen) AS nextTakeUp
FROM 
[PlanningSys].[control].[data] d
JOIN 
[PlanningSys].[control].givenPlanning g ON d.id = g.iid
JOIN 
givenTbl c ON c.planningNo = g.planningNo AND c.drumNo = g.drumNo AND c.pairNo = g.pairNo
WHERE g.isProdComplete  = 0 AND g.planningNo = '$planno'";
        $run = sqlsrv_query($conn, $sql); 
        while($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)){

            $cleanedString = str_replace("/% /", "", $row['InsuType']);
            $tcore = $row['totalCore'];
            $xx = explode("_", $row['nextTakeUp']);
            $isCoreMerge = ($row['TakeUp'].'_'.$row['PairNo'] != $row['nextTakeUp']);
            $isTakeUpMerge = $row['TakeUp'] != $xx[0];

            $mergeCore = $row['drCnt'];
            $mergeTakeUp = $row['drCnt1'];

        $tbl .= '<tr>';
            $tbl .= '<td width="90px" class="bigtext">'.$row['JobNo'].'</td>';
            $tbl .= '<td width="50px" class="bigtext">'.$row['Size'].'</td>';
            $tbl .= '<td width="53px" class="smalltext">'.$row['condSize'].'</td>';
            $tbl .= '<td width="55px" class="smalltext">'.$row['isMica'].'</td>';
            $tbl .= '<td width="100px" class="bigtext">'.$row['CondType'].'</td>';
            $tbl .= '<td width="90px" class="bigtext">'.$cleanedString.'</td>';
            $tbl .= '<td width="70px" class="bigtext">'.$row['color'].'</td>';
            $tbl .= '<td width="35px" class="smalltext">'.$row['PairNo'].'</td>';
            $tbl .= '<td width="55px" class="smalltext">'.$row['OrdCutLength'].'</td>';
            $tbl .= '<td width="33px" class="smalltext">'.$row['planTol'].'%</td>';
            $tbl .= '<td width="55px" class="smalltext">'.$row['cutLen'].'</td>';
            $tbl .= '<td width="40px" class="smalltext">'.$row['noofDrums'].'</td>';
            if ($isCoreMerge) {
                $tbl .= '<td width="55px" rowspan="'.$mergeCore.'" class="smalltext">'.$tcore.'</td>';
            }if ($isTakeUpMerge) {
                $tbl .= '<td width="45px" rowspan="'.$mergeTakeUp.'" class="smalltext">'.$row['TakeUp'].'</td>';
            }
            
        $tbl .= '</tr>';

        }




$tbl .= '</table>';
$pdf->SetFont("times", "A", 11);           
$pdf->ln(-3);
$pdf->SetY(28);
$pdf->SetX(3);
$pdf->writeHTML($tbl, true, false, false, false, 'L');

// Clean any content of the output buffer
ob_end_clean();

//Close and output PDF document
$pdf->Output('InsuPlan.pdf', 'I');


?>