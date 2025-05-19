<?php
session_start();
require_once("db.php");

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Get date range from request or default to current month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch sales statistics
$query = "SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(o.total_amount) as total_revenue,
            COUNT(DISTINCT o.account_id) as total_customers,
            AVG(o.total_amount) as average_order_value
          FROM orders o
          WHERE o.created_at BETWEEN ? AND ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch top selling products
$query = "SELECT p.name, p.price, SUM(oi.quantity) as total_sold, SUM(oi.quantity * p.price) as total_revenue
          FROM order_items oi
          JOIN products p ON oi.product_id = p.product_id
          JOIN orders o ON oi.order_id = o.order_id
          WHERE o.created_at BETWEEN ? AND ?
          GROUP BY p.product_id
          ORDER BY total_sold DESC
          LIMIT 5";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch daily sales for chart
$query = "SELECT DATE(created_at) as date, SUM(total_amount) as daily_sales
          FROM orders
          WHERE created_at BETWEEN ? AND ?
          GROUP BY DATE(created_at)
          ORDER BY date";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$daily_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics - Rhine Lab</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="img&css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 1.8em;
            font-weight: bold;
            color: #4CAF50;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .top-products {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .product-list {
            list-style: none;
            padding: 0;
        }
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .product-item:last-child {
            border-bottom: none;
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
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>Sales Analytics</h1>
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="filter-section">
            <form class="filter-form" method="GET">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="value"><?php echo number_format($stats['total_orders']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Customers</h3>
                <div class="value"><?php echo number_format($stats['total_customers']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Average Order Value</h3>
                <div class="value">₱<?php echo number_format($stats['average_order_value'], 2); ?></div>
            </div>
        </div>

        <div class="chart-container">
            <h2>Daily Sales</h2>
            <canvas id="salesChart"></canvas>
        </div>

        <div class="top-products">
            <h2>Top Selling Products</h2>
            <ul class="product-list">
                <?php foreach ($top_products as $product): ?>
                    <li class="product-item">
                        <div>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                            <div>Units Sold: <?php echo number_format($product['total_sold']); ?></div>
                        </div>
                        <div class="text-right">
                            <div>₱<?php echo number_format($product['price'], 2); ?> each</div>
                            <div>Total: ₱<?php echo number_format($product['total_revenue'], 2); ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <script>
        // Initialize sales chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($daily_sales, 'date')); ?>,
                datasets: [{
                    label: 'Daily Sales',
                    data: <?php echo json_encode(array_column($daily_sales, 'daily_sales')); ?>,
                    borderColor: '#4CAF50',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 