<?php
session_start();
require_once("db.php");

// Get all categories for filter
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

if (isset($_GET['price_range']) && !empty($_GET['price_range'])) {
    switch ($_GET['price_range']) {
        case 'under_100':
            $where_conditions[] = "p.price < 100";
            break;
        case '100_to_500':
            $where_conditions[] = "p.price BETWEEN 100 AND 500";
            break;
        case '500_to_1000':
            $where_conditions[] = "p.price BETWEEN 500 AND 1000";
            break;
        case 'over_1000':
            $where_conditions[] = "p.price > 1000";
            break;
    }
}

if (isset($_GET['sort']) && !empty($_GET['sort'])) {
    $order_by = match($_GET['sort']) {
        'price_low' => "p.price ASC",
        'price_high' => "p.price DESC",
        'name_asc' => "p.name ASC",
        'name_desc' => "p.name DESC",
        default => "p.name ASC"
    };
} else {
    $order_by = "p.name ASC";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get products with filters
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          JOIN categories c ON p.category_id = c.category_id 
          $where_clause 
          ORDER BY $order_by";

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
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>Products - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-card {
            height: 100%;
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            background: white;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .product-image {
            height: 250px;
            object-fit: cover;
            border-radius: 12px 12px 0 0;
        }
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .form-select, .form-control {
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 0.6rem 1rem;
            transition: all 0.2s ease;
        }
        .form-select:focus, .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15);
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #0d6efd;
            border: none;
        }
        .btn-primary:hover {
            background: #0b5ed7;
        }
        .btn-outline-primary {
            border-width: 2px;
        }
        .btn-outline-secondary {
            border-width: 2px;
        }
        .card-body {
            padding: 1.5rem;
        }
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.75rem;
            line-height: 1.3;
        }
        .card-text {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }
        .h5.mb-0 {
            color: #0d6efd;
            font-weight: 600;
            font-size: 2.1rem;
        }
        .text-muted {
            color: #6c757d !important;
            font-size: 0.85rem;
        }
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.2rem;
        }
        .alert-info {
            background-color: #e8f4f8;
            color: #0c5460;
        }
        .input-group {
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        .input-group .form-control {
            border-right: none;
        }
        .input-group .btn {
            border-left: none;
            padding: 0.6rem 1.2rem;
        }
        .h2 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .small {
            font-size: 0.875rem;
        }
        .mt-2 {
            margin-top: 1rem !important;
        }
        .me-2 {
            margin-right: 0.75rem !important;
        }
        .mb-4 {
            margin-bottom: 2rem !important;
        }
        .g-4 {
            --bs-gutter-y: 1.5rem;
            --bs-gutter-x: 1.5rem;
        }
        .g-3 {
            --bs-gutter-y: 1rem;
            --bs-gutter-x: 1rem;
        }
        .btn-secondary:disabled {
            background-color: #6c757d;
            opacity: 0.65;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <h1 class="h2 mb-4">Products</h1>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
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
                    <label class="form-label">Price Range</label>
                    <select name="price_range" class="form-select">
                        <option value="">All Prices</option>
                        <option value="under_100" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] === 'under_100') ? 'selected' : ''; ?>>
                            Under ₱100
                        </option>
                        <option value="100_to_500" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] === '100_to_500') ? 'selected' : ''; ?>>
                            ₱100 - ₱500
                        </option>
                        <option value="500_to_1000" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] === '500_to_1000') ? 'selected' : ''; ?>>
                            ₱500 - ₱1000
                        </option>
                        <option value="over_1000" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] === 'over_1000') ? 'selected' : ''; ?>>
                            Over ₱1000
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="name_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'name_asc') ? 'selected' : ''; ?>>
                            Name (A-Z)
                        </option>
                        <option value="name_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'name_desc') ? 'selected' : ''; ?>>
                            Name (Z-A)
                        </option>
                        <option value="price_low" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_low') ? 'selected' : ''; ?>>
                            Price (Low to High)
                        </option>
                        <option value="price_high" <?php echo (isset($_GET['sort']) && $_GET['sort'] === 'price_high') ? 'selected' : ''; ?>>
                            Price (High to Low)
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search products..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="products.php" class="btn btn-outline-secondary">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="row g-4">
            <?php if (empty($products)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No products found matching your criteria.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card product-card">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="card-img-top product-image">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text text-muted small">
                                    <?php echo htmlspecialchars($product['description']); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h5 mb-0">₱<?php echo number_format($product['price'], 2); ?></span>
                                    <div class="d-flex flex-column align-items-end gap-2 ms-3" style="min-width: 160px;">
                                        <a href="product_details.php?id=<?php echo $product['product_id']; ?>" class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="fas fa-eye me-2"></i> View Details
                                        </a>
                                        <?php if ($product['stock'] > 0): ?>
                                            <form method="POST" action="add_to_cart.php" class="d-inline w-100">
                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                <button type="submit" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-lg w-100 d-flex align-items-center justify-content-center" disabled>Out of Stock</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        Category: <?php echo htmlspecialchars($product['category_name']); ?><br>
                                        Stock: <?php echo $product['stock']; ?> available
                                    </small>
                                </div>
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