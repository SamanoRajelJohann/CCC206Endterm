<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error'] = "Please login to view your cart";
    header("Location: index.php");
    exit();
}

$account_id = $_SESSION['id'];

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $cart_id = intval($_POST['cart_id']);
    $new_quantity = intval($_POST['quantity']);
    
    // Get product stock
    $stmt = $mysqli->prepare("SELECT p.stock FROM products p JOIN cart c ON p.product_id = c.product_id WHERE c.cart_id = ?");
    $stmt->bind_param("i", $cart_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if ($new_quantity <= $product['stock']) {
        $stmt = $mysqli->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND account_id = ?");
        $stmt->bind_param("iii", $new_quantity, $cart_id, $account_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Cart updated successfully";
    } else {
        $_SESSION['error'] = "Not enough stock available";
    }
    header("Location: cart.php");
    exit();
}

// Handle item removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $cart_id = intval($_POST['cart_id']);
    $stmt = $mysqli->prepare("DELETE FROM cart WHERE cart_id = ? AND account_id = ?");
    $stmt->bind_param("ii", $cart_id, $account_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = "Item removed from cart";
    header("Location: cart.php");
    exit();
}

// Fetch cart items with product details
$query = "SELECT c.cart_id, c.quantity, p.*, cat.name as category_name 
          FROM cart c 
          JOIN products p ON c.product_id = p.product_id 
          JOIN categories cat ON p.category_id = cat.category_id 
          WHERE c.account_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total
$total = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>Shopping Cart - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            
        }
        .cart-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-top: 2.5rem;
        }
        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        .cart-section {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .cart-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .cart-table th {
            background: #f8f9fa;
            padding: 1.2rem 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }
        .cart-table td {
            padding: 1.2rem 1.5rem;
            color: #2c3e50;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            vertical-align: middle;
        }
        .cart-table tr:last-child td {
            border-bottom: none;
        }
        .cart-table tr:hover {
            background-color: rgba(13, 110, 253, 0.02);
        }
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .product-info {
            margin-left: 1rem;
        }
        .product-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .quantity-input {
            width: 70px;
            padding: 0.8rem 1.2rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-align: center;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .quantity-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            outline: none;
        }
        .cart-summary {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-top: 2rem;
        }
        .cart-summary h3 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(13, 110, 253, 0.1);
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: #6c757d;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        .expiry-warning {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .expiry-warning i {
            font-size: 1rem;
        }
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .alert-success {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        @media (max-width: 768px) {
            .cart-table {
                display: block;
                overflow-x: auto;
            }
            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
        .empty-cart {
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

    <div class="cart-container">
        <h1 class="h2 mb-4">My Shopping Cart</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if ($result->num_rows > 0): ?>
            <div class="cart-section">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $result->fetch_assoc()): 
                            $subtotal = $item['price'] * $item['quantity'];
                            $total += $subtotal;
                            
                            $expiry_date = new DateTime($item['expiry_date']);
                            $today = new DateTime();
                            $days_until_expiry = $today->diff($expiry_date)->days;
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'img&css/placeholder.jpg'; ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="product-image">
                                        <div class="product-info">
                                            <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <?php if ($days_until_expiry <= 30): ?>
                                                <div class="expiry-warning">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    Expires in <?php echo $days_until_expiry; ?> days
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                <td>
                                    <form method="POST" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                               min="1" max="<?php echo $item['stock']; ?>" class="quantity-input">
                                        <button type="submit" name="update_quantity" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>₱<?php echo number_format($subtotal, 2); ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                        <button type="submit" name="remove_item" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <div class="cart-summary">
                    <h3>Order Summary</h3>
                    <div class="summary-item">
                        <span>Total Items:</span>
                        <span><?php echo $result->num_rows; ?></span>
                    </div>
                    <div class="summary-total">
                        <span>Total Amount:</span>
                        <span>₱<?php echo number_format($total, 2); ?></span>
                    </div>
                    <div class="d-grid gap-2 mt-4">
                        <a href="checkout.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart"></i> Proceed to Checkout
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <h3>Your cart is empty</h3>
                <p>Add some products to your cart to continue shopping.</p>
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Browse Products
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
