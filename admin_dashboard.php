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

// Fetch recent orders
$query = "
    SELECT o.*, a.username, a.email,
           COUNT(oi.order_item_id) as total_items,
           SUM(oi.quantity) as total_quantity
    FROM orders o
    JOIN accounts a ON o.account_id = a.account_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT 5";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch low stock products
$query = "SELECT * FROM products WHERE stock < 10 ORDER BY stock ASC LIMIT 5";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$low_stock_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch total statistics
$query = "SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(o.total_amount) as total_revenue,
            COUNT(DISTINCT o.account_id) as total_customers
          FROM orders o";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>Admin Dashboard - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .admin-container h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0;
            text-align: left;
            letter-spacing: -0.5px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.8rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2.2rem;
            margin-bottom: 1.2rem;
            background: rgba(0,0,0,0.03);
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .dashboard-section {
            background: white;
            border-radius: 12px;
            padding: 1.8rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .nav-links {
            display: flex;
            gap: 0.5rem;
        }
        .nav-links .btn {
            padding: 0.6rem 1.2rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .nav-links .btn:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            padding: 0.4rem 0.8rem;
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-body {
            padding: 1.2rem;
        }
        .h2 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .h5 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1.2rem;
        }
        .badge {
            padding: 0.5rem 0.8rem;
            font-weight: 500;
            border-radius: 6px;
        }
        .btn-outline-primary {
            border-width: 2px;
            font-weight: 500;
        }
        .btn-outline-primary:hover {
            transform: translateY(-2px);
        }
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.2rem;
        }
        .text-muted {
            color: #6c757d !important;
        }
        .small {
            font-size: 0.875rem;
        }
        .mt-2 {
            margin-top: 1rem !important;
        }
        .mt-3 {
            margin-top: 1.5rem !important;
        }
        .mb-2 {
            margin-bottom: 1rem !important;
        }
        .mb-3 {
            margin-bottom: 1.5rem !important;
        }
        .mb-4 {
            margin-bottom: 2rem !important;
        }
        .me-2 {
            margin-right: 0.75rem !important;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">Admin Dashboard</h1>
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
        </div>

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
                                                <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
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

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Order Management</h5>
                        <p class="card-text">Manage customer orders and track their status.</p>
                        <a href="admin_orders.php" class="btn btn-primary me-2">View Orders</a>
                        <a href="admin_order_status.php" class="btn btn-outline-primary">Manage Status</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 