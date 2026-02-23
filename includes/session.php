<?php 
session_start();

if (!isset($_SESSION['username'])) {
        ?>
        <script>
        alert('Please login First !!');
        window.open('../index.php','_self');
        </script>
        <?php
}


 ?>