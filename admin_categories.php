<?php
session_start();
require_once("db.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Handle category deletion
if (isset($_POST['delete_category'])) {
    $category_id = $_POST['category_id'];
    
    // Check if category has products
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        header("Location: admin_categories.php?error=Cannot delete category with existing products");
        exit();
    }
    
    $stmt = $mysqli->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    header("Location: admin_categories.php?message=Category deleted successfully");
    exit();
}

// Handle category updates
if (isset($_POST['update_category'])) {
    $category_id = $_POST['category_id'];
    $name = $_POST['name'];
    
    $stmt = $mysqli->prepare("UPDATE categories SET name = ? WHERE category_id = ?");
    $stmt->bind_param("si", $name, $category_id);
    $stmt->execute();
    header("Location: admin_categories.php?message=Category updated successfully");
    exit();
}

// Handle category creation
if (isset($_POST['create_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (!empty($name)) {
        $stmt = $mysqli->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        $stmt->execute();
        header("Location: admin_categories.php?message=Category created successfully");
        exit();
    }
}

// Get categories with product counts
$query = "SELECT c.*, COUNT(p.product_id) as product_count 
          FROM categories c 
          LEFT JOIN products p ON c.category_id = p.category_id 
          GROUP BY c.category_id 
          ORDER BY c.name";
$stmt = $mysqli->prepare($query);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - Rhine Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .category-card {
            transition: transform 0.2s;
        }
        .category-card:hover {
            transform: translateY(-5px);
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
            <h1 class="h2">Category Management</h1>
            <div>
                <a href="admin_dashboard.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>
        </div>

        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Categories Grid -->
        <div class="row g-4">
            <?php foreach ($categories as $category): ?>
                <div class="col-md-4">
                    <div class="card category-card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                            <p class="card-text text-muted">
                                <?php echo $category['product_count']; ?> products
                            </p>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?php echo $category['category_id']; ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if ($category['product_count'] == 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal<?php echo $category['category_id']; ?>">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Category Modal -->
                <div class="modal fade" id="editCategoryModal<?php echo $category['category_id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Category</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($category['description']); ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="update_category" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete Category Modal -->
                <div class="modal fade" id="deleteCategoryModal<?php echo $category['category_id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Delete Category</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete this category?</p>
                                <p><strong><?php echo htmlspecialchars($category['name']); ?></strong></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                    <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Create Category Modal -->
    <div class="modal fade" id="createCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
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
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_category" class="btn btn-primary">Create Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 