<?php
require_once("db.php");

// Check database connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
echo "Database connection successful\n";

// Check admin account
$email = "admin@rhinelab.com";
$stmt = $mysqli->prepare("SELECT account_id, username, email, password, role FROM accounts WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "Admin account found:\n";
    echo "ID: " . $admin['account_id'] . "\n";
    echo "Username: " . $admin['username'] . "\n";
    echo "Email: " . $admin['email'] . "\n";
    echo "Role: " . $admin['role'] . "\n";
    echo "Password hash: " . $admin['password'] . "\n";
    
    // Test password verification
    $test_password = "admin123";
    $verify = password_verify($test_password, $admin['password']);
    echo "Password verification test: " . ($verify ? "Success" : "Failed") . "\n";
} else {
    echo "Admin account not found\n";
}

$stmt->close();
$mysqli->close();
?> 