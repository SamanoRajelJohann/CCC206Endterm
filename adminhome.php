<?php
session_start();
require_once("db.php");

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Handle product deletion
if (isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];
    $stmt = $mysqli->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch all products with their categories
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          JOIN categories c ON p.category_id = c.category_id 
          ORDER BY p.created_at DESC";
$result = $mysqli->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .navbar {
            margin-bottom: 2rem;
        }
        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .product-table th, .product-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .product-table th {
            background-color: #f5f5f5;
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
        .btn-edit {
            background-color: #2196F3;
            color: white;
        }
        /* DataTables Custom Styling */
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 15px;
        }
        .dataTables_wrapper .dataTables_filter input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-left: 8px;
        }
        .dataTables_wrapper .dataTables_length select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .dataTables_wrapper .dataTables_info {
            padding: 15px 0;
        }
        .dataTables_wrapper .dataTables_paginate {
            padding-top: 15px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 8px 16px;
            margin: 0 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background-color: #4CAF50;
            color: white !important;
            border-color: #4CAF50;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="admin-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Admin Dashboard</h1>
            <div>
                <a href="admin_products.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-box"></i> Products
                </a>
                <a href="admin_orders.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
                <a href="admin_categories.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-tags"></i> Categories
                </a>
            </div>
        </div>

        <h2>Product Management</h2>
        <table id="productTable" class="product-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Manufacturer</th>
                    <th>Expiry Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['product_id']; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                    <td>$<?php echo number_format($row['price'], 2); ?></td>
                    <td><?php echo $row['stock']; ?></td>
                    <td><?php echo htmlspecialchars($row['manufacturer']); ?></td>
                    <td><?php echo $row['expiry_date']; ?></td>
                    <td>
                        <a href="edit_product.php?id=<?php echo $row['product_id']; ?>" class="btn btn-edit">Edit</a>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">
                            <button type="submit" name="delete_product" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#productTable').DataTable({
                "pageLength": 10,
                "order": [[0, "desc"]], // Sort by ID column by default
                "language": {
                    "search": "Search products:",
                    "lengthMenu": "Show _MENU_ products per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ products",
                    "infoEmpty": "No products available",
                    "infoFiltered": "(filtered from _MAX_ total products)"
                },
                "columnDefs": [
                    { "orderable": false, "targets": 7 } // Disable sorting on Actions column
                ]
            });
        });
    </script>
</body>
</html> 