<?php 
$serverName = "192.168.0.245";
$connectionInfo = array("Database"=>"TRADEZ","UID"=>"sa","PWD"=>"suyog@123","CharacterSet" => "UTF-8");
$con =sqlsrv_connect($serverName,$connectionInfo);
date_default_timezone_set('Asia/Kolkata');
$queryArr = array(
"Conductor"=>"SELECT '0' as pOd,a.CondDepth as tOd,LayLength,a.NoOfStrand as noOfGi,a.StrandDia as armSize,'0' as Thick,'N.A' as color,a.Ope,a.Shift,b.Core,b.CorePair,b.SQMM from (select JOBNo,CondDepth,LayLength,NoOfStrand,StrandDia,Ope,Shift from Conductor where JOBNo = ':job' AND PLC_CycleNo = ':cycleNo') a join OrdDetail b on a.JOBNo = b.JOBNo",
"Ins"=>"SELECT '0' as pOd,a.DiaAct as tOd,'0' as LayLength,'0' as noOfGi,'0' as armSize,ThickAvg as Thick,Colour as color,a.Ope,a.Shift,b.Core,b.CorePair,b.SQMM from (select JOBNo,DiaAct,ThickAvg,Colour,StrandDia,Ope,Shift from Ins where PLC_CycleNo = ':cycleNo') a join OrdDetail b on a.JOBNo = b.JOBNo",
"LaidUp"=>"SELECT '0' as pOd,a.dia_start_act as tOd,LayLength,'0' as noOfGi,'0' as armSize,'0' as Thick,'N.A' as color,a.Ope,a.Shift,b.Core,b.CorePair,b.SQMM from (select JOBNo,dia_start_act,LayLength,Ope,Shift from LaidUp where JOBNo = ':job' AND PLC_CycleNo = ':cycleNo') a join OrdDetail b on a.JOBNo = b.JOBNo",
"InnerSth"=>"SELECT '0' as pOd,a.dia_start_atc as tOd,'0' as LayLength,'0' as noOfGi,'0' as armSize,a.t_avg_start as Thick,a.InnerColor as color,a.Ope,a.Shift,b.Core,b.CorePair,b.SQMM from (select JOBNo,dia_start_atc,t_avg_start,InnerColor,Ope,Shift from InnerSth where JOBNo = ':job' AND PLC_CycleNo = ':cycleNo') a join OrdDetail b on a.JOBNo = b.JOBNo",
"Armour"=>"SELECT DiaUnder as pOd,a.dia_start_act as tOd,'0' as LayLength,noOfGi,b.ArmSizeAvg as armSize,'0' as Thick,'N.A' as color,a.Ope,a.Shift,b.Core,b.CorePair,b.SQMM from (select JOBNo,DiaUnder,dia_start_act,IIF(strip_act = '',strip_req,strip_act) as noOfGi,Ope,Shift from Armour where JOBNo = ':job' AND PLC_CycleNo = ':cycleNo') a join OrdDetail b on a.JOBNo = b.JOBNo",
"OuterSth"=>"SELECT '0' as pOd,a.dia_start_atc as tOd,'0' as LayLength,'0' as noOfGi,'0' as armSize,a.t_avg_start as Thick,a.outercolor as color,a.Ope,a.Shift,b.Core,b.CorePair,b.SQMM from (select JOBNo,dia_start_atc,t_avg_start,outercolor,Ope,Shift from OuterSth where JOBNo = ':job' AND PLC_CycleNo = ':cycleNo') a join OrdDetail b on a.JOBNo = b.JOBNo"
);
// start function For Cycle Time calculate
function getTimeInSecond($cycleNo,$con,$dbName){
  $preCycle = $cycleNo + 1;
  $sql = "SELECT top 1 Status,DateAndTime FROM [$dbName].[dbo].MachineState WHERE CycleNo = '$preCycle' ORDER BY DateAndTime desc";
  $run = sqlsrv_query($con,$sql);
  $row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC);
  if ($row['Status'] == '1') {
     $sql1 = "SELECT Status, DateAndTime, DATEDIFF(SECOND, DateAndTime, LEAD(DateAndTime) OVER (ORDER BY DateAndTime)) AS DifferenceInSeconds FROM (SELECT '1' as Status,'".$row['DateAndTime']->format('Y-m-d H:i:s')."' as DateAndTime
          UNION ALL
          SELECT Status, DateAndTime FROM [$dbName].[dbo].MachineState WHERE CycleNo = '$cycleNo') tbl ORDER BY DateAndTime";
   }else{
      $sql1 = "SELECT Status, DateAndTime, DATEDIFF(SECOND, DateAndTime, LEAD(DateAndTime) OVER (ORDER BY DateAndTime)) AS DifferenceInSeconds FROM [$dbName].[dbo].MachineState WHERE CycleNo = '$cycleNo' ORDER BY DateAndTime";
   } 
	$result = sqlsrv_query($con,$sql1,array(),array("Scrollable" => SQLSRV_CURSOR_KEYSET));
	$count = sqlsrv_num_rows($result);
	if ($count > 0) {
	$run1 = sqlsrv_query($con,$sql1);
  $timeInterval = array();
	$onTime = 0;
	$offTime = 0;
	while($row1 = sqlsrv_fetch_array($run1, SQLSRV_FETCH_ASSOC)){
		array_push($timeInterval,$row1['DateAndTime']->format('Y-m-d H:i:s'));
		if ($row1['Status'] == '1') {
			$onTime += $row1['DifferenceInSeconds'];
		}else{
			$offTime += $row1['DifferenceInSeconds'];
		}
	}
	$minDate = min($timeInterval);
	$maxDate = max($timeInterval);
	return array("onTime"=>$onTime,"offTime"=>$offTime,"startTime"=>$minDate,"endTime"=>$maxDate);
	} else {
		return array("onTime"=>0,"offTime"=>0,"startTime"=>NULL,"endTime"=>NULL);
	}
	
		
} // End function 

