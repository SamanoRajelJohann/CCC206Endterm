<?php
session_start();
require_once("db.php");

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    $tracking_number = $_POST['tracking_number'];
    
    $stmt = $mysqli->prepare("UPDATE orders SET status = ?, tracking_number = ? WHERE order_id = ?");
    $stmt->bind_param("ssi", $new_status, $tracking_number, $order_id);
    
    if ($stmt->execute()) {
        // Get order details for email notification
        $stmt = $mysqli->prepare("
            SELECT o.*, a.email, a.username 
            FROM orders o 
            JOIN accounts a ON o.account_id = a.account_id 
            WHERE o.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        // Send email notification
        $to = $order['email'];
        $subject = "Order Status Update - Order #" . $order_id;
        $message = "Dear " . $order['username'] . ",\n\n";
        $message .= "Your order #" . $order_id . " status has been updated to: " . $new_status . "\n";
        if (!empty($tracking_number)) {
            $message .= "Tracking Number: " . $tracking_number . "\n";
        }
        $message .= "\nThank you for shopping with us!\n";
        $message .= "Rhine Lab Team";
        
        mail($to, $subject, $message);
        
        header("Location: admin_order_status.php?message=Order status updated successfully");
        exit();
    } else {
        header("Location: admin_order_status.php?error=Failed to update order status");
        exit();
    }
}

// Get all orders with customer details
$query = "SELECT o.*, a.username, a.email, COUNT(oi.order_item_id) as total_items
          FROM orders o
          JOIN accounts a ON o.account_id = a.account_id
          LEFT JOIN order_items oi ON o.order_id = oi.order_id
          GROUP BY o.order_id
          ORDER BY o.created_at DESC";
$result = $mysqli->query($query);
$orders = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Management - Rhine Lab</title>
    <link rel="icon" type="image/png" href="img/Followers.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Order Status Management</h1>
            <a href="admin_dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Orders List -->
        <div class="row">
            <?php foreach ($orders as $order): ?>
                <div class="col-12">
                    <div class="order-card">
                        <div class="row">
                            <div class="col-md-3">
                                <h5 class="mb-2">Order #<?php echo $order['order_id']; ?></h5>
                                <p class="text-muted mb-1">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($order['username']); ?>
                                </p>
                                <p class="text-muted mb-1">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($order['email']); ?>
                                </p>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                </p>
                            </div>
                            <div class="col-md-3">
                                <h6 class="mb-2">Shipping Address</h6>
                                <p class="mb-1"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                                <p class="mb-0"><?php echo htmlspecialchars($order['shipping_city']); ?>, <?php echo htmlspecialchars($order['shipping_state']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <h6 class="mb-2">Order Details</h6>
                                <p class="mb-1">Total: ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                                <p class="mb-0">Items: <?php echo $order['total_items']; ?></p>
                            </div>
                            <div class="col-md-3">
                                <form method="POST" class="mb-2">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <div class="mb-2">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select" required>
                                            <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Tracking Number</label>
                                        <input type="text" name="tracking_number" class="form-control" 
                                               value="<?php echo htmlspecialchars($order['tracking_number']); ?>"
                                               placeholder="Enter tracking number">
                                    </div>
                                    <button type="submit" name="update_status" class="btn btn-primary w-100">
                                        Update Status
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 