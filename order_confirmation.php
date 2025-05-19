<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: profile.php");
    exit();
}

$order_id = $_GET['id'];
$account_id = $_SESSION['id'];

// Get order details
$query = "SELECT o.*, a.username, a.email 
          FROM orders o 
          JOIN accounts a ON o.account_id = a.account_id 
          WHERE o.order_id = ? AND o.account_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $order_id, $account_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: profile.php");
    exit();
}

// Get order items
$query = "SELECT oi.*, p.name, p.image_url 
          FROM order_items oi 
          JOIN products p ON oi.product_id = p.product_id 
          WHERE oi.order_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>Order Confirmation - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .order-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d1e7dd; color: #0f5132; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="confirmation-container">
        <div class="text-center mb-4">
            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
            <h1 class="h2 mt-3">Order Confirmed!</h1>
            <p class="text-muted">Thank you for your purchase</p>
        </div>

        <div class="order-card">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">Order Details</h5>
                    <p class="mb-1"><strong>Order Number:</strong> #<?php echo $order['order_id']; ?></p>
                    <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
                    <p class="mb-1"><strong>Status:</strong> 
                        <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                            <?php echo $order['status']; ?>
                        </span>
                    </p>
                    <p class="mb-1"><strong>Payment Method:</strong> <?php echo $order['payment_method']; ?></p>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-3">Shipping Information</h5>
                    <p class="mb-1"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                    <p class="mb-1"><?php echo htmlspecialchars($order['shipping_city']); ?>, <?php echo htmlspecialchars($order['shipping_state']); ?></p>
                    <p class="mb-1">Phone: <?php echo htmlspecialchars($order['phone']); ?></p>
                </div>
            </div>
        </div>

        <div class="order-card">
            <h5 class="mb-3">Order Items</h5>
            <?php foreach ($order_items as $item): ?>
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                         style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; margin-right: 1rem;">
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
                    <strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Shipping</span>
                    <strong>Free</strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-4">
                    <span>Total</span>
                    <strong class="h5 mb-0">₱<?php echo number_format($order['total_amount'], 2); ?></strong>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="products.php" class="btn btn-primary me-2">
                <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
            <a href="profile.php" class="btn btn-outline-primary">
                <i class="fas fa-user"></i> View Order History
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 