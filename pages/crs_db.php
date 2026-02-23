<?php
include('../includes/dbcon.php');
date_default_timezone_set('Asia/Kolkata');
$date = date('m/d/Y h:i:s a', time());

session_start();
$user = $_SESSION['username'];

if (isset($_POST['butsave'])) {
    $srno = $_POST['srno'];
    $comment = $_POST['comment'];
    $response = $_POST['response'];
    $inqNo = $_POST['inqNo'];
    $verson = $_POST['version'];
    $ok = $_POST['ok'];
    $id = $_POST['id'];

    foreach ($srno as $key => $value) {
        $sql = "SELECT MAX(id) AS id FROM CRS";
        $run = sqlsrv_query($con, $sql);
        $row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC);
        $tid = $row['id'] + 1;
        
        if($id[$key] == '' AND $comment[$key] != '' ){
            $sql1 = "INSERT INTO CRS (id, srno, comment, response, inqNo, version, createdBy) VALUES ('$tid', '$value', '".$comment[$key]."', '".$response[$key]."', '$inqNo', '$verson', '$user')";
       }
       else if($id[$key] != '' AND $ok[$key] == '1'){
            $sql1 = "UPDATE CRS SET srno='".$value."', comment='".$comment[$key]."', response='".$response[$key]."', inqNo='$inqNo', version='$verson', updatedAt='$date', updatedBy='$user' WHERE id = '".$id[$key]."'";
       }else {
            $sql1 = "";
       }
       $run1 = sqlsrv_query($con, $sql1);
    }
     if($run1){
        echo "<script>alert('Data Saved successfully')</script>";
        echo "<script>window.location.href='crs.php'</script>";
       
    }else{
        
        die(print_r(sqlsrv_errors(),true));
    }
}



?>