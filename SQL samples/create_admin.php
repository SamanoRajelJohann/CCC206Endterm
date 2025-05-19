<?php
require_once("db.php");

// Admin account details
$admin_email = "admin@rhinelab.com";
$admin_password = "admin123";
$admin_username = "admin";

// Check if admin account exists
$stmt = $mysqli->prepare("SELECT account_id FROM accounts WHERE email = ?");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Create admin account
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("INSERT INTO accounts (username, email, password, role) VALUES (?, ?, ?, 'Admin')");
    $stmt->bind_param("sss", $admin_username, $admin_email, $hashed_password);
    
    if ($stmt->execute()) {
        echo "Admin account created successfully!\n";
        echo "Email: " . $admin_email . "\n";
        echo "Password: " . $admin_password . "\n";
    } else {
        echo "Failed to create admin account.\n";
    }
} else {
    // Update admin password
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE accounts SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed_password, $admin_email);
    
    if ($stmt->execute()) {
        echo "Admin password updated successfully!\n";
        echo "Email: " . $admin_email . "\n";
        echo "Password: " . $admin_password . "\n";
    } else {
        echo "Failed to update admin password.\n";
    }
}

$stmt->close();
$mysqli->close();
?> 