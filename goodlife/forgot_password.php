<?php
// forgot_password.php
session_start();
include 'db_connect.php';

$message = "";
$msg_class = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);

    // Check if the email exists
    $sql = "SELECT caregiverID, name FROM Caregiver WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = $row['name'];
        
        // Generate a secure random token
        $token = bin2hex(random_bytes(50));
        
        // Set expiration time (1 hour from now)
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // Store token in database
        $update_sql = "UPDATE Caregiver SET reset_token = '$token', reset_expires = '$expires' WHERE email = '$email'";
        
        if ($conn->query($update_sql) === TRUE) {
            // Create the reset link (Make sure your folder is named 'goodlife')
            $reset_link = "http://localhost/goodlife/reset_password.php?token=" . $token;

            // Setup the email
            $subject = "GoodLife Vision - Password Reset Request";
            $body = "Hello $name,\n\nWe received a request to reset your password.\n\nPlease click the link below to create a new password:\n$reset_link\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nGoodLife Vision Team";
            $headers = "From: noreply@goodlifevision.com";

            // Send the email
            if (mail($email, $subject, $body, $headers)) {
                $message = "A password reset link has been sent to your email.";
                $msg_class = "success";
            } else {
                $message = "Database updated, but failed to send the email. Please check your XAMPP sendmail settings.";
                $msg_class = "error";
            }
        } else {
            $message = "Error generating reset link. Please try again.";
            $msg_class = "error";
        }
    } else {
        $message = "No account found with that email address.";
        $msg_class = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - GoodLife Vision</title>
    <style>
        /* Exact same CSS as before */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 20px; }
        .recovery-container { background-color: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        .logo-area { text-align: center; margin-bottom: 20px; }
        .main-logo { width: 180px; height: auto; margin-bottom: 10px; }
        h2 { color: #2c3e50; font-size: 20px; margin-bottom: 10px; text-align: center; }
        .subtitle { color: #7f8c8d; font-size: 14px; margin-bottom: 25px; text-align: center; line-height: 1.5; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #34495e; font-size: 14px; }
        .form-group input[type="email"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus { border-color: #4a90e2; outline: none; }
        .btn-recover { width: 100%; padding: 14px; background: linear-gradient(135deg, #4a90e2, #6b52ae); border: none; border-radius: 8px; color: white; font-size: 16px; font-weight: bold; cursor: pointer; transition: opacity 0.3s; }
        .btn-recover:hover { opacity: 0.9; }
        .footer-link { text-align: center; margin-top: 25px; font-size: 14px; }
        .footer-link a { color: #4a90e2; text-decoration: none; font-weight: bold; }
        .message { padding: 10px; border-radius: 5px; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .error { color: #e74c3c; background-color: #fadbd8; }
        .success { color: #27ae60; background-color: #d5f5e3; }
    </style>
</head>
<body>
<div class="recovery-container">
    <div class="logo-area">
        <img src="images/logo.jpg" alt="GoodLife Vision Logo" class="main-logo">
    </div>
    <h2>Password Recovery</h2>
    <p class="subtitle">Enter your registered email address. We will send you a secure link to reset your password.</p>
    <?php if($message != "") echo "<div class='message $msg_class'>$message</div>"; ?>
    <form action="forgot_password.php" method="POST">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required>
        </div>
        <button type="submit" class="btn-recover">Send Reset Link</button>
    </form>
    <div class="footer-link">Remembered your password? <a href="login.php">Back to Login</a></div>
</div>
</body>
</html>