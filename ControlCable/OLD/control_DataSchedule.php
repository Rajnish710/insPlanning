<?php 
include('../includes/dbcon45.php');
date_default_timezone_set('Asia/Kolkata');
$logMessage = sprintf("\n\n[%s] local.INFO: ", date('Y-m-d H:i:s'));
$rows = array();
$condType = array('A','B','C','B/C','50%B & 50%C');
$sqmmArr = array('0.5','0.75','1','1.5','2.5','4','6','10');
$rsArr = array("Thousand"=>0.01, "Lacs"=>1, "Crore"=>100);
$params = array();
$options = array("Scrollable" => SQLSRV_CURSOR_KEYSET);

$colorArr = array( 
    "Black Core Numbering" => "Black", 
    "Black Core Numbering on Insulation & Tape" => "Black", 
    "Black Core Numbering on Tape" => "Black", 
    "Blue with Black longitudinal Strip" => "Blue", 
    "Green insulation with Yellow stripes" => "Green", 
    "Grey Core Numbering" => "Grey", 
    "Natural with number printed" => "Natural", 
    "White Core Numbering" => "White"
    );

$sql = "SELECT top 10
    a.ordid,
    a.JobNo,
    a.Core,
    a.CorePair,
    a.SQMM,
    a.Remark,
    a.Qty,
    a.CableType,
    a.Condtypep,
    d.condgrd,
    IIF(d.insgrd2 = '', d.insgrd1, CONCAT(d.insgrd1, ' /', d.insper, '% /', d.insgrd2)) AS insGrade,
    a.BasicRate,
    CONVERT(VARCHAR(11), CONVERT(DATE, b.Plnsentto, 103), 106) AS Plnsentto,
    b.OfferNo,
    b.TolM,
    b.TolP,
    b.Value,
    b.RsSign,
    b.LD,
    b.LDASPER,
    b.LDSDate,
    CONVERT(VARCHAR(11), b.SiteDate, 106) AS SiteDate,
    a.ColorType,
    a.col1,
    a.col2,
    a.col3,
    a.col4,
    a.col5,
    c.NoofDrum,
    c.CQty
FROM 
    OrdDetail a
JOIN 
    OrdMaster b ON a.OrdID = b.OrdID
JOIN 
    CutLength c ON a.JobNo = c.JobNo
JOIN 
    [backward_calc].[dbo].pvcGrade d ON a.JobNo = d.Job
WHERE 
    a.OrdID >= 1984990903
    AND a.InsTypeP <> ''
    AND a.CorePair = 'C' 
    AND a.Conductor = 'CU' 
    AND a.SQMM < 10
    --AND NOT EXISTS (SELECT 1 FROM [PlanningSys].[control].[data] pd WHERE pd.JobNo = a.JobNo)
    AND a.JobNo IN (select jobNo from [RunningJobs].[dbo].[RunningJobs] where isFinalComplete = 0)
ORDER BY 
    b.SiteDate,
    a.JobNo";
$run = sqlsrv_query($conn,$sql);
$x = 0;
while($row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC)){
	// set Planning Tolerance
	$sql1 = "SELECT CEILING(".$row['CQty']." * (1 + per * 0.01)) AS newCutLength FROM [PlanningSys].[master].[qtyTol] WHERE ".$row['CQty']." BETWEEN lenFrom AND lenTo AND minus = '".$row['TolM']."' AND plus = '".$row['TolP']."'";
	$result1 = sqlsrv_query($conn,$sql1,$params,$options);
    $count1 = sqlsrv_num_rows($result1);
    if ($count1 > 0) {
    	$run1 = sqlsrv_query($conn,$sql1);
		$row1 = sqlsrv_fetch_array($run1, SQLSRV_FETCH_ASSOC);
		$row['planLen'] = $row1['newCutLength'];
    } else {
    	$row['planLen'] = CEIL($row['CQty'] * 1.02);
    }
	// set conductor Size
	$condgrd = $row['condgrd'];
	$sqmm = $row['SQMM'];

	if (preg_match('/Stranded/', $row['Condtypep'])) {
		$type = 'Stranded';
		$noOfStr = 7;
	}else if(preg_match('/Solid/', $row['Condtypep'])){
		$type = 'Solid';
		$noOfStr = 1;
	}else{
		$type = '';
		$noOfStr = '';
	}
	if (in_array($condgrd, $condType) && $type != '' && in_array($sqmm, $sqmmArr)) {
		$sql2 = "SELECT [$condgrd] as grd from [PlanningSys].[master].[condSize] where type = '$type' and Size = '$sqmm'";
		$run2 = sqlsrv_query($conn,$sql2);
		$row2 = sqlsrv_fetch_array($run2, SQLSRV_FETCH_ASSOC);
		$row['noOfStr'] = $noOfStr;
		$row['StrDia'] = $row2['grd'];
	}else{
		$row['noOfStr'] = $noOfStr;
		$row['StrDia'] = '';
	}
	// Set Insulation Required Date
	$origin1 = date_create($row['SiteDate']);
    $target1 = date_create($row['Plnsentto']);                   
    $diff = date_diff($origin1, $target1)->format("%a");

    if ($target1 && $diff > 60) {
       $insuReqDate = date('Y-m-d', strtotime($row['SiteDate'] . ' -45 days'));
    }else if ($target1 && $diff <= 60){
       $insuReqDate = date('Y-m-d', strtotime($row['SiteDate'] . ' -22 days'));	
    }else{
    	$insuReqDate = date('Y-m-d', strtotime('+5 days'));
    }
    // Set LD Percentage
    if (preg_match('/([\d.]+)%/', $row['LD'], $matches)) {
        $ldWeekPer = (float)$matches[1];
    } else {
        $ldWeekPer = 0;
    }
    // set Ordvalue in lacs
    if (array_key_exists($row['RsSign'], $rsArr)) {
    	$offerVal = $rsArr[$row['RsSign']]*$row['Value'];
    }else{
    	$offerVal = 0;
    }

    // set mica 
    $isMica = ($row['Remark'] == 'Yes') ? 1 : 0;

    // set Insulation Capacity in table in Minutes per KM
    $sqlCap = "SELECT top 1 * from [PlanningSys].[master].[insuCapacity] order by ABS('".$row['SQMM']."' - size)";
    $runCap = sqlsrv_query($conn,$sqlCap);
    $rowCap = sqlsrv_fetch_array($runCap, SQLSRV_FETCH_ASSOC);
    $mtr = $isMica ? $rowCap['insuCapMica'] : $rowCap['insuCapCore'];
    $drawingCap = round((540/$rowCap['drawKgs'])*$rowCap['weight'], 4);
    $tinningCap = round((540/$rowCap['TinKgs'])*$rowCap['weight'], 4);
    $bunchCap = round((540/$rowCap['bunchKgs'])*$rowCap['weight'], 4);
    $insuCap = round((540/$mtr)*1000, 4);

    // Set Color
    	// $sql3 = "SELECT JobNo, Color, COUNT(Color) AS ColorCount FROM OrdColorIns UNPIVOT (Color FOR ColorColumn IN (color_1, color_2, color_3) ) AS Unpvt where JobNo = '".$row['JobNo']."' AND Color IS NOT NULL GROUP BY JobNo,Color";
        $sql3 = "SELECT color_1 AS Color, ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS PairNo FROM OrdColorIns where JobNo = '".$row['JobNo']."'";
    	$result = sqlsrv_query($conn,$sql3,$params,$options);
    	$count = sqlsrv_num_rows($result);
    	if ($count > 0) {
    		$run3 = sqlsrv_query($conn,$sql3);
			while($row3 = sqlsrv_fetch_array($run3, SQLSRV_FETCH_ASSOC)){
				$x++;
                $sqlin = "INSERT INTO [PlanningSys].[control].[data] ([DeliveryDate] ,[JobNo] ,[Core] ,[CP] ,[Sqmm], [isMica],[OrderQty] ,[TolM] ,[TolP] ,[OrdCutLength] ,[ord_NoofDrums] ,[CondGrade] ,[CondType] ,[InsuType] ,[NoOfStr] ,[StrDia] ,[PlanCutLen] ,[plan_NoofDrums], [multiplyDrums],[InsuColor] , [PairNo],[PlanningDate] ,[RequiredDate] ,[ordID] ,[enqNo] ,[valueAmt] ,[LdPer] ,[LdTerm] ,[createdAt] ,[createdBy],[drawingCap],[tinningCap],[bunchCap],[insuCap]) VALUES ('".$row['SiteDate']."', '".$row['JobNo']."', '".$row['Core']."', '".$row['CorePair']."', '".$row['SQMM']."', '".$isMica."', '".$row['Qty']."', '".$row['TolM']."', '".$row['TolP']."', '".$row['CQty']."', '".$row['NoofDrum']."', '".$row['condgrd']."', '".$row['Condtypep']."', '".$row['insGrade']."', '".$row['noOfStr']."', '".$row['StrDia']."', '".$row['planLen']."', '".$row['NoofDrum']."', '1','".$row3['Color']."','".$row3['PairNo']."', '".date('Y-m-d')."', '".$insuReqDate."', '".$row['ordid']."', '".$row['OfferNo']."', '".$offerVal."', '".$ldWeekPer."', '".$row['LDASPER']."', '".date('Y-m-d H:i:s')."', '','$drawingCap','$tinningCap','$bunchCap','$insuCap')";
                $runin = sqlsrv_query($conn,$sqlin);	
                if ($runin == false) {
                    error_log($logMessage.$row['JobNo'], 3, "error.log");
                }else{
                    print_r(sqlsrv_errors());
                }
            }

    	} else {
    		  if ($row['Core'] < 6) {
                $sqlCol = "SELECT ColName, ColValue
                            FROM (
                                SELECT Col1, Col2, Col3, Col4, Col5 
                                FROM OrdDetail 
                                WHERE jobno = '".$row['JobNo']."'
                            ) AS SourceTable
                            UNPIVOT (
                                ColValue FOR ColName IN (Col1, Col2, Col3, Col4, Col5)
                            ) AS UnpivotTable";
                $runCol = sqlsrv_query($conn,$sqlCol);            
                for ($i=1; $i <= $row['Core']; $i++) {
                    $rowCol = sqlsrv_fetch_array($runCol, SQLSRV_FETCH_ASSOC); 
                    $sqlin = "INSERT INTO [PlanningSys].[control].[data] ([DeliveryDate] ,[JobNo] ,[Core] ,[CP] ,[Sqmm], [isMica],[OrderQty] ,[TolM] ,[TolP] ,[OrdCutLength] ,[ord_NoofDrums] ,[CondGrade] ,[CondType] ,[InsuType] ,[NoOfStr] ,[StrDia] ,[PlanCutLen] ,[plan_NoofDrums], [multiplyDrums],[InsuColor] , [PairNo],[PlanningDate] ,[RequiredDate] ,[ordID] ,[enqNo] ,[valueAmt] ,[LdPer] ,[LdTerm] ,[createdAt] ,[createdBy],[drawingCap],[tinningCap],[bunchCap],[insuCap]) VALUES ('".$row['SiteDate']."', '".$row['JobNo']."', '".$row['Core']."', '".$row['CorePair']."', '".$row['SQMM']."', '".$isMica."', '".$row['Qty']."', '".$row['TolM']."', '".$row['TolP']."', '".$row['CQty']."', '".$row['NoofDrum']."', '".$row['condgrd']."', '".$row['Condtypep']."', '".$row['insGrade']."', '".$row['noOfStr']."', '".$row['StrDia']."', '".$row['planLen']."', '".$row['NoofDrum']."', '1','".$rowCol['ColValue']."','$i', '".date('Y-m-d')."', '".$insuReqDate."', '".$row['ordid']."', '".$row['OfferNo']."', '".$offerVal."', '".$ldWeekPer."', '".$row['LDASPER']."', '".date('Y-m-d H:i:s')."', '','$drawingCap','$tinningCap','$bunchCap','$insuCap')";
                    $runin = sqlsrv_query($conn,$sqlin);    
                    if ($runin == false) {
                        error_log($logMessage.$row['JobNo'], 3, "error.log");
                    }else{
                        print_r(sqlsrv_errors());
                    }
                }
                 
              } else {
                  for ($i=1; $i <= $row['Core']; $i++) {
                        $insColor = array_key_exists($row['ColorType'], $colorArr) ? $colorArr[$row['ColorType']] : $row['col1'];
                        $sqlin = "INSERT INTO [PlanningSys].[control].[data] ([DeliveryDate] ,[JobNo] ,[Core] ,[CP] ,[Sqmm], [isMica],[OrderQty] ,[TolM] ,[TolP] ,[OrdCutLength] ,[ord_NoofDrums] ,[CondGrade] ,[CondType] ,[InsuType] ,[NoOfStr] ,[StrDia] ,[PlanCutLen] ,[plan_NoofDrums], [multiplyDrums],[InsuColor] , [PairNo],[PlanningDate] ,[RequiredDate] ,[ordID] ,[enqNo] ,[valueAmt] ,[LdPer] ,[LdTerm] ,[createdAt] ,[createdBy],[drawingCap],[tinningCap],[bunchCap],[insuCap]) VALUES ('".$row['SiteDate']."', '".$row['JobNo']."', '".$row['Core']."', '".$row['CorePair']."', '".$row['SQMM']."', '".$isMica."', '".$row['Qty']."', '".$row['TolM']."', '".$row['TolP']."', '".$row['CQty']."', '".$row['NoofDrum']."', '".$row['condgrd']."', '".$row['Condtypep']."', '".$row['insGrade']."', '".$row['noOfStr']."', '".$row['StrDia']."', '".$row['planLen']."', '".$row['NoofDrum']."', '1','".$insColor.$i."','$i', '".date('Y-m-d')."', '".$insuReqDate."', '".$row['ordid']."', '".$row['OfferNo']."', '".$offerVal."', '".$ldWeekPer."', '".$row['LDASPER']."', '".date('Y-m-d H:i:s')."', '','$drawingCap','$tinningCap','$bunchCap','$insuCap')";
                        $runin = sqlsrv_query($conn,$sqlin);    
                        if ($runin == false) {
                            error_log($logMessage.$row['JobNo'], 3, "error.log");
                        }else{
                            print_r(sqlsrv_errors());
                        }

                  }
              }
              
    	}
}


// echo '<pre>';
// print_r($rows);
// echo '</pre>';
// echo json_encode($rows);

 ?>