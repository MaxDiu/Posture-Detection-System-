<?php
// reset_password.php
session_start();
include 'db_connect.php';

$message = "";
$msg_class = "";
$show_form = false;
$email = "";

// Check if token is present in the URL
if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    $current_time = date("Y-m-d H:i:s");

    // Verify token exists and hasn't expired
    $sql = "SELECT email FROM Caregiver WHERE reset_token = '$token' AND reset_expires > '$current_time'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $show_form = true;
        $row = $result->fetch_assoc();
        $email = $row['email']; // Store email to use when updating
    } else {
        $message = "This password reset link is invalid or has expired. Please request a new one.";
        $msg_class = "error";
    }
} else {
    // If no token is provided at all
    $message = "No reset token provided.";
    $msg_class = "error";
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_email = $conn->real_escape_string($_POST['email']);

    // --- INPUT VALIDATION ---
    // Validate Password (Must contain at least 1 letter, 1 number, 1 symbol, and be at least 8 chars long)
    $password_regex = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[\W_]).{8,}$/';

    if (!preg_match($password_regex, $new_password)) {
        $message = "Password must be at least 8 characters long, containing at least one letter, one number, and one symbol.";
        $msg_class = "error";
        $show_form = true;
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $msg_class = "error";
        $show_form = true;
    } else {
        // Validation passed. Update password and clear the token so the link cannot be reused.
        // NOTE: Again, consider using password_hash($new_password, PASSWORD_DEFAULT) here for security.
        $update_sql = "UPDATE Caregiver SET password = '$new_password', reset_token = NULL, reset_expires = NULL WHERE email = '$user_email'";
        
        if ($conn->query($update_sql) === TRUE) {
            $message = "Your password has been successfully reset! Redirecting to login...";
            $msg_class = "success";
            $show_form = false; // Hide the form after success
            header("refresh:3;url=login.php"); // Redirect to login after 3 seconds
        } else {
            $message = "Error updating password. Please try again.";
            $msg_class = "error";
            $show_form = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - GoodLife Vision</title>
    <style>
        /* Shared UI Styles */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 20px; }
        .recovery-container { background-color: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        .logo-area { text-align: center; margin-bottom: 20px; }
        .main-logo { width: 180px; height: auto; margin-bottom: 10px; }
        h2 { color: #2c3e50; font-size: 20px; margin-bottom: 10px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #34495e; font-size: 14px; }
        .form-group input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus { border-color: #4a90e2; outline: none; }
        .btn-recover { width: 100%; padding: 14px; background: linear-gradient(135deg, #4a90e2, #6b52ae); border: none; border-radius: 8px; color: white; font-size: 16px; font-weight: bold; cursor: pointer; transition: opacity 0.3s; margin-top: 10px;}
        .btn-recover:hover { opacity: 0.9; }
        .message { padding: 10px; border-radius: 5px; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .error { color: #e74c3c; background-color: #fadbd8; }
        .success { color: #27ae60; background-color: #d5f5e3; }
        .footer-link { text-align: center; margin-top: 25px; font-size: 14px; }
        .footer-link a { color: #4a90e2; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="recovery-container">
    <div class="logo-area">
        <img src="images/logo.jpg" alt="GoodLife Vision Logo" class="main-logo">
    </div>
    
    <h2>Create New Password</h2>

    <?php if($message != "") echo "<div class='message $msg_class'>$message</div>"; ?>

    <?php if($show_form): ?>
    <form action="reset_password.php?token=<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>" method="POST">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
        </div>

        <button type="submit" class="btn-recover">Save Password</button>
    </form>
    <?php endif; ?>

    <?php if(!$show_form && $msg_class == "error"): ?>
        <div class="footer-link">
            <a href="forgot_password.php">Request a new reset link</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>