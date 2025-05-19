<?php
session_start();
require_once("db.php");

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Handle user role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];
    
    $stmt = $mysqli->prepare("UPDATE accounts SET role = ? WHERE account_id = ?");
    $stmt->bind_param("si", $new_role, $user_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: admin_users.php");
    exit();
}

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = $_POST['user_id'];
    $new_status = $_POST['status'];
    
    $stmt = $mysqli->prepare("UPDATE accounts SET status = ? WHERE account_id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: admin_users.php");
    exit();
}

// Fetch all users with their order statistics
$query = "SELECT a.*, 
          COUNT(DISTINCT o.order_id) as total_orders,
          SUM(o.total_amount) as total_spent
          FROM accounts a
          LEFT JOIN orders o ON a.account_id = o.account_id
          GROUP BY a.account_id
          ORDER BY a.created_at DESC";
$result = $mysqli->query($query);
$users = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Rhine Lab</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="img&css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .users-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .users-table tr:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-inactive { background-color: #f8d7da; color: #721c24; }
        .role-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .role-admin { background-color: #cce5ff; color: #004085; }
        .role-user { background-color: #e2e3e5; color: #383d41; }
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
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.9em;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .filter-section {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .form-group {
            margin-bottom: 0;
        }
        .form-control {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>User Management</h1>
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="filter-section">
            <form class="filter-form" method="GET">
                <div class="form-group">
                    <select name="role" class="form-control">
                        <option value="">All Roles</option>
                        <option value="Admin" <?php echo isset($_GET['role']) && $_GET['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="User" <?php echo isset($_GET['role']) && $_GET['role'] === 'User' ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <table class="users-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Orders</th>
                    <th>Total Spent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>#<?php echo $user['account_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                                <?php echo $user['role']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($user['status']); ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($user['total_orders']); ?></td>
                        <td>₱<?php echo number_format($user['total_spent'] ?? 0, 2); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['account_id']; ?>">
                                <select name="role" class="form-control" style="display: inline-block; width: auto;">
                                    <option value="User" <?php echo $user['role'] === 'User' ? 'selected' : ''; ?>>User</option>
                                    <option value="Admin" <?php echo $user['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <button type="submit" name="update_role" class="btn btn-primary btn-sm">
                                    <i class="fas fa-user-tag"></i> Update Role
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['account_id']; ?>">
                                <select name="status" class="form-control" style="display: inline-block; width: auto;">
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                    <i class="fas fa-toggle-on"></i> Update Status
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 