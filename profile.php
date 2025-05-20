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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .profile-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .profile-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .profile-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-control {
            padding: 0.8rem 1.2rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            outline: none;
        }
        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background: #0d6efd;
            color: white;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
        }
        .btn-primary:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(13, 110, 253, 0.3);
        }
        .section-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(13, 110, 253, 0.1);
        }
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        .order-info {
            font-size: 0.95rem;
            color: #6c757d;
        }
        .order-info strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-block;
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
        .status-completed { 
            background: #d4edda; 
            color: #155724;
            border: 1px solid rgba(21, 87, 36, 0.1);
        }
        .status-cancelled { 
            background: #f8d7da; 
            color: #721c24;
            border: 1px solid rgba(114, 28, 36, 0.1);
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="profile-container">
        <h1 class="h2 mb-4">My Profile</h1>

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

        <div class="profile-section">
            <h2 class="section-title">Personal Information</h2>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="password-section">
                    <h3 class="section-title">Change Password</h3>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" name="update_profile" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Profile
                </button>
            </form>
        </div>

        <div class="profile-section">
            <h2 class="section-title">Order History</h2>
            <?php if (empty($orders)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You haven't placed any orders yet.
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <strong>Order #<?php echo $order['order_id']; ?></strong>
                                <div class="text-muted small">
                                    <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </div>
                        <div class="order-details">
                            <div class="order-info">
                                <strong>Total Items</strong>
                                <?php echo $order['total_items']; ?>
                            </div>
                            <div class="order-info">
                                <strong>Total Quantity</strong>
                                <?php echo $order['total_quantity']; ?>
                            </div>
                            <div class="order-info">
                                <strong>Total Amount</strong>
                                ₱<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 