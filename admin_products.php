<?php
session_start();
require_once("db.php");

// Check if user is logged in and is an admin
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
    header("Location: admin_products.php?message=Product deleted successfully");
    exit();
}

// Handle product updates
if (isset($_POST['update_product'])) {
    $product_id = $_POST['product_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $category_id = $_POST['category_id'];
    $manufacturer = $_POST['manufacturer'];
    $expiry_date = $_POST['expiry_date'];
    
    $stmt = $mysqli->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, manufacturer = ?, expiry_date = ? WHERE product_id = ?");
    $stmt->bind_param("ssdiissi", $name, $description, $price, $stock, $category_id, $manufacturer, $expiry_date, $product_id);
    $stmt->execute();
    header("Location: admin_products.php?message=Product updated successfully");
    exit();
}

// Handle product creation
if (isset($_POST['create_product'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $category_id = $_POST['category_id'];
    $manufacturer = $_POST['manufacturer'];
    $expiry_date = $_POST['expiry_date'];
    $image_url = 'img/products/default.jpg'; // Default image
    
    $stmt = $mysqli->prepare("INSERT INTO products (name, description, price, stock, category_id, manufacturer, expiry_date, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdiisss", $name, $description, $price, $stock, $category_id, $manufacturer, $expiry_date, $image_url);
    $stmt->execute();
    header("Location: admin_products.php?message=Product created successfully");
    exit();
}

// Get all categories for the dropdown
$stmt = $mysqli->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build the product query with filters
$where_conditions = [];
$params = [];
$types = "";

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $_GET['category'];
    $types .= "i";
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.manufacturer LIKE ?)";
    $search_term = "%" . $_GET['search'] . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (isset($_GET['stock_status']) && !empty($_GET['stock_status'])) {
    if ($_GET['stock_status'] === 'low') {
        $where_conditions[] = "p.stock <= 10";
    } elseif ($_GET['stock_status'] === 'out') {
        $where_conditions[] = "p.stock = 0";
    }
}

if (isset($_GET['expiry_status']) && !empty($_GET['expiry_status'])) {
    if ($_GET['expiry_status'] === 'soon') {
        $where_conditions[] = "p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($_GET['expiry_status'] === 'expired') {
        $where_conditions[] = "p.expiry_date < CURDATE()";
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get products with filters
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          JOIN categories c ON p.category_id = c.category_id 
          $where_clause 
          ORDER BY p.name";

$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        .stock-warning {
            color: #dc3545;
        }
        .expiry-warning {
            color: #ffc107;
        }
        .navbar {
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="admin-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Product Management</h1>
            <div>
                <a href="admin_dashboard.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProductModal">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
            </div>
        </div>

        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Stock Status</label>
                        <select name="stock_status" class="form-select">
                            <option value="">All</option>
                            <option value="low" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] === 'low') ? 'selected' : ''; ?>>Low Stock (≤10)</option>
                            <option value="out" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] === 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expiry Status</label>
                        <select name="expiry_status" class="form-select">
                            <option value="">All</option>
                            <option value="soon" <?php echo (isset($_GET['expiry_status']) && $_GET['expiry_status'] === 'soon') ? 'selected' : ''; ?>>Expiring Soon (≤30 days)</option>
                            <option value="expired" <?php echo (isset($_GET['expiry_status']) && $_GET['expiry_status'] === 'expired') ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="admin_products.php" class="btn btn-outline-secondary">Clear Filters</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Image</th>
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
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <div class="text-muted small"><?php echo htmlspecialchars($product['description']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <?php if ($product['stock'] <= 10): ?>
                                            <span class="stock-warning">
                                                <?php echo $product['stock']; ?> left
                                            </span>
                                        <?php else: ?>
                                            <?php echo $product['stock']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['manufacturer']); ?></td>
                                    <td>
                                        <?php
                                        $expiry = new DateTime($product['expiry_date']);
                                        $today = new DateTime();
                                        $days_until_expiry = $today->diff($expiry)->days;
                                        
                                        if ($expiry < $today) {
                                            echo '<span class="expiry-warning">Expired</span>';
                                        } elseif ($days_until_expiry <= 30) {
                                            echo '<span class="expiry-warning">' . $days_until_expiry . ' days left</span>';
                                        } else {
                                            echo $product['expiry_date'];
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProductModal<?php echo $product['product_id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProductModal<?php echo $product['product_id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit Product Modal -->
                                <div class="modal fade" id="editProductModal<?php echo $product['product_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Product</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Name</label>
                                                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Price</label>
                                                        <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $product['price']; ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Stock</label>
                                                        <input type="number" name="stock" class="form-control" value="<?php echo $product['stock']; ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Category</label>
                                                        <select name="category_id" class="form-select" required>
                                                            <?php foreach ($categories as $category): ?>
                                                                <option value="<?php echo $category['category_id']; ?>" <?php echo ($product['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Manufacturer</label>
                                                        <input type="text" name="manufacturer" class="form-control" value="<?php echo htmlspecialchars($product['manufacturer']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Expiry Date</label>
                                                        <input type="date" name="expiry_date" class="form-control" value="<?php echo $product['expiry_date']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_product" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Product Modal -->
                                <div class="modal fade" id="deleteProductModal<?php echo $product['product_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Product</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this product?</p>
                                                <p><strong><?php echo htmlspecialchars($product['name']); ?></strong></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Product Modal -->
    <div class="modal fade" id="createProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" name="price" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" name="manufacturer" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_product" class="btn btn-primary">Create Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 