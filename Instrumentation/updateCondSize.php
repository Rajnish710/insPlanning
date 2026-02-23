<?php 
include('../dbcon.php');
$condType = ['A', 'B', 'C', 'B/C', '50%B & 50%C'];
$sqldb = "SELECT  id,JobNo,CondGrade,Sqmm,CondType from [PlanningSys].[instru].[data] 
            where NoOfStr = '0' AND insuStartDate is not NULL";

$rundb = sqlsrv_query($conn, $sqldb);
while ($rowdb = sqlsrv_fetch_array($rundb, SQLSRV_FETCH_ASSOC)) {
    $id = $rowdb['id'];

    $noOfStr = 0; 
    $strDia  = 0; 

    $grade = trim((string)$rowdb['CondGrade']);
    if (!in_array($grade, $condType, true)) {
        $grade = 'B';
    }
    $gradeCaseSql = "
    CASE 
        WHEN ? = 'A' THEN s.[A]
        WHEN ? = 'B' THEN s.[B]
        WHEN ? = 'C' THEN s.[C]
        WHEN ? = 'B/C' THEN COALESCE(NULLIF(s.[B], ''), NULLIF(s.[C], ''), '')
        WHEN ? = '50%B & 50%C' THEN COALESCE(NULLIF(s.[B], ''), NULLIF(s.[C], ''), '')
        ELSE '' 
    END
    ";
    $csSQL = "
    SELECT 
        t.condCat,
        t.condMat,
        {$gradeCaseSql} AS grd
    FROM [PlanningSys].[master].condType AS t
    LEFT JOIN [PlanningSys].[master].condSize AS s
        ON s.size = ? AND s.type = t.condCat
    WHERE t.CondType = ?
    ";
    $params = [$grade, $grade, $grade, $grade, $grade, $rowdb['Sqmm'], $rowdb['CondType']];
    $csRes = sqlsrv_query($conn, $csSQL, $params);
    if ($r = sqlsrv_fetch_array($csRes, SQLSRV_FETCH_ASSOC)) {

        if (isset($r['condMat']) && $r['condMat'] == 'other') {
            $noOfStr = '-';
            $strDia  = '-';
        } else {
            $grd = isset($r['grd']) ? trim((string)$r['grd']) : '';
            if ($grd !== '') {
                $parts = array_map('trim', explode('/', $grd, 2));
                if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                    $noOfStr = $parts[0];
                    $strDia  = $parts[1];
                }
            }
        }
    }



          $sql = "UPDATE [PlanningSys].[instru].[data] set [NoOfStr] = '$noOfStr',[StrDia] = '$strDia'
           where id = '$id'";
          $run = sqlsrv_query($conn, $sql);
          if ($run === false) {
            echo $id;
            echo json_encode(sqlsrv_errors());
            exit;

          }

          echo $rowdb['JobNo'].'<br>';
          // echo $noOfStr.'/'.$strDia;

     }     

 ?>