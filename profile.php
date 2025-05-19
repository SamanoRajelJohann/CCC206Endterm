<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error'] = "Please login to view your profile";
    header("Location: index.php");
    exit();
}

$account_id = $_SESSION['id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($username) || empty($email)) {
        $_SESSION['error'] = "Username and email are required";
    } else {
        // Check if email is already taken by another user
        $stmt = $mysqli->prepare("SELECT account_id FROM accounts WHERE email = ? AND account_id != ?");
        $stmt->bind_param("si", $email, $account_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error'] = "Email is already taken";
        } else {
            // Start transaction
            $mysqli->begin_transaction();
            
            try {
                // Update basic info
                $stmt = $mysqli->prepare("UPDATE accounts SET username = ?, email = ? WHERE account_id = ?");
                $stmt->bind_param("ssi", $username, $email, $account_id);
                $stmt->execute();
                $stmt->close();

                // Update password if provided
                if (!empty($current_password)) {
                    // Verify current password
                    $stmt = $mysqli->prepare("SELECT password FROM accounts WHERE account_id = ?");
                    $stmt->bind_param("i", $account_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();

                    if ($current_password === $user['password']) {
                        if ($new_password === $confirm_password) {
                            $stmt = $mysqli->prepare("UPDATE accounts SET password = ? WHERE account_id = ?");
                            $stmt->bind_param("si", $new_password, $account_id);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            throw new Exception("New passwords do not match");
                        }
                    } else {
                        throw new Exception("Current password is incorrect");
                    }
                }

                // Update user profile
                $address = trim($_POST['address']);
                $phone = trim($_POST['phone']);

                $stmt = $mysqli->prepare("INSERT INTO user_profiles (account_id, address, phone) 
                                        VALUES (?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE 
                                        address = VALUES(address), 
                                        phone = VALUES(phone)");
                $stmt->bind_param("iss", $account_id, $address, $phone);
                $stmt->execute();
                $stmt->close();

                $mysqli->commit();
                $_SESSION['success'] = "Profile updated successfully";
                $_SESSION['name'] = $username; // Update session name
                
            } catch (Exception $e) {
                $mysqli->rollback();
                $_SESSION['error'] = $e->getMessage();
            }
        }
    }
    header("Location: profile.php");
    exit();
}

// Fetch user data
$query = "SELECT a.*, up.address, up.phone 
          FROM accounts a 
          LEFT JOIN user_profiles up ON a.account_id = up.account_id 
          WHERE a.account_id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch order history
$query = "SELECT o.*, 
          COUNT(oi.order_item_id) as total_items,
          SUM(oi.quantity) as total_quantity
          FROM orders o
          LEFT JOIN order_items oi ON o.order_id = oi.order_id
          WHERE o.account_id = ?
          GROUP BY o.order_id
          ORDER BY o.created_at DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>My Profile - Rhine Lab</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="img&css/style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .profile-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
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
        .btn-primary:hover {
            background-color: #45a049;
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
        .section-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4CAF50;
        }
        .password-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .order-history {
            margin-top: 30px;
        }
        .order-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .order-info {
            font-size: 0.9em;
            color: #666;
        }
        .order-info strong {
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        .order-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        .view-order-btn {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
        }
        .view-order-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Profile</h1>
            <div class="nav-links">
                <a href="products.php"><i class="fas fa-shopping-bag"></i> Shop</a>
                <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="profile-section">
            <h2 class="section-title">Profile Information</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>

                <div class="password-section">
                    <h3>Change Password</h3>
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                    </div>
                </div>

                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
        </div>

        <div class="order-history">
            <h2 class="section-title">Order History</h2>
            <?php if (empty($orders)): ?>
                <p>No orders found.</p>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <h3>Order #<?php echo $order['order_id']; ?></h3>
                                <small>Placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></small>
                            </div>
                            <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                        <div class="order-details">
                            <div class="order-info">
                                <strong>Total Items</strong>
                                <?php echo $order['total_items']; ?> items (<?php echo $order['total_quantity']; ?> units)
                            </div>
                            <div class="order-info">
                                <strong>Total Amount</strong>
                                ₱<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                            <div class="order-info">
                                <strong>Shipping Address</strong>
                                <?php echo htmlspecialchars($order['shipping_address']); ?>
                            </div>
                            <div class="order-info">
                                <strong>Phone</strong>
                                <?php echo htmlspecialchars($order['phone']); ?>
                            </div>
                        </div>
                        <div style="margin-top: 15px; text-align: right;">
                            <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="view-order-btn">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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