<?php 
if (true) {
    include('../includes/dbcon45.php');
date_default_timezone_set('Asia/Kolkata');
$logMessage = sprintf("\n\n[%s] local.INFO: ", date('Y-m-d H:i:s'));
if ($_POST['noOfDrums'] == $_POST['ord_NoofDrums']) {
  $sql = "UPDATE [PlanningSys].[instru].[data]
   SET [CondGrade] = '".$_POST['condGrade']."'
      ,[InsuType] = '".$_POST['insuType']."'
      ,[NoOfStr] = '".$_POST['noOfStr']."'
      ,[StrDia] = '".$_POST['strDia']."'
      ,[PlanCutLen] = '".$_POST['planCutLen']."'
      ,[plan_NoofDrums] = '".$_POST['noOfDrums']."'
      ,[InsuColor] = '".$_POST['insuColor']."'
      ,[McNo] = '".$_POST['mc']."'
      ,[PlanningDate] = '".$_POST['planningDate']."'
      ,[RequiredDate] = '".$_POST['reqDate']."'
      ,[DrumNo] = '".$_POST['drumNo']."'
      ,[Remark] = '".$_POST['remark']."'
      ,[isGiven] = 1
      ,[updatedAt] = '".date('Y-m-d H:i:s')."'
      ,[updatedBy] = ''
 WHERE id = '".$_POST['iid']."'";

}else{
   $sql = "BEGIN TRANSACTION
UPDATE [PlanningSys].[instru].[data]
   SET [CondGrade] = '".$_POST['condGrade']."'
      ,[InsuType] = '".$_POST['insuType']."'
      ,[NoOfStr] = '".$_POST['noOfStr']."'
      ,[StrDia] = '".$_POST['strDia']."'
      ,[PlanCutLen] = '".$_POST['planCutLen']."'
      ,[plan_NoofDrums] = '".$_POST['noOfDrums']."'
      ,[InsuColor] = '".$_POST['insuColor']."'
      ,[McNo] = '".$_POST['mc']."'
      ,[PlanningDate] = '".$_POST['planningDate']."'
      ,[RequiredDate] = '".$_POST['reqDate']."'
      ,[DrumNo] = '".$_POST['drumNo']."'
      ,[Remark] = '".$_POST['remark']."'
      ,[isGiven] = 1
      ,[updatedAt] = '".date('Y-m-d H:i:s')."'
      ,[updatedBy] = ''
      ,[partialPlan] = '".$_POST['iid']."'
 WHERE id = '".$_POST['iid']."'

   INSERT INTO [PlanningSys].[instru].[data]
           ([DeliveryDate]
           ,[JobNo]
           ,[Core]
           ,[CP]
           ,[Sqmm]
           ,[isMica]
           ,[OrderQty]
           ,[TolM]
           ,[TolP]
           ,[OrdCutLength]
           ,[ord_NoofDrums]
           ,[CondGrade]
           ,[CondType]
           ,[InsuType]
           ,[NoOfStr]
           ,[StrDia]
           ,[PlanCutLen]
           ,[plan_NoofDrums]
           ,[multiplyDrums]
           ,[InsuColor]
           ,[PairNo]
           ,[McNo]
           ,[PlanningDate]
           ,[RequiredDate]
           ,[DrumNo]
           ,[Remark]
           ,[ordID]
           ,[cableCat]
           ,[enqNo]
           ,[valueAmt]
           ,[LdPer]
           ,[LdTerm]
           ,[LdDate]
           ,[createdAt]
           ,[createdBy]
           ,[updatedAt]
           ,[updatedBy]
           ,[isGiven]
           ,[isDelete]
           ,[drawingCap]
           ,[tinningCap]
           ,[bunchCap]
           ,[insuCap]
           ,[partialPlan])
           SELECT 
           [DeliveryDate]
           ,[JobNo]
           ,[Core]
           ,[CP]
           ,[Sqmm]
           ,[isMica]
           ,[OrderQty]
           ,[TolM]
           ,[TolP]
           ,[OrdCutLength]
           ,'".($_POST['ord_NoofDrums'] - $_POST['noOfDrums'])."' 
           ,'".$_POST['condGrade']."'
           ,[CondType]
           ,'".$_POST['insuType']."'
           ,'".$_POST['noOfStr']."'
           ,'".$_POST['strDia']."'
           ,'".$_POST['planCutLen']."'
           ,'".($_POST['ord_NoofDrums'] - $_POST['noOfDrums'])."'
           ,[multiplyDrums]
           ,'".$_POST['insuColor']."'
           ,[PairNo]
           ,''
           ,'".$_POST['planningDate']."'
           ,'".$_POST['reqDate']."'
           ,'".$_POST['drumNo']."'
           ,'".$_POST['remark']."'
           ,[ordID]
           ,[cableCat]
           ,[enqNo]
           ,[valueAmt]
           ,[LdPer]
           ,[LdTerm]
           ,[LdDate]
           ,'".date('Y-m-d H:i:s')."'
           ,[createdBy]
           ,'".date('Y-m-d H:i:s')."'
           ,[updatedBy]
           ,'0'
           ,[isDelete]
           ,[drawingCap]
           ,[tinningCap]
           ,[bunchCap]
           ,[insuCap]
           ,'".$_POST['iid']."' 
           From [PlanningSys].[instru].[data] WHERE id = '".$_POST['iid']."' 
           COMMIT";
}
// error_log($logMessage.$sql, 3, "error.log");
 $run = sqlsrv_query($conn, $sql);
 if ($run) {
 	echo "yes";
 }else{
 	print_r(sqlsrv_errors());
 }

}
 ?>