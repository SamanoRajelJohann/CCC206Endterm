<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit();
}

// Get user's recent orders
$query = "
    SELECT o.*, 
           COUNT(oi.order_item_id) as total_items,
           SUM(oi.quantity) as total_quantity
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.account_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT 5";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user's cart items
$query = "
    SELECT c.*, p.name, p.price, p.image_url
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.account_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate cart total
$cart_total = 0;
foreach ($cart_items as $item) {
    $cart_total += $item['price'] * $item['quantity'];
}

// Get user's profile information
$query = "
    SELECT a.*, up.address, up.phone
    FROM accounts a
    LEFT JOIN user_profiles up ON a.account_id = up.account_id
    WHERE a.account_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .dashboard-section {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .dashboard-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            height: 100%;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1.2rem;
            transition: all 0.3s ease;
        }
        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        .stat-label {
            color: #6c757d;
            font-size: 1rem;
            font-weight: 500;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .status-pending { 
            background: #fff3cd; 
            color: #856404;
            border: 1px solid rgba(133, 100, 4, 0.1);
        }
        .status-processing { 
            background: #cce5ff; 
            color: #004085;
            border: 1px solid rgba(0, 64, 133, 0.1);
        }
        .status-shipped { 
            background: #d4edda; 
            color: #155724;
            border: 1px solid rgba(21, 87, 36, 0.1);
        }
        .status-delivered { 
            background: #d1e7dd; 
            color: #0f5132;
            border: 1px solid rgba(15, 81, 50, 0.1);
        }
        .status-cancelled { 
            background: #f8d7da; 
            color: #721c24;
            border: 1px solid rgba(114, 28, 36, 0.1);
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .card-body {
            padding: 1.2rem;
        }
        .btn-outline-primary {
            border-width: 2px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-outline-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
        }
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        .h2 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        .h5 {
            color: #2c3e50;
            font-weight: 600;
        }
        .text-muted {
            color: #6c757d !important;
        }
        img {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        strong {
            color: #2c3e50;
            font-weight: 600;
        }
        .small {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <h1 class="h2 mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>

        <!-- Quick Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-value"><?php echo count($recent_orders); ?></div>
                    <div class="stat-label">Recent Orders</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value"><?php echo count($cart_items); ?></div>
                    <div class="stat-label">Cart Items</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stat-value"><?php echo $user['address'] ? 'Complete' : 'Incomplete'; ?></div>
                    <div class="stat-label">Profile Status</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-md-6">
                <div class="dashboard-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5 mb-0">Recent Orders</h3>
                        <a href="orders.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <?php if (empty($recent_orders)): ?>
                        <div class="alert alert-info">No orders found.</div>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Order #<?php echo $order['order_id']; ?></strong>
                                            <div class="text-muted small">
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </div>
                                        </div>
                                        <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <small>
                                            Items: <?php echo $order['total_items']; ?> 
                                            (<?php echo $order['total_quantity']; ?> total)<br>
                                            Total: ₱<?php echo number_format($order['total_amount'], 2); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cart Summary -->
            <div class="col-md-6">
                <div class="dashboard-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5 mb-0">Cart Summary</h3>
                        <a href="cart.php" class="btn btn-outline-primary btn-sm">View Cart</a>
                    </div>
                    <?php if (empty($cart_items)): ?>
                        <div class="alert alert-info">Your cart is empty.</div>
                    <?php else: ?>
                        <?php foreach ($cart_items as $item): ?>
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             class="me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                            <div class="text-muted small">
                                                Quantity: <?php echo $item['quantity']; ?><br>
                                                Price: ₱<?php echo number_format($item['price'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="mt-3 text-end">
                            <strong>Total: ₱<?php echo number_format($cart_total, 2); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Profile Status -->
                <div class="dashboard-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5 mb-0">Profile Status</h3>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Email:</strong>
                                <div><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div class="mb-2">
                                <strong>Address:</strong>
                                <div><?php echo $user['address'] ? htmlspecialchars($user['address']) : 'Not set'; ?></div>
                            </div>
                            <div>
                                <strong>Phone:</strong>
                                <div><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : 'Not set'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 