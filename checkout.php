<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error'] = "Please login to proceed with checkout";
    header("Location: index.php");
    exit();
}

$account_id = $_SESSION['id'];

// Get cart items
$query = "SELECT c.*, p.name, p.price, p.stock, p.image_url 
          FROM cart c 
          JOIN products p ON c.product_id = p.product_id 
          WHERE c.account_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address = trim($_POST['shipping_address']);
    $shipping_city = trim($_POST['shipping_city']);
    $shipping_state = trim($_POST['shipping_state']);
    $phone = trim($_POST['phone']);
    $payment_method = $_POST['payment_method'];
    
    // Validate input
    if (empty($shipping_address) || empty($shipping_city) || empty($shipping_state) || empty($phone)) {
        $_SESSION['error'] = "Please fill in all required fields";
    } else {
        // Start transaction
        $mysqli->begin_transaction();
        
        try {
            // Create order
            $stmt = $mysqli->prepare("
                INSERT INTO orders (account_id, total_amount, shipping_address, shipping_city, shipping_state, phone, payment_method, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->bind_param("idsssss", $account_id, $total, $shipping_address, $shipping_city, $shipping_state, $phone, $payment_method);
            $stmt->execute();
            $order_id = $mysqli->insert_id;
            
            // Add order items
            $stmt = $mysqli->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($cart_items as $item) {
                // Check stock
                if ($item['stock'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for " . $item['name']);
                }
                
                // Add to order items
                $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                $stmt->execute();
                
                // Update stock
                $new_stock = $item['stock'] - $item['quantity'];
                $update_stock = $mysqli->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
                $update_stock->bind_param("ii", $new_stock, $item['product_id']);
                $update_stock->execute();
            }
            
            // Clear cart
            $stmt = $mysqli->prepare("DELETE FROM cart WHERE account_id = ?");
            $stmt->bind_param("i", $account_id);
            $stmt->execute();
            
            $mysqli->commit();
            
            // Send order confirmation email
            $to = $_SESSION['email'];
            $subject = "Order Confirmation - Order #" . $order_id;
            $message = "Dear " . $_SESSION['name'] . ",\n\n";
            $message .= "Thank you for your order! Your order number is: " . $order_id . "\n\n";
            $message .= "Order Details:\n";
            foreach ($cart_items as $item) {
                $message .= "- " . $item['name'] . " x " . $item['quantity'] . " = ₱" . ($item['price'] * $item['quantity']) . "\n";
            }
            $message .= "\nTotal: ₱" . $total . "\n\n";
            $message .= "Shipping Address:\n";
            $message .= $shipping_address . "\n";
            $message .= $shipping_city . ", " . $shipping_state . "\n\n";
            $message .= "We will notify you when your order ships.\n\n";
            $message .= "Thank you for shopping with us!\n";
            $message .= "Rhine Lab Team";
            
            mail($to, $subject, $message);
            
            $_SESSION['success'] = "Order placed successfully! Order #" . $order_id;
            header("Location: order_confirmation.php?id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
    }
}

// Get user profile
$stmt = $mysqli->prepare("SELECT * FROM user_profiles WHERE account_id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>Checkout - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .order-summary {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="checkout-container">
        <h1 class="h2 mb-4">Checkout</h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Order Form -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Shipping Information</h5>
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Shipping Address</label>
                                    <input type="text" name="shipping_address" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" name="shipping_city" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">State/Province</label>
                                    <input type="text" name="shipping_state" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile['state'] ?? ''); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method" class="form-select" required>
                                        <option value="Cash on Delivery">Cash on Delivery</option>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="GCash">GCash</option>
                                    </select>
                                </div>
                            </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-md-4">
                <div class="order-summary">
                    <h5 class="mb-4">Order Summary</h5>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                <p class="text-muted mb-0">
                                    <?php echo $item['quantity']; ?> x ₱<?php echo number_format($item['price'], 2); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <strong>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <strong>₱<?php echo number_format($total, 2); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping</span>
                            <strong>Free</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <span>Total</span>
                            <strong class="h5 mb-0">₱<?php echo number_format($total, 2); ?></strong>
                        </div>
                        <button type="submit" name="place_order" class="btn btn-primary w-100">
                            Place Order
                        </button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 