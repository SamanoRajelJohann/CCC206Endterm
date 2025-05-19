<?php
session_start();
require_once("db.php");

// Check if product ID is provided
if (!isset($_GET['product_id'])) {
    header("Location: products.php");
    exit();
}

$product_id = intval($_GET['product_id']);

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $review_id = intval($_POST['review_id']);
        $action = $_POST['action'];

        // Verify the review exists and belongs to the user or user is admin
        $check_stmt = $mysqli->prepare("
            SELECT r.*, a.role 
            FROM product_reviews r 
            JOIN accounts a ON r.account_id = a.account_id 
            WHERE r.review_id = ?
        ");
        $check_stmt->bind_param("i", $review_id);
        $check_stmt->execute();
        $review = $check_stmt->get_result()->fetch_assoc();

        if ($review && (isset($_SESSION['logged_in']) && ($_SESSION['id'] == $review['account_id'] || $_SESSION['role'] === 'admin'))) {
            switch ($action) {
                case 'edit':
                    $rating = intval($_POST['rating']);
                    $review_text = trim($_POST['review_text']);
                    
                    if ($rating >= 1 && $rating <= 5) {
                        $update_stmt = $mysqli->prepare("UPDATE product_reviews SET rating = ?, review_text = ? WHERE review_id = ?");
                        $update_stmt->bind_param("isi", $rating, $review_text, $review_id);
                        if ($update_stmt->execute()) {
                            $_SESSION['success'] = "Review updated successfully";
                        } else {
                            $_SESSION['error'] = "Failed to update review";
                        }
                    }
                    break;

                case 'delete':
                    $delete_stmt = $mysqli->prepare("DELETE FROM product_reviews WHERE review_id = ?");
                    $delete_stmt->bind_param("i", $review_id);
                    if ($delete_stmt->execute()) {
                        $_SESSION['success'] = "Review deleted successfully";
                    } else {
                        $_SESSION['error'] = "Failed to delete review";
                    }
                    break;

                case 'approve':
                    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                        $update_stmt = $mysqli->prepare("UPDATE product_reviews SET status = 'approved' WHERE review_id = ?");
                        $update_stmt->bind_param("i", $review_id);
                        if ($update_stmt->execute()) {
                            $_SESSION['success'] = "Review approved";
                        } else {
                            $_SESSION['error'] = "Failed to approve review";
                        }
                    }
                    break;

                case 'reject':
                    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                        $update_stmt = $mysqli->prepare("UPDATE product_reviews SET status = 'rejected' WHERE review_id = ?");
                        $update_stmt->bind_param("i", $review_id);
                        if ($update_stmt->execute()) {
                            $_SESSION['success'] = "Review rejected";
                        } else {
                            $_SESSION['error'] = "Failed to reject review";
                        }
                    }
                    break;
            }
        }
        
        header("Location: product_reviews.php?product_id=" . $product_id);
        exit();
    }
}

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

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['logged_in'])) {
    $rating = intval($_POST['rating']);
    $review_text = trim($_POST['review_text']);
    $account_id = $_SESSION['id'];

    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = "Rating must be between 1 and 5";
    } else {
        // Check if user has already reviewed this product
        $check_stmt = $mysqli->prepare("SELECT review_id FROM product_reviews WHERE account_id = ? AND product_id = ?");
        $check_stmt->bind_param("ii", $account_id, $product_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $_SESSION['error'] = "You have already reviewed this product";
        } else {
            // Insert new review
            $insert_stmt = $mysqli->prepare("INSERT INTO product_reviews (product_id, account_id, rating, review_text) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("iiis", $product_id, $account_id, $rating, $review_text);
            
            if ($insert_stmt->execute()) {
                $_SESSION['success'] = "Review submitted successfully";
            } else {
                $_SESSION['error'] = "Failed to submit review";
            }
        }
    }
    
    header("Location: product_reviews.php?product_id=" . $product_id);
    exit();
}

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

// Fetch reviews with user information
$query = "SELECT r.*, a.username, a.role 
          FROM product_reviews r 
          JOIN accounts a ON r.account_id = a.account_id 
          WHERE r.product_id = ? 
          ORDER BY r.created_at DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if current user has reviewed
$has_reviewed = false;
if (isset($_SESSION['logged_in'])) {
    $check_stmt = $mysqli->prepare("SELECT review_id FROM product_reviews WHERE account_id = ? AND product_id = ?");
    $check_stmt->bind_param("ii", $_SESSION['id'], $product_id);
    $check_stmt->execute();
    $has_reviewed = $check_stmt->get_result()->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>Reviews - <?php echo htmlspecialchars($product['name']); ?> - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reviews-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .review-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .star-rating {
            color: #ffc107;
        }
        .star-rating .far {
            color: #e4e5e9;
        }
        .review-form {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .rating-input {
            display: none;
        }
        .rating-label {
            cursor: pointer;
            font-size: 1.5rem;
            color: #e4e5e9;
            transition: color 0.2s;
        }
        .rating-input:checked ~ .rating-label,
        .rating-label:hover,
        .rating-label:hover ~ .rating-label {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="reviews-container">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                <li class="breadcrumb-item"><a href="product_details.php?id=<?php echo $product_id; ?>"><?php echo htmlspecialchars($product['name']); ?></a></li>
                <li class="breadcrumb-item active">Reviews</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-md-4">
                <div class="review-card">
                    <h2 class="h4 mb-3">Customer Reviews</h2>
                    <div class="d-flex align-items-center mb-3">
                        <div class="star-rating me-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= round($avg_rating) ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="h5 mb-0"><?php echo number_format($avg_rating, 1); ?></span>
                    </div>
                    <p class="text-muted">Based on <?php echo $review_count; ?> reviews</p>
                </div>
            </div>
            <div class="col-md-8">
                <?php if (isset($_SESSION['logged_in']) && !$has_reviewed): ?>
                    <div class="review-form">
                        <h3 class="h4 mb-3">Write a Review</h3>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Rating</label>
                                <div class="rating-input-group">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" class="rating-input" required>
                                        <label for="rating<?php echo $i; ?>" class="rating-label">
                                            <i class="fas fa-star"></i>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="review_text" class="form-label">Your Review</label>
                                <textarea class="form-control" id="review_text" name="review_text" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (empty($reviews)): ?>
                    <div class="alert alert-info">
                        No reviews yet. Be the first to review this product!
                    </div>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h5 class="mb-1">
                                        <?php echo htmlspecialchars($review['username']); ?>
                                        <?php if ($review['role'] === 'admin'): ?>
                                            <span class="badge bg-primary">Admin</span>
                                        <?php endif; ?>
                                    </h5>
                                    <div class="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block">
                                        <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                    </small>
                                    <?php if (isset($_SESSION['logged_in']) && ($_SESSION['id'] == $review['account_id'] || isset($_SESSION['role']) && $_SESSION['role'] === 'admin')): ?>
                                        <div class="btn-group mt-2">
                                            <?php if ($_SESSION['id'] == $review['account_id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editReviewModal<?php echo $review['review_id']; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this review?');">
                                                    <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                                <?php if ($review['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                        </div>

                        <!-- Edit Review Modal -->
                        <div class="modal fade" id="editReviewModal<?php echo $review['review_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Review</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                            <input type="hidden" name="action" value="edit">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Rating</label>
                                                <div class="rating-input-group">
                                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                                        <input type="radio" name="rating" value="<?php echo $i; ?>" 
                                                               id="editRating<?php echo $review['review_id']; ?>_<?php echo $i; ?>" 
                                                               class="rating-input" required
                                                               <?php echo $i == $review['rating'] ? 'checked' : ''; ?>>
                                                        <label for="editRating<?php echo $review['review_id']; ?>_<?php echo $i; ?>" class="rating-label">
                                                            <i class="fas fa-star"></i>
                                                        </label>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="editReviewText<?php echo $review['review_id']; ?>" class="form-label">Your Review</label>
                                                <textarea class="form-control" id="editReviewText<?php echo $review['review_id']; ?>" 
                                                          name="review_text" rows="4" required><?php echo htmlspecialchars($review['review_text']); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 