// start function For Get prod data
function getProdData($job, $cycleNo, $con, $sql){
	  $sql = str_replace(':job', $job, $sql);
    $sql = str_replace(':cycleNo', $cycleNo, $sql);
    $result = sqlsrv_query($con,$sql,array(),array("Scrollable" => SQLSRV_CURSOR_KEYSET));
	$count = sqlsrv_num_rows($result);
	if ($count > 0) {
		$run = sqlsrv_query($con,$sql);
		$row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC);
		return $row;
	} else {
	return array("Core"=>'' ,"CorePair"=>'' ,"SQMM"=>'' ,"pOd"=>'' ,"tOd"=>'' ,"LayLength"=>'' ,"noOfGi"=>'' ,"armSize"=>'' ,"Thick"=>'' ,"color"=>'' ,"Ope"=>'' ,"Shift"=>'');
	}	
} // End function 
$sql = "SELECT ISNULL(MAX(iid), 0) as newIid FROM [PLC].[JobAssign]";
$run = sqlsrv_query($con,$sql);
$row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC);
$newId = $row['newIid'];

$sql1 = "SELECT TOP 10
 [IID]
,[dbname]
,[datetime]
,[jobno]
,[machine]
,[ProdStage]
,[cycle]
,[mtr]
  FROM [TRADEZ].[dbo].[PLCJobNo] 
  where IID > '$newId' AND 
  ProdStage IS NOT NULL AND 
  cycle > 1 AND dbname 
  IN (
 'P1_MC10',
'P1_MC12',
'P1_MC70',
'P1_MC75',
'P1_MC25',
'P1_MC50',
'P1_MC80',
'P1_MC14',
'P1_MC45',
'P1_EXT95',
'P2_MC101',
'P2_MC76',
'P2_MC33',
'P2_MC11',
'P1_MC58',
'P1_MC78',
'P1_MC20',
'P2_MC100',
'P1_MC34',
'ARM111',
'P2_MC17',
'P2_MC67',
'P2_MC35',
'P1_MC63',
'P1_MC73',
'P1_MC85',
'P1_MC21',
'P1_MC23',
'P2_MC98',
'P2_MC97',
'P2_MC31',
'P2_MC44',
'P2_MC55'
) order by IID";
$run1 = sqlsrv_query($con,$sql1);
while($row1 = sqlsrv_fetch_array($run1, SQLSRV_FETCH_ASSOC)){
	$dbName = $row1['dbname'];
	$cycleNo = $row1['cycle'];
	$job = $row1['jobno'];
	$stage = $row1['ProdStage'];
	$qry = $queryArr[$stage];
	$timeArr = getTimeInSecond($cycleNo,$con,$dbName);
	$prodArr = getProdData($job, $cycleNo, $con, $qry);

$sql2 = "INSERT INTO [PLC].[JobAssign]
           ([iid]
           ,[aDate]
           ,[jobno]
           ,[mc]
           ,[stage]
           ,[cycleNo]
           ,[mtr]
           ,[fromTime]
           ,[endTime]
           ,[onTime]
           ,[offTime]
           ,[Core]
           ,[CP]
           ,[SQMM]
           ,[pOd]
           ,[tOd]
           ,[LayLength]
           ,[noOfGi]
           ,[armSize]
           ,[Thick]
           ,[color]
           ,[Ope]
           ,[Shift])
     VALUES
           ('".$row1['IID']."'
           ,'".$row1['datetime']->format('Y-m-d')."'
           ,'".$row1['jobno']."'
           ,'".$row1['machine']."'
           ,'".$stage."'
           ,'".$cycleNo."'
           ,'".$row1['mtr']."'
           ,'".$timeArr['startTime']."'
           ,'".$timeArr['endTime']."'
           ,'".$timeArr['onTime']."'
           ,'".$timeArr['offTime']."'
           ,'".$prodArr['Core']."'
           ,'".$prodArr['CorePair']."'
           ,'".$prodArr['SQMM']."'
           ,'".$prodArr['pOd']."'
           ,'".$prodArr['tOd']."'
           ,'".$prodArr['LayLength']."'
           ,'".$prodArr['noOfGi']."'
           ,'".$prodArr['armSize']."'
           ,'".$prodArr['Thick']."'
           ,'".$prodArr['color']."'
           ,'".$prodArr['Ope']."'
           ,'".$prodArr['Shift']."')";

$run2 = sqlsrv_query($con,$sql2);
if ($run2 === false) {
           	print_r(sqlsrv_errors());
        }           


}



// echo '<pre>';
// print_r($timeArr);
// echo '</pre>';


 ?>