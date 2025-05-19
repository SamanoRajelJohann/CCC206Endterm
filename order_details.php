<?php
// Start the session
session_start();

// Include database connection
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error'] = "Please login to view order details";
    header("Location: index.php");
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit();
}

$order_id = intval($_GET['id']);
$account_id = $_SESSION['id'];

// Fetch order details
$query = "SELECT o.*, 
          COUNT(oi.order_item_id) as total_items,
          SUM(oi.quantity * oi.price) as total_amount
          FROM orders o
          JOIN order_items oi ON o.order_id = oi.order_id
          WHERE o.order_id = ? AND o.account_id = ?
          GROUP BY o.order_id";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $order_id, $account_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Fetch order items
$query = "SELECT oi.*, p.name as product_name, p.image_url
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
    <title>Order #<?php echo $order_id; ?> - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-details {
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
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="order-details">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="orders.php">My Orders</a></li>
                <li class="breadcrumb-item active">Order #<?php echo $order_id; ?></li>
            </ol>
        </nav>

        <div class="order-card">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h1 class="h3 mb-3">Order #<?php echo $order_id; ?></h1>
                    <p class="mb-1">
                        <strong>Order Date:</strong> 
                        <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Status:</strong>
                        <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1">
                        <strong>Total Items:</strong> <?php echo $order['total_items']; ?>
                    </p>
                    <p class="mb-1">
                        <strong>Total Amount:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?>
                    </p>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="mb-3">Shipping Information</h5>
                    <p class="mb-1">
                        <strong>Address:</strong><br>
                        <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                    </p>
                    <?php if (isset($order['contact_number']) && !empty($order['contact_number'])): ?>
                        <p class="mb-1">
                            <strong>Contact Number:</strong><br>
                            <?php echo htmlspecialchars($order['contact_number']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-3">Payment Information</h5>
                    <p class="mb-1">
                        <strong>Payment Method:</strong><br>
                        <?php echo htmlspecialchars($order['payment_method']); ?>
                    </p>
                    <?php if (isset($order['payment_status']) && $order['payment_status']): ?>
                        <p class="mb-1">
                            <strong>Payment Status:</strong><br>
                            <span class="text-success">Paid</span>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <h5 class="mb-3">Order Items</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                             class="product-image me-3">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                        </div>
                                    </div>
                                </td>
                                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td class="text-end">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                            <td class="text-end"><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 