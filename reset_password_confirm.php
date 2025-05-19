<?php
session_start();
require_once("db.php");

if (!isset($_GET['token'])) {
    header("Location: index.php");
    exit();
}

$token = $_GET['token'];

// Verify token
$stmt = $mysqli->prepare("SELECT email FROM password_resets WHERE token = ? AND expiry > NOW() AND used = 0");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Invalid or expired reset token.";
    header("Location: reset_password.php");
    exit();
}

$email = $result->fetch_assoc()['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
    } else {
        // Update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE accounts SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            // Mark token as used
            $stmt = $mysqli->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            
            $_SESSION['success'] = "Password has been reset successfully. Please login with your new password.";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to reset password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>Reset Password - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Set New Password</h2>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error'];
                                unset($_SESSION['error']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 