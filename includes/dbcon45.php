<?php 
$serverName = "192.168.0.245";
$connectionInfo = array("Database"=>"TRADEZ","UID"=>"sa","PWD"=>"suyog@123","CharacterSet" => "UTF-8");
$conn =sqlsrv_connect($serverName,$connectionInfo);

if($conn) {
    /*echo "connection established.<br />";*/
    
}else{
    echo "connection could not be established.<br />";
    die(print_r( sqlsrv_errors(), true));
}


// find nearest value

// Function to find the nearest key
function findNearestKey($arr, $value) {
    $keys = array_keys($arr);
    $keys = array_map('floatval', $keys); // Convert keys to float for comparison
    $nearest = null;

    foreach ($keys as $key) {
        if ($nearest === null || abs($key - $value) < abs($nearest - $value)) {
            $nearest = $key;
        }
    }

    return strval($nearest); // Convert back to string since keys are strings
}
?>