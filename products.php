<?php
session_start();
require_once("db.php");

// Get all categories for the filter
$stmt = $mysqli->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build the product query
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          JOIN categories c ON p.category_id = c.category_id 
          WHERE 1=1";
$params = [];
$types = "";

// Category filter
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $query .= " AND p.category_id = ?";
    $params[] = $_GET['category'];
    $types .= "i";
}

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.manufacturer LIKE ?)";
    $search = "%" . $_GET['search'] . "%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

// Sort options
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'name_desc':
        $query .= " ORDER BY p.name DESC";
        break;
    default:
        $query .= " ORDER BY p.name ASC";
}

// Prepare and execute the query
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
    <title>Products - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .products-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .product-card {
            height: 100%;
            transition: transform 0.2s;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .product-info {
            padding: 1rem;
        }
        .stock-status {
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }
        .in-stock {
            background: #d4edda;
            color: #155724;
        }
        .low-stock {
            background: #fff3cd;
            color: #856404;
        }
        .out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
        .expiry-warning {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        .nav-links {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="products-container">
        <div class="nav-links">
            <?php if (isset($_SESSION['logged_in'])): ?>
                <a href="cart.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-shopping-cart"></i> Cart
                </a>
                <a href="profile.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            <?php else: ?>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
        </div>

        <div class="filters">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                           placeholder="Search products...">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                    <?php echo (isset($_GET['category']) && $_GET['category'] == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort" class="form-label">Sort By</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
            </form>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php foreach ($products as $product): 
                // Calculate days until expiry
                $expiry = new DateTime($product['expiry_date']);
                $today = new DateTime();
                $days_until_expiry = $today->diff($expiry)->days;
            ?>
                <div class="col">
                    <div class="product-card">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="product-image">
                        <div class="product-info">
                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($product['category_name']); ?></p>
                            
                            <h6 class="mb-2">₱<?php echo number_format($product['price'], 2); ?></h6>
                            
                            <?php if ($product['stock'] > 10): ?>
                                <span class="stock-status in-stock">
                                    <i class="fas fa-check-circle"></i> In Stock
                                </span>
                            <?php elseif ($product['stock'] > 0): ?>
                                <span class="stock-status low-stock">
                                    <i class="fas fa-exclamation-circle"></i> Low Stock
                                </span>
                            <?php else: ?>
                                <span class="stock-status out-of-stock">
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </span>
                            <?php endif; ?>

                            <?php if ($days_until_expiry <= 30): ?>
                                <div class="expiry-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Expires in <?php echo $days_until_expiry; ?> days
                                </div>
                            <?php endif; ?>

                            <div class="mt-3">
                                <a href="product_details.php?id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm w-100">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="alert alert-info mt-4">
                No products found matching your criteria.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 