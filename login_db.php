<?php
session_start(); 
include('dbcon.php');
if (isset($_POST['save'])) {
    $employee_id = $_POST['employee_id'];
    $password = $_POST['password'];

    $sql="SELECT * FROM [user] WHERE employee_id='$employee_id' AND password='$password'";
    $run = sqlsrv_query($conn,$sql);
    $row = sqlsrv_fetch_array($run, SQLSRV_FETCH_ASSOC);
    $params = array();
    $options = array("Scrollable" => SQLSRV_CURSOR_KEYSET);
    $result=sqlsrv_query($conn,$sql,$params,$options);
    $count=sqlsrv_num_rows($result);
        if($count<1)
        {
            ?>
                <script>
                    alert('Employee Id and Password Not Match... Try Again!');
                    window.open('index.php','_self');
                </script>
            <?php
        }
        else
        {
            $_SESSION['username'] = $row['user_name'];
                
            ?>
                <script>
                    window.open('Pages/dashboard.php','_self');
                </script>
            <?php
        }
    }
?>