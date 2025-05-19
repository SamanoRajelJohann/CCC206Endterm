<?php
require_once("db.php");

// Generate a new hash for admin123
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Update the admin password
$stmt = $mysqli->prepare("UPDATE accounts SET password = ? WHERE email = ?");
$email = "admin@rhinelab.com";
$stmt->bind_param("ss", $hash, $email);

if ($stmt->execute()) {
    echo "Admin password updated successfully\n";
    echo "New hash: " . $hash . "\n";
    
    // Verify the update
    $verify = password_verify($password, $hash);
    echo "Verification test: " . ($verify ? "Success" : "Failed") . "\n";
} else {
    echo "Failed to update admin password\n";
}

$stmt->close();
$mysqli->close();
?> 