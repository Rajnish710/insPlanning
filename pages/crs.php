<?php
include('../dbcon.php');
$machines = [];

$sqlOpe = "SELECT machine FROM [plc696].[dbo].[dailyMpAllocation] where prodDate = '2025-03-17' AND shift = 'A' AND iid IN(1460, 1464, 1465, 1468, 1489, 1490, 1500, 1503, 1571, 1603)";
$runOpe = sqlsrv_query($conn, $sqlOpe);
$x = 0;
while ($rowOpe = sqlsrv_fetch_array($runOpe, SQLSRV_FETCH_ASSOC)) {
    $x++;
   $machines['CC'.$x] = 'abc';
}
// Print the array
echo '<pre>';
print_r($machines);
echo '</pre>';
