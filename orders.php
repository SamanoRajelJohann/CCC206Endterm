<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error'] = "Please login to view your orders";
    header("Location: index.php");
    exit();
}

$account_id = $_SESSION['id'];

// Fetch orders with order items
$query = "SELECT o.*, 
          COUNT(oi.order_item_id) as total_items,
          SUM(oi.quantity * oi.price) as total_amount
          FROM orders o
          JOIN order_items oi ON o.order_id = oi.order_id
          WHERE o.account_id = ?
          GROUP BY o.order_id
          ORDER BY o.created_at DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>My Orders - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .orders-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .order-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-processing {
            background: #cce5ff;
            color: #004085;
        }
        .status-shipped {
            background: #d4edda;
            color: #155724;
        }
        .status-delivered {
            background: #d1e7dd;
            color: #0f5132;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .empty-orders {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="orders-container">
        <h1 class="h2 mb-4">My Orders</h1>

        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                <h3>No Orders Yet</h3>
                <p class="text-muted">Start shopping to see your orders here.</p>
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Browse Products
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <h5 class="mb-1">Order #<?php echo $order['order_id']; ?></h5>
                            <small class="text-muted">
                                <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                            </small>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-1">
                                <strong>Items:</strong> <?php echo $order['total_items']; ?>
                            </div>
                            <div>
                                <strong>Total:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-1">
                                <strong>Shipping Address:</strong><br>
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                            <div class="mt-2">
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline-primary btn-sm">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 