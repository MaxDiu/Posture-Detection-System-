<?php
// login.php
session_start();
include 'db_connect.php';

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // Query to check if the caregiver exists
    $sql = "SELECT caregiverID, name, password FROM Caregiver WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // In a real app, use password_verify(). For now, simple matching:
        if ($password == $row['password']) { 
            $_SESSION['caregiverID'] = $row['caregiverID'];
            $_SESSION['caregiverName'] = $row['name'];
            header("Location: dashboard.php"); // Redirect on success
            exit();
        } else {
            $error_message = "Invalid password.";
        }
    } else {
        $error_message = "No account found with that email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GoodLife Vision</title>
    <style>
        /* Modern Front-End Styling */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
        }
        .logo-area {
            text-align: center;
            margin-bottom: 20px;
        }

        .main-logo {
            width: 180px; 
            height: auto;
            margin-bottom: 10px;
        }

        .logo-area h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 24px;
        }
        .logo-area p {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
        h2 {
            color: #2c3e50;
            font-size: 20px;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-size: 14px;
        }
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #4a90e2;
            outline: none;
        }
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 13px;
        }
        .options-row a {
            color: #4a90e2;
            text-decoration: none;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4a90e2, #6b52ae);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .btn-login:hover {
            opacity: 0.9;
        }
        .footer-link {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
        }
        .footer-link a {
            color: #4a90e2;
            text-decoration: none;
            font-weight: bold;
        }
        .error {
            color: #e74c3c;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo-area">
        <img src="images/logo.jpg" alt="GoodLife Vision Logo" class="main-logo">
        <p>Smart Fall Detection for Independent Living</p>
    </div>
    
    <h2>Welcome Back</h2>

    <?php if($error_message != "") echo "<div class='error'>$error_message</div>"; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="caregiver@example.com" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>

        <div class="options-row">
            <label><input type="checkbox" name="remember"> Remember me</label>
            <a href="forgot_password.php">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-login">Login</button>
    </form>

    <div class="footer-link">
        Don't have an account? <a href="register.php">Register</a>
    </div>
</div>

</body>
</html>