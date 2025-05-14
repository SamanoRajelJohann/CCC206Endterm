<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error'] = "Please login to add items to cart";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $account_id = $_SESSION['id'];
    $quantity = 1; // Default quantity

    // Check if product exists and has stock
    $stmt = $mysqli->prepare("SELECT stock FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if (!$product || $product['stock'] <= 0) {
        $_SESSION['error'] = "Product is out of stock";
        header("Location: products.php");
        exit();
    }

    // Check if product is already in cart
    $stmt = $mysqli->prepare("SELECT cart_id, quantity FROM cart WHERE account_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $account_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_item = $result->fetch_assoc();
    $stmt->close();

    if ($cart_item) {
        // Update quantity if product is already in cart
        $new_quantity = $cart_item['quantity'] + $quantity;
        if ($new_quantity <= $product['stock']) {
            $stmt = $mysqli->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
            $stmt->bind_param("ii", $new_quantity, $cart_item['cart_id']);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = "Cart updated successfully";
        } else {
            $_SESSION['error'] = "Not enough stock available";
        }
    } else {
        // Add new item to cart
        $stmt = $mysqli->prepare("INSERT INTO cart (account_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $account_id, $product_id, $quantity);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Product added to cart";
    }
} else {
    $_SESSION['error'] = "Invalid request";
}

header("Location: products.php");
exit();
?> 