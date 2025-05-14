<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error'] = "Please login to view order details";
    header("Location: index.php");
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Invalid order ID";
    header("Location: products.php");
    exit();
}

$order_id = intval($_GET['id']);
$account_id = $_SESSION['id'];

// Fetch order details
$query = "SELECT o.*, oi.quantity, oi.price, p.name as product_name, p.image_url, cat.name as category_name
          FROM orders o
          JOIN order_items oi ON o.order_id = oi.order_id
          JOIN products p ON oi.product_id = p.product_id
          JOIN categories cat ON p.category_id = cat.category_id
          WHERE o.order_id = ? AND o.account_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $order_id, $account_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Order not found";
    header("Location: products.php");
    exit();
}

// Get order header info
$order = $result->fetch_assoc();
$result->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Rhine Lab</title>
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
        .confirmation-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .order-details {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        .order-items {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
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
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-pending {
            background-color: #ffd700;
            color: #000;
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
            <h1>Order Confirmation</h1>
            <div class="nav-links">
                <a href="products.php"><i class="fas fa-shopping-bag"></i> Continue Shopping</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="confirmation-box">
            <h2>Thank you for your order!</h2>
            <p>Your order has been placed successfully. Here are your order details:</p>
            
            <div class="order-info">
                <p><strong>Order ID:</strong> #<?php echo $order_id; ?></p>
                <p><strong>Order Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                <p><strong>Status:</strong> 
                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                        <?php echo $order['status']; ?>
                    </span>
                </p>
                <p><strong>Shipping Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
            </div>
        </div>

        <div class="order-details">
            <div class="order-items">
                <h3>Order Items</h3>
                <?php while ($item = $result->fetch_assoc()): 
                    $subtotal = $item['price'] * $item['quantity'];
                    
                    $expiry_date = new DateTime($item['expiry_date']);
                    $today = new DateTime();
                    $days_until_expiry = $today->diff($expiry_date)->days;
                ?>
                    <div class="order-item">
                        <div style="display: flex; align-items: center;">
                            <img src="<?php echo !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'img&css/placeholder.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                 class="product-image">
                            <div>
                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                <div><?php echo htmlspecialchars($item['category_name']); ?></div>
                                <div>Quantity: <?php echo $item['quantity']; ?></div>
                                <?php if ($days_until_expiry <= 30): ?>
                                    <div class="expiry-warning">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Expires in <?php echo $days_until_expiry; ?> days
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>$<?php echo number_format($subtotal, 2); ?></div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="order-summary">
                <h3>Order Summary</h3>
                <p><strong>Total Amount:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                <a href="products.php" class="btn btn-primary">Continue Shopping</a>
            </div>
        </div>
    </div>
</body>
</html> 