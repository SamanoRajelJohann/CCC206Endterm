<?php
session_start();
require_once("db.php");

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    $_SESSION['error'] = "Please login to manage your wishlist";
    header("Location: login.php");
    exit();
}

$account_id = $_SESSION['id'];

// Handle wishlist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['product_id'])) {
        $product_id = intval($_POST['product_id']);
        $action = $_POST['action'];

        if ($action === 'add') {
            // Check if product exists and is not already in wishlist
            $check_query = "SELECT * FROM wishlist WHERE account_id = ? AND product_id = ?";
            $check_stmt = $mysqli->prepare($check_query);
            $check_stmt->bind_param("ii", $account_id, $product_id);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows === 0) {
                $insert_query = "INSERT INTO wishlist (account_id, product_id) VALUES (?, ?)";
                $insert_stmt = $mysqli->prepare($insert_query);
                $insert_stmt->bind_param("ii", $account_id, $product_id);
                $insert_stmt->execute();
                $_SESSION['success'] = "Product added to wishlist";
            }
        } elseif ($action === 'remove') {
            $delete_query = "DELETE FROM wishlist WHERE account_id = ? AND product_id = ?";
            $delete_stmt = $mysqli->prepare($delete_query);
            $delete_stmt->bind_param("ii", $account_id, $product_id);
            $delete_stmt->execute();
            $_SESSION['success'] = "Product removed from wishlist";
        }
    }
}

// Fetch wishlist items
$query = "SELECT p.*, w.wishlist_id 
          FROM wishlist w 
          JOIN products p ON w.product_id = p.product_id 
          WHERE w.account_id = ? 
          ORDER BY w.created_at DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$wishlist_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>My Wishlist - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .wishlist-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .wishlist-item {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        .empty-wishlist {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="wishlist-container">
        <h1 class="h2 mb-4">My Wishlist</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($wishlist_items)): ?>
            <div class="empty-wishlist">
                <i class="fas fa-heart fa-3x text-muted mb-3"></i>
                <h3>Your Wishlist is Empty</h3>
                <p class="text-muted">Add items to your wishlist to see them here.</p>
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i> Browse Products
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($wishlist_items as $item): ?>
                <div class="wishlist-item">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 class="product-image">
                        </div>
                        <div class="col-md-4">
                            <h5 class="mb-1">
                                <a href="product_details.php?id=<?php echo $item['product_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            </h5>
                            <p class="text-muted mb-0">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </p>
                        </div>
                        <div class="col-md-2">
                            <h5 class="mb-0">₱<?php echo number_format($item['price'], 2); ?></h5>
                        </div>
                        <div class="col-md-4 text-end">
                            <form action="add_to_cart.php" method="POST" class="d-inline">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </form>
                            <form action="wishlist.php" method="POST" class="d-inline">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 