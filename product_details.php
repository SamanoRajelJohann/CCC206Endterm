<?php
session_start();
require_once("db.php");

// Check if product ID is provided
if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$product_id = $_GET['id'];

// Fetch product details
$stmt = $mysqli->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.category_id 
    WHERE p.product_id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: products.php");
    exit();
}

// Calculate days until expiry
$expiry = new DateTime($product['expiry_date']);
$today = new DateTime();
$days_until_expiry = $today->diff($expiry)->days;

// Calculate average rating
$stmt = $mysqli->prepare("SELECT AVG(rating) as avg_rating FROM product_reviews WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$avg_rating = round($stmt->get_result()->fetch_assoc()['avg_rating'], 1);

// Get review count
$stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM product_reviews WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$review_count = $stmt->get_result()->fetch_assoc()['count'];

// Get related products from the same category
$stmt = $mysqli->prepare("
    SELECT * FROM products 
    WHERE category_id = ? AND product_id != ? 
    LIMIT 4
");
$stmt->bind_param("ii", $product['category_id'], $product_id);
$stmt->execute();
$related_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle add to cart
if (isset($_POST['add_to_cart'])) {
    // ... existing add to cart code ...
}

// Handle add to wishlist
if (isset($_POST['add_to_wishlist'])) {
    if (!isset($_SESSION['logged_in'])) {
        $_SESSION['error'] = "Please login to add items to wishlist";
        header("Location: index.php");
        exit();
    }

    $account_id = $_SESSION['id'];
    $product_id = intval($_GET['id']);

    // Check if already in wishlist
    $stmt = $mysqli->prepare("SELECT wishlist_id FROM wishlist WHERE account_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $account_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Item is already in your wishlist";
    } else {
        $stmt = $mysqli->prepare("INSERT INTO wishlist (account_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $account_id, $product_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Item added to wishlist";
        } else {
            $_SESSION['error'] = "Failed to add item to wishlist";
        }
    }
    
    header("Location: product_details.php?id=" . $product_id);
    exit();
}

// Check if product is in wishlist
$in_wishlist = false;
if (isset($_SESSION['logged_in'])) {
    $stmt = $mysqli->prepare("SELECT wishlist_id FROM wishlist WHERE account_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $_SESSION['id'], $product_id);
    $stmt->execute();
    $in_wishlist = $stmt->get_result()->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title><?php echo htmlspecialchars($product['name']); ?> - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-details {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .product-image {
            width: 100%;
            max-width: 500px;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .product-info {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stock-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
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
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .related-products {
            margin-top: 3rem;
        }
        .related-product-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1rem;
            height: 100%;
            transition: transform 0.2s;
        }
        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .related-product-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
        }
        .quantity-input {
            width: 100px;
        }
    </style>
</head>
<body>
    <div class="product-details">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                <li class="breadcrumb-item"><a href="products.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-md-6 mb-4">
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                     class="product-image">
            </div>
            <div class="col-md-6">
                <div class="product-info">
                    <h1 class="h2 mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                    <p class="text-muted mb-2"><?php echo htmlspecialchars($product['category_name']); ?></p>
                    <div class="rating mb-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= round($avg_rating) ? '' : '-o'; ?>"></i>
                        <?php endfor; ?>
                        <span class="ms-2">(<?php echo number_format($avg_rating, 1); ?>)</span>
                        <a href="product_reviews.php?product_id=<?php echo $product['product_id']; ?>" class="ms-2">
                            <?php echo $review_count; ?> reviews
                        </a>
                    </div>
                    
                    <h2 class="h4 mb-3">₱<?php echo number_format($product['price'], 2); ?></h2>
                    
                    <?php if ($product['stock'] > 10): ?>
                        <span class="stock-status in-stock mb-3">
                            <i class="fas fa-check-circle"></i> In Stock
                        </span>
                    <?php elseif ($product['stock'] > 0): ?>
                        <span class="stock-status low-stock mb-3">
                            <i class="fas fa-exclamation-circle"></i> Low Stock (<?php echo $product['stock']; ?> left)
                        </span>
                    <?php else: ?>
                        <span class="stock-status out-of-stock mb-3">
                            <i class="fas fa-times-circle"></i> Out of Stock
                        </span>
                    <?php endif; ?>

                    <?php if ($days_until_expiry <= 30): ?>
                        <div class="expiry-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Expires in <?php echo $days_until_expiry; ?> days
                        </div>
                    <?php endif; ?>

                    <hr class="my-4">

                    <p class="mb-4"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

                    <div class="mb-4">
                        <strong>Manufacturer:</strong> <?php echo htmlspecialchars($product['manufacturer']); ?><br>
                        <strong>Expiry Date:</strong> <?php echo date('F j, Y', strtotime($product['expiry_date'])); ?>
                    </div>

                    <div class="product-actions mt-4">
                        <form action="add_to_cart.php" method="POST" class="d-inline">
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                            <div class="input-group mb-3" style="max-width: 200px;">
                                <button type="button" class="btn btn-outline-secondary" onclick="decrementQuantity()">-</button>
                                <input type="number" class="form-control text-center" name="quantity" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                                <button type="button" class="btn btn-outline-secondary" onclick="incrementQuantity()">+</button>
                            </div>
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </form>
                        <?php if (isset($_SESSION['logged_in'])): ?>
                            <?php
                            // Check if product is in wishlist
                            $wishlist_query = "SELECT * FROM wishlist WHERE account_id = ? AND product_id = ?";
                            $wishlist_stmt = $mysqli->prepare($wishlist_query);
                            $wishlist_stmt->bind_param("ii", $_SESSION['id'], $product['product_id']);
                            $wishlist_stmt->execute();
                            $in_wishlist = $wishlist_stmt->get_result()->num_rows > 0;
                            ?>
                            <form action="wishlist.php" method="POST" class="d-inline">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <input type="hidden" name="action" value="<?php echo $in_wishlist ? 'remove' : 'add'; ?>">
                                <button type="submit" class="btn <?php echo $in_wishlist ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                    <i class="fas fa-heart"></i> <?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-outline-danger">
                                <i class="fas fa-heart"></i> Add to Wishlist
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($related_products)): ?>
        <div class="related-products">
            <h3 class="h4 mb-4">Related Products</h3>
            <div class="row">
                <?php foreach ($related_products as $related): ?>
                    <div class="col-md-3 mb-4">
                        <div class="related-product-card">
                            <img src="<?php echo htmlspecialchars($related['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($related['name']); ?>" 
                                 class="related-product-image mb-3">
                            <h4 class="h6 mb-2"><?php echo htmlspecialchars($related['name']); ?></h4>
                            <p class="mb-2">₱<?php echo number_format($related['price'], 2); ?></p>
                            <a href="product_details.php?id=<?php echo $related['product_id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 