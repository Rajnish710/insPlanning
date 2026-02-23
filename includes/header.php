<?php
// include 'session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <!-- Bootstrap 5 CSS alert dissmiss-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <!-- Bootstrap 5 JS alert dissmiss-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
    <!-- External CSS/JS -->
    <link rel="stylesheet" href="../css/style.css" />
    <!-- <link rel="stylesheet" href="style.css" /> -->
    <script src="../js/script.js"></script>

    <title><?php echo $title ?></title>
    <!-- Template CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script> -->

    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- Data Table CDN -->
     <!-- jQuery UI -->
    <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

    <!----------------------- jQuery UI --------------------------->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
    <!-- <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script> -->
    <script src="https://cdn.datatables.net/buttons/1.6.5/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.print.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.5/css/buttons.dataTables.min.css" />

    <style type="text/css">
        
        #ui-id-1 {
                width:100px;
                height: 300px; 
                overflow: auto; 
        }
    </style>
</head>
<body id="body-pd" class="body-pd">
    <header class="header body-pd" id="header">
        <div class="header_toggle"> <i class='bx bx-menu' id="header-toggle"></i> </div>
        <div class="header_img"> <b><?php echo $_SESSION['username']; ?></b></div>
    </header>
    <div class="l-navbar show" id="nav-bar">
        <nav class="nav">
            <div> <a href="#" class="nav_logo"> <i class='bx bx-layer nav_logo-icon'></i> <span class="nav_logo-name">SEPL</span> </a>
                <div class="nav_list"> 
                    <a href="../Pages/dashboard.php" class="nav_link" id="dashboardPage"> <i class='bx bx-grid-alt nav_icon'></i> <span class="nav_name">Dashboard</span> </a> 

                    <a class="sub-btn2 nav_link">
                        <i class='bx bx-book-content nav_icon'></i>
                        <span class="nav_name">Conductor <i class='bx bxs-chevron-down' style="font-size: 1rem;"></i></span>
                    </a>
                    <div class="drop2 mb-2 ms-3">
                        <a class="nav_link mb-0" href="../Conductor/copperPlan.php" id="copperPlan"><span class="nav_name">1. &nbsp;&nbsp;Copper Plan</span></a>
                    </div>


                    <a class="sub-btn2 nav_link">
                        <i class='bx bx-book-content nav_icon'></i>
                        <span class="nav_name">Instrumentetion <i class='bx bxs-chevron-down' style="font-size: 1rem;"></i></span>
                    </a>
                    <div class="drop2 mb-2 ms-3">
                        <a class="nav_link mb-0" href="../Instrumentation/instru_finalPlan.php" id="instruFinalePlanPage"><span class="nav_name">1. &nbsp;&nbsp;Final Plan</span></a>
                        <a class="nav_link mb-0" href="../Instrumentation/Instru_ProdPending.php" id="instruProdPlanPage"><span class="nav_name">2. &nbsp;&nbsp;Prod Pending</span></a>
                    </div>

                    <a class="sub-btn2 nav_link">
                        <i class='bx bx-book-content nav_icon'></i>
                        <span class="nav_name">Control Cable <i class='bx bxs-chevron-down' style="font-size: 1rem;"></i></span>
                    </a>
                    <div class="mb-2 ms-3">
                        <a class="nav_link mb-0" href="../ControlCable/control_finalPlan.php" id="ControlCablePlanPage"><span class="nav_name">1. &nbsp;&nbsp; Cable Plan</span></a>
                        <a class="nav_link mb-0" href="../ControlCable/Control_ProdPending.php" id="ControlProdPlanPage"><span class="nav_name">2. &nbsp;&nbsp;Prod Pending</span></a>
                    </div>
                   
                    <!-- <a href="../Pages/rework.php" class="nav_link" id="reworkPage"> <i class='bx bx-user nav_icon'></i> <span class="nav_name">Rework</span> </a>  -->
                </div>
                </div>
                    <a href="../logout.php" class="nav_link"> <i class='bx bx-log-out nav_icon'></i> <span class="nav_name">SignOut</span> </a>
            </div> 
        </nav>
    </div> 
    <!--Container Main start-->
    <!-- <div class="height-100 bg-light"> -->
    <div class="height-100" style="padding-top:70px">