<?php
session_start();
require_once("db.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    $stmt = $mysqli->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $_POST['status'], $_POST['order_id']);
    $stmt->execute();
    
    // Redirect to prevent form resubmission
    header("Location: admin_orders.php");
    exit();
}

// Build the order query
$query = "SELECT o.*, a.username, 
          COUNT(oi.order_item_id) as total_items,
          SUM(oi.quantity) as total_quantity
          FROM orders o 
          JOIN accounts a ON o.account_id = a.account_id
          JOIN order_items oi ON o.order_id = oi.order_id
          WHERE 1=1";
$params = [];
$types = "";

// Status filter
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $query .= " AND o.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// Date range filter
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $query .= " AND o.order_date >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $query .= " AND o.order_date <= ?";
    $params[] = $_GET['end_date'] . ' 23:59:59';
    $types .= "s";
}

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $query .= " AND (a.username LIKE ? OR o.order_id LIKE ? OR o.shipping_address LIKE ?)";
    $search = "%" . $_GET['search'] . "%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

$query .= " GROUP BY o.order_id ORDER BY o.order_date DESC";

// Prepare and execute the query
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .order-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .order-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        .order-body {
            padding: 1rem;
        }
        .status-badge {
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
        .nav-links {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="nav-links">
            <a href="admin_dashboard.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="admin_products.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-box"></i> Products
            </a>
            <a href="admin_categories.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-tags"></i> Categories
            </a>
            <a href="logout.php" class="btn btn-outline-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <h1 class="h2 mb-4">Order Management</h1>

        <div class="filters">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                           placeholder="Search orders...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Processing" <?php echo (isset($_GET['status']) && $_GET['status'] === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                        <option value="Shipped" <?php echo (isset($_GET['status']) && $_GET['status'] === 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                        <option value="Delivered" <?php echo (isset($_GET['status']) && $_GET['status'] === 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                        <option value="Cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                           value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                           value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>

        <?php if (empty($orders)): ?>
            <div class="alert alert-info">
                No orders found matching your criteria.
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <strong>Order #<?php echo $order['order_id']; ?></strong>
                                <div class="text-muted small">
                                    <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <strong>Customer:</strong> <?php echo htmlspecialchars($order['username']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Items:</strong> <?php echo $order['total_items']; ?> 
                                (<?php echo $order['total_quantity']; ?> total)
                            </div>
                            <div class="col-md-3 text-end">
                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo $order['status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="order-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Shipping Address:</strong><br>
                                <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Phone:</strong><br>
                                <?php echo htmlspecialchars($order['phone_number']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Total Amount:</strong><br>
                                ₱<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-9">
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                            <div class="col-md-3 text-end">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <select name="status" class="form-select form-select-sm d-inline-block w-auto" 
                                            onchange="this.form.submit()">
                                        <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </form>
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