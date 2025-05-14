<?php
session_start();
require_once("db.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Get total products
$stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM products");
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];

// Get total orders
$stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM orders");
$stmt->execute();
$total_orders = $stmt->get_result()->fetch_assoc()['total'];

// Get total revenue
$stmt = $mysqli->prepare("SELECT SUM(total_amount) as total FROM orders WHERE status != 'Cancelled'");
$stmt->execute();
$total_revenue = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Get total users
$stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM accounts WHERE role = 'User'");
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['total'];

// Get recent orders
$stmt = $mysqli->prepare("
    SELECT o.*, a.username, 
           COUNT(oi.order_item_id) as total_items,
           SUM(oi.quantity) as total_quantity
    FROM orders o 
    JOIN accounts a ON o.account_id = a.account_id
    JOIN order_items oi ON o.order_id = oi.order_id
    GROUP BY o.order_id 
    ORDER BY o.order_date DESC 
    LIMIT 5
");
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get low stock products
$stmt = $mysqli->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.category_id 
    WHERE p.stock <= 10 
    ORDER BY p.stock ASC 
    LIMIT 5
");
$stmt->execute();
$low_stock_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get products nearing expiry
$stmt = $mysqli->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.category_id 
    WHERE p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY p.expiry_date ASC 
    LIMIT 5
");
$stmt->execute();
$expiring_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 100%;
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .dashboard-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .nav-links {
            margin-bottom: 1rem;
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d4edda; color: #155724; }
        .status-delivered { background: #d1e7dd; color: #0f5132; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="nav-links">
            <a href="admin_products.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-box"></i> Products
            </a>
            <a href="admin_orders.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-shopping-cart"></i> Orders
            </a>
            <a href="admin_categories.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-tags"></i> Categories
            </a>
            <a href="logout.php" class="btn btn-outline-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <h1 class="h2 mb-4">Admin Dashboard</h1>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_products); ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_orders); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">₱<?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-md-6">
                <div class="dashboard-section">
                    <h3 class="h5 mb-3">Recent Orders</h3>
                    <?php if (empty($recent_orders)): ?>
                        <div class="alert alert-info">No recent orders found.</div>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Order #<?php echo $order['order_id']; ?></strong>
                                            <div class="text-muted small">
                                                <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?>
                                            </div>
                                        </div>
                                        <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <small>
                                            Customer: <?php echo htmlspecialchars($order['username']); ?><br>
                                            Items: <?php echo $order['total_items']; ?> 
                                            (<?php echo $order['total_quantity']; ?> total)<br>
                                            Total: ₱<?php echo number_format($order['total_amount'], 2); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-end mt-3">
                            <a href="admin_orders.php" class="btn btn-outline-primary btn-sm">
                                View All Orders
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock & Expiring Products -->
            <div class="col-md-6">
                <div class="dashboard-section">
                    <h3 class="h5 mb-3">Low Stock Products</h3>
                    <?php if (empty($low_stock_products)): ?>
                        <div class="alert alert-success">No low stock products.</div>
                    <?php else: ?>
                        <?php foreach ($low_stock_products as $product): ?>
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-warning">
                                            <?php echo $product['stock']; ?> left
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-end mt-3">
                            <a href="admin_products.php" class="btn btn-outline-primary btn-sm">
                                View All Products
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-section">
                    <h3 class="h5 mb-3">Products Nearing Expiry</h3>
                    <?php if (empty($expiring_products)): ?>
                        <div class="alert alert-success">No products nearing expiry.</div>
                    <?php else: ?>
                        <?php foreach ($expiring_products as $product): 
                            $expiry = new DateTime($product['expiry_date']);
                            $today = new DateTime();
                            $days_until_expiry = $today->diff($expiry)->days;
                        ?>
                            <div class="card mb-2">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($product['category_name']); ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-danger">
                                            Expires in <?php echo $days_until_expiry; ?> days
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-end mt-3">
                            <a href="admin_products.php" class="btn btn-outline-primary btn-sm">
                                View All Products
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 