<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error'] = "Please login to checkout";
    header("Location: index.php");
    exit();
}

$account_id = $_SESSION['id'];

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address = trim($_POST['shipping_address']);
    $phone = trim($_POST['phone']);
    
    if (empty($shipping_address) || empty($phone)) {
        $_SESSION['error'] = "Please fill in all required fields";
        header("Location: checkout.php");
        exit();
    }

    // Start transaction
    $mysqli->begin_transaction();

    try {
        // Get cart items
        $stmt = $mysqli->prepare("SELECT c.*, p.price, p.stock 
                                 FROM cart c 
                                 JOIN products p ON c.product_id = p.product_id 
                                 WHERE c.account_id = ?");
        $stmt->bind_param("i", $account_id);
        $stmt->execute();
        $cart_items = $stmt->get_result();
        $stmt->close();

        if ($cart_items->num_rows === 0) {
            throw new Exception("Your cart is empty");
        }

        // Calculate total
        $total = 0;
        while ($item = $cart_items->fetch_assoc()) {
            if ($item['quantity'] > $item['stock']) {
                throw new Exception("Not enough stock for " . $item['name']);
            }
            $total += $item['price'] * $item['quantity'];
        }

        // Create order
        $stmt = $mysqli->prepare("INSERT INTO orders (account_id, total_amount, shipping_address, phone, status) 
                                 VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("idss", $account_id, $total, $shipping_address, $phone);
        $stmt->execute();
        $order_id = $mysqli->insert_id;
        $stmt->close();

        // Add order items and update stock
        $cart_items->data_seek(0);
        while ($item = $cart_items->fetch_assoc()) {
            // Add order item
            $stmt = $mysqli->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                     VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt->execute();
            $stmt->close();

            // Update stock
            $new_stock = $item['stock'] - $item['quantity'];
            $stmt = $mysqli->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
            $stmt->bind_param("ii", $new_stock, $item['product_id']);
            $stmt->execute();
            $stmt->close();
        }

        // Clear cart
        $stmt = $mysqli->prepare("DELETE FROM cart WHERE account_id = ?");
        $stmt->bind_param("i", $account_id);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $mysqli->commit();
        $_SESSION['success'] = "Order placed successfully! Order ID: " . $order_id;
        header("Location: order_confirmation.php?id=" . $order_id);
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        $mysqli->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: checkout.php");
        exit();
    }
}

// Fetch cart items for display
$query = "SELECT c.cart_id, c.quantity, p.*, cat.name as category_name 
          FROM cart c 
          JOIN products p ON c.product_id = p.product_id 
          JOIN categories cat ON p.category_id = cat.category_id 
          WHERE c.account_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total
$total = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Rhine Lab</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="img&css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .checkout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        .checkout-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .order-summary {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
        }
        .order-items {
            margin-bottom: 20px;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            width: 100%;
            text-align: center;
            font-size: 1.1em;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background-color: #45a049;
        }
        .nav-links {
            display: flex;
            gap: 20px;
        }
        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: bold;
        }
        .nav-links a:hover {
            color: #4CAF50;
        }
        .expiry-warning {
            color: #f44336;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Checkout</h1>
            <div class="nav-links">
                <a href="cart.php"><i class="fas fa-arrow-left"></i> Back to Cart</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="checkout-grid">
                <div class="checkout-form">
                    <h2>Shipping Information</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="shipping_address">Shipping Address *</label>
                            <input type="text" id="shipping_address" name="shipping_address" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>
                        <button type="submit" name="place_order" class="btn btn-primary">Place Order</button>
                    </form>
                </div>

                <div class="order-summary">
                    <h2>Order Summary</h2>
                    <div class="order-items">
                        <?php while ($item = $result->fetch_assoc()): 
                            $subtotal = $item['price'] * $item['quantity'];
                            $total += $subtotal;
                            
                            $expiry_date = new DateTime($item['expiry_date']);
                            $today = new DateTime();
                            $days_until_expiry = $today->diff($expiry_date)->days;
                        ?>
                            <div class="order-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <div>Quantity: <?php echo $item['quantity']; ?></div>
                                    <?php if ($days_until_expiry <= 30): ?>
                                        <div class="expiry-warning">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            Expires in <?php echo $days_until_expiry; ?> days
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>$<?php echo number_format($subtotal, 2); ?></div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="order-total">
                        <h3>Total: $<?php echo number_format($total, 2); ?></h3>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart fa-3x" style="color: #ddd; margin-bottom: 20px;"></i>
                <h2>Your cart is empty</h2>
                <p>Add some products to your cart to continue shopping.</p>
                <a href="products.php" class="btn btn-primary">Browse Products</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            alert("<?php echo $_SESSION['success']; ?>");
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            alert("<?php echo $_SESSION['error']; ?>");
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
</body>
</html> 