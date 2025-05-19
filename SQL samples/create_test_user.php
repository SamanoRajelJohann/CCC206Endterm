<?php
require_once("db.php");

// Test user details
$test_email = "test@example.com";
$test_password = "test123";
$test_username = "Test User";

// Check if test user exists
$stmt = $mysqli->prepare("SELECT account_id FROM accounts WHERE email = ?");
$stmt->bind_param("s", $test_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Create test user account
    $hashed_password = password_hash($test_password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("INSERT INTO accounts (username, email, password, role) VALUES (?, ?, ?, 'User')");
    $stmt->bind_param("sss", $test_username, $test_email, $hashed_password);
    
    if ($stmt->execute()) {
        echo "Test user account created successfully!\n";
        echo "Email: " . $test_email . "\n";
        echo "Password: " . $test_password . "\n";
    } else {
        echo "Failed to create test user account.\n";
    }
} else {
    // Update test user password
    $hashed_password = password_hash($test_password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE accounts SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashed_password, $test_email);
    
    if ($stmt->execute()) {
        echo "Test user password updated successfully!\n";
        echo "Email: " . $test_email . "\n";
        echo "Password: " . $test_password . "\n";
    } else {
        echo "Failed to update test user password.\n";
    }
}

$stmt->close();
$mysqli->close();
?> 