<?php
// Start the session
session_start();

// Include database connection
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: profile.php");
    exit();
}

$order_id = $_GET['id'];

// Fetch order details
$stmt = $mysqli->prepare("
    SELECT o.*, 
           COUNT(oi.order_item_id) as total_items,
           SUM(oi.quantity) as total_quantity
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.order_id = ? AND o.account_id = ?
    GROUP BY o.order_id
");
$stmt->bind_param("ii", $order_id, $_SESSION['id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: profile.php");
    exit();
}

// Fetch order items
$stmt = $mysqli->prepare("
    SELECT oi.*, p.name, p.price, p.image_url, p.expiry_date
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate days until expiry for each item
foreach ($order_items as &$item) {
    if ($item['expiry_date']) {
        $expiry = new DateTime($item['expiry_date']);
        $today = new DateTime();
        $item['days_until_expiry'] = $today->diff($expiry)->days;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Rhineland Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-details {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .order-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .order-items {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        .item-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        .item-card:last-child {
            border-bottom: none;
        }
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 1rem;
        }
        .item-details {
            flex-grow: 1;
        }
        .expiry-warning {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .order-summary {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-processing {
            background: #cce5ff;
            color: #004085;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="order-details">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Order Details</h1>
            <a href="profile.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>
        </div>

        <div class="order-header">
            <div class="row">
                <div class="col-md-6">
                    <h2 class="h5 mb-3">Order #<?php echo $order['order_id']; ?></h2>
                    <p class="mb-1">
                        <strong>Order Date:</strong> 
                        <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Status:</strong>
                        <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                            <?php echo $order['status']; ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1">
                        <strong>Shipping Address:</strong><br>
                        <?php echo htmlspecialchars($order['shipping_address']); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="order-items">
            <h3 class="h5 mb-4">Order Items</h3>
            <?php foreach ($order_items as $item): ?>
                <div class="item-card">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                         class="item-image">
                    <div class="item-details">
                        <h4 class="h6 mb-1"><?php echo htmlspecialchars($item['name']); ?></h4>
                        <p class="mb-1">
                            Quantity: <?php echo $item['quantity']; ?> × 
                            ₱<?php echo number_format($item['price'], 2); ?>
                        </p>
                        <?php if (isset($item['days_until_expiry']) && $item['days_until_expiry'] <= 30): ?>
                            <div class="expiry-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Expires in <?php echo $item['days_until_expiry']; ?> days
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <strong>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="order-summary">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-2">
                        <strong>Total Items:</strong> <?php echo $order['total_items']; ?>
                    </p>
                    <p class="mb-2">
                        <strong>Total Quantity:</strong> <?php echo $order['total_quantity']; ?>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h4 class="h5 mb-3">Order Total</h4>
                    <p class="h4 mb-0">₱<?php echo number_format($order['total_amount'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 