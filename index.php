<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Production Planning</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.0/dist/boxicons.js" integrity="sha512-Dm5UxqUSgNd93XG7eseoOrScyM1BVs65GrwmavP0D0DujOA8mjiBfyj71wmI2VQZKnnZQsSWWsxDKNiQIqk8sQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main>
        <header>
            <!-- <h4>SEPL</h4> -->
            <img src="img/sepl_2.png" width="100px" height="100px">
        </header>
    
        <form action="login_db.php" method="POST">
            <div class="form_wrapper">
                <input type="text" id="employee_id" name="employee_id" required>
                <label for="employee_id">Employee Id</label>
                <i class='bx bxs-user'></i>
            </div>
            <div class="form_wrapper">
                <input type="password" id="pass" name="password" required>
                <label for="pass">Password</label>
                <i class='bx bxs-lock-alt'></i>
            </div>
            <div class="remember_box">
                <div class="remember">
                    <input type="checkbox">Remember me
                </div>
                <!-- <a href="#">Forgot Password ? </a> -->
            </div>
            <button type="submit" value="Sign In" name="save"><b>Sign In</b></button>
            <!-- <div class="new_account">Don't have account?<a href="#">Sign Up</a></div> -->
        </form>
    </main>
</body>
</html>