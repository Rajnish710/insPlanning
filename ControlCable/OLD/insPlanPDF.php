<?php
// session_start();
// Include the PDF class
require_once "../package/TCPDF-main/tcpdf.php";
// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF {

    //Page header
    // public function Header() {
      // Connecting with database
       // get the current page break margin
    //    $bMargin = $this->getBreakMargin();
    //    // get current auto-page-break mode
    //    $auto_page_break = $this->AutoPageBreak;
    //    // disable auto-page-break
    //    $this->SetAutoPageBreak(false, 0);
    //    // set bacground image
    //    $img_file = 'img/bgPDF.jpg';
    //    $this->Image($img_file, 0, 30, 210, 297, '', '', '', false, 300, '', false, false, 0);
    //    // restore auto-page-break status
    //    $this->SetAutoPageBreak($auto_page_break, $bMargin);
    //    // set the starting point for the page content
    //    $this->setPageMark();

    // }
    // Page footer
    // public function Footer() {
        // Position at 15 mm from bottom
        // $this->SetY(-8);
        // Set font
    //    $this->SetFont('helvetica', 'I', 8);
        /*$this->Cell(0, 10, '**This Is Computer Generated PO Signature Is Not Required**', 0, false, 'C', 0, '', 0, false, 'T', 'M');*/
        // Page number
        // $this->Cell(0, 0, '**This Is Computer Generated PO Signature Is Not Required**', 0, false, 'C', 0, '', 0, false, 'T', 'M');

        // $this->SetY(-12);
        // Set font
    //    $this->SetFont('helvetica', 'I', 8.5);
        /*$this->Cell(0, 10, '**This Is Computer Generated PO Signature Is Not Required**', 0, false, 'C', 0, '', 0, false, 'T', 'M');*/
        // Page number
        // $this->Cell(0, 0, 'Subject To Vadodara Jurisdiction', 0, false, 'C', 0, '', 0, false, 'T', 'M');
    // }
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

// set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
// $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(1, 1, 3); //top, bottom, right
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
// $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 2);
$pdf->AddPage();
// Logo          
            date_default_timezone_set('Asia/Kolkata');

            include '../includes/dbcon45.php';
            include '../includes/dbcon.php';
            $curDate = date('d-m-Y');
            $currentTime = date( 'h:i:s A', time () );
            $mcno = $_GET['id'];

            $imagePath = '../img/sepl_2.png';
            $x = 5;
            $y = 3;
            $width = 24;
            $height = 24;
            $pdf->Image($imagePath, $x, $y, $width, $height);

    $title .= '<table style="width:50%;">';
        $title .= '<tr>';
            $title .= '<td style="text-align=right"><h1>Insulation Planning</h1></td>';
        $title .= '</tr>';
    $title .= '</table>';
$pdf->SetFont("times", "A", 13);           
$pdf->SetY(11);
$pdf->SetX(70);
$pdf->writeHTML($title, true, false, false, false, 'C');

$add = '<table style="width:100%;">';
        $add .= '<tr>';
            $add .= '<td>Date : '.$curDate.'</td>';
        $add .= '</tr>';
    $add .= '</table>';
$pdf->SetFont("times", "A", 10.5);           
$pdf->SetY(25);
$pdf->SetX(260);
$pdf->writeHTML($add, true, false, false, false, 'L');

    $hr .= '<table style="width:100%;">';
        $hr .= '<tr>';
            $hr .= '<td><hr></td>';
        $hr .= '</tr>';
    $hr .= '</table>';
$pdf->SetFont("times", "A", 11);           
$pdf->SetY(31);
$pdf->SetX(3);
$pdf->writeHTML($hr, true, false, false, false, 'L');

    $hr1 .= '<table style="width:100%;">';
        $hr1 .= '<tr>';
            $hr1 .= '<td><hr></td>';
        $hr1 .= '</tr>';
    $hr1 .= '</table>';
$pdf->SetFont("times", "A", 11);           
$pdf->ln(-9);
$pdf->SetX(3);
$pdf->writeHTML($hr1, true, false, false, false, 'L');

$p .= '<table style="width:100%;">';
        $p .= '<tr>';
            $p .= '<th style="text-align:left"><b> => Machine No :</b> '.$mcno.'</th>';
        $p .= '</tr>';
$p .= '</table>';
$pdf->SetFont("times", "A", 14);           
$pdf->ln(-7);
$pdf->SetX(5);
$pdf->writeHTML($p, true, false, false, false, 'L');

$tbl .= '<table style="width:100%; border: 0.2px solid gray; padding: 3px; padding-top:2px; padding-bottom:2px">';
        $tbl .= '<tr>';
            $tbl .= '<th style="text-align:center; border: 0.2px solid gray; background-color: #dddddd;" width="5%"><b>Plan No</b></th>';
            $tbl .= '<th style="text-align:center; border: 0.2px solid gray; background-color: #dddddd;" width="7%"><b>Size</b></th>';
            $tbl .= '<th style="text-align:center; border: 0.2px solid gray; background-color: #dddddd;" width="22%"><b>Cond Type</b></th>';
            $tbl .= '<th style="text-align:center; border: 0.2px solid gray; background-color: #dddddd;" width="19%"><b>Insu Type</b></th>';
            $tbl .= '<th style="text-align:center; border: 0.2px solid gray; background-color: #dddddd;" width="10%"><b>Insu Color</b></th>';
            $tbl .= '<th style="text-align:center; border: 0.2px solid gray; background-color: #dddddd;" width="10%"><b>Drum No</b></th>';
            $tbl .= '<th style="text-align:center; border: 0.2px solid gray; background-color: #dddddd;" width="10%"><b>CutLen</b></th>';
            $tbl .= '<th style="text-align:center; border: 0.2px solid gray; background-color: #dddddd;" width="7%"><b>No Of Drum</b></th>';
            $tbl .= '<th style="text-align:center; border: 0.2px solid gray; background-color: #dddddd;" width="10%"><b>Total Core</b></th>';
        $tbl .= '</tr>';

        // $sql = "SELECT id.McNo,id.Sqmm,id.CondType,id.InsuType,id.NoOfStr,id.StrDia,id.InsuColor,igp.drumNo,igp.cutLen,igp.noofDrums,igp.planningNo FROM [PlanningSys].[instru].[data] id JOIN [PlanningSys].[instru].[givenPlanning] igp ON id.id = igp.iid where id.McNo = '$mcno'";

        $sql = "SELECT id.CondType,id.InsuType,id.NoOfStr,id.StrDia,id.InsuColor,igp.drumNo,igp.cutLen,igp.noofDrums,igp.planningNo,LEAD(igp.planningNo) OVER (ORDER BY igp.planningNo) AS nextPlanning FROM [PlanningSys].[instru].[data] id JOIN [PlanningSys].[instru].[givenPlanning] igp ON id.id = igp.iid where id.McNo = '$mcno' order by igp.planningNo";
        $run = sqlsrv_query($con, $sql);

        $prevPlanningNo = '';  // To store the previous planningNo
        $isFirstRow = true;  // Flag to detect the first row of a group
        
       
        while($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)){

            $string = $row['InsuType'];
            $cleanedString = str_replace("/% /", "", $string);

            $size = $row['NoOfStr'].'/'.$row['StrDia'];
            $tcore = $row['cutLen']*$row['noofDrums'];

            // if($row['planningNo'] != $row['nextPlanning']){
            //     $tbl .= '<tr>';
            //     $tbl .= '<td colspan="9" style="border-bottom: 0.3px solid black;"></td>'; // Example of a separator row
            //     $tbl .= '</tr>';
                
            // }

        $tbl .= '<tr>';
        $tbl .= '<th style="border: 0.3px solid black; text-align:center;">'.$row['planningNo'].'</th>';
            $tbl .= '<th style="border: 0.3px solid black; text-align:center;">'.$size.'</th>';
            $tbl .= '<th style="border: 0.3px solid black; text-align:center;">'.$row['CondType'].'</th>';
            $tbl .= '<th style="border: 0.3px solid black; text-align:center;">'.$cleanedString.'</th>';
            $tbl .= '<th style="border: 0.3px solid black; text-align:center;">'.$row['InsuColor'].'</th>';
            $tbl .= '<th style="border: 0.3px solid black; text-align:center;">'.$row['drumNo'].'</th>';
            $tbl .= '<th style="border: 0.3px solid black; text-align:center;">'.$row['cutLen'].'</th>';
            $tbl .= '<th style="border: 0.3px solid black; text-align:center;">'.$row['noofDrums'].'</th>';
            $tbl .= '<th style="border: 0.3px solid black; text-align:center;">'.$tcore.'</th>';
            
        $tbl .= '</tr>';

        // Check the condition, and if true, create a new blank row
        if($row['planningNo'] != $row['nextPlanning']){
            $tbl .= '<tr>';
            $tbl .= '<td colspan="9" style="height: 10px;"></td>'; // Blank row with 9 columns and some height
            $tbl .= '</tr>';
        }

        }

$tbl .= '</table>';
$pdf->SetFont("times", "A", 11);           
$pdf->ln(-3);
$pdf->SetX(5);
$pdf->writeHTML($tbl, true, false, false, false, 'L');

// Clean any content of the output buffer
ob_end_clean();

//Close and output PDF document
$pdf->Output('InsuPlan.pdf', 'I');


?>