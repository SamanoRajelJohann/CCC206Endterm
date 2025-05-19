<?php
session_start();
require_once("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // Check if email exists
    $stmt = $mysqli->prepare("SELECT account_id FROM accounts WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token in database
        $stmt = $mysqli->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expiry);
        $stmt->execute();
        
        // Send reset email
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password_confirm.php?token=" . $token;
        $to = $email;
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $reset_link;
        $headers = "From: noreply@rhinelab.com";
        
        mail($to, $subject, $message, $headers);
        
        $_SESSION['success'] = "Password reset instructions have been sent to your email.";
    } else {
        $_SESSION['error'] = "Email not found.";
    }
    
    header("Location: reset_password.php");
    exit();
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
                        <h2 class="text-center mb-4">Reset Password</h2>
                        
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo $_SESSION['success'];
                                unset($_SESSION['success']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
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
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="index.php">Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 