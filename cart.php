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
    <title>Shopping Cart - Rhine Lab</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="img&css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .cart-table th, .cart-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .cart-table th {
            background-color: #f5f5f5;
        }
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        .quantity-input {
            width: 60px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-danger {
            background-color: #f44336;
            color: white;
        }
        .btn-primary:hover {
            background-color: #45a049;
        }
        .btn-danger:hover {
            background-color: #da190b;
        }
        .cart-summary {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .cart-summary h3 {
            margin-top: 0;
        }
        .nav-links {
            display: flex;
            gap: 20px;
        }
        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: bold;
        }
        .nav-links a:hover {
            color: #4CAF50;
        }
        .empty-cart {
            text-align: center;
            padding: 40px;
            background-color: #f5f5f5;
            border-radius: 8px;
        }
        .expiry-warning {
            color: #f44336;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Shopping Cart</h1>
            <div class="nav-links">
                <a href="products.php"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <?php if ($result->num_rows > 0): ?>
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
                                <img src="<?php echo !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'img&css/placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="product-image">
                                <div><?php echo htmlspecialchars($item['name']); ?></div>
                                <?php if ($days_until_expiry <= 30): ?>
                                    <div class="expiry-warning">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Expires in <?php echo $days_until_expiry; ?> days
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                           min="1" max="<?php echo $item['stock']; ?>" class="quantity-input">
                                    <button type="submit" name="update_quantity" class="btn btn-primary">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>
                            </td>
                            <td>$<?php echo number_format($subtotal, 2); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                    <button type="submit" name="remove_item" class="btn btn-danger">
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
                <p>Total Items: <?php echo $result->num_rows; ?></p>
                <p>Total Amount: $<?php echo number_format($total, 2); ?></p>
                <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart fa-3x" style="color: #ddd; margin-bottom: 20px;"></i>
                <h2>Your cart is empty</h2>
                <p>Add some products to your cart to continue shopping.</p>
                <a href="products.php" class="btn btn-primary">Browse Products</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            alert("<?php echo $_SESSION['success']; ?>");
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            alert("<?php echo $_SESSION['error']; ?>");
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
</body>
</html> 