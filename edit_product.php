<?php
session_start();
require_once("db.php");

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$errors = [];
$success = false;

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch product details
$stmt = $mysqli->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: adminhome.php");
    exit();
}

// Fetch all categories
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = $mysqli->query($categories_query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category_id = $_POST['category_id'];
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $manufacturer = trim($_POST['manufacturer']);
    $expiry_date = $_POST['expiry_date'];
    
    // Validation
    if (empty($name)) $errors[] = "Product name is required";
    if (empty($category_id)) $errors[] = "Category is required";
    if (empty($description)) $errors[] = "Description is required";
    if ($price <= 0) $errors[] = "Price must be greater than 0";
    if ($stock < 0) $errors[] = "Stock cannot be negative";
    if (empty($manufacturer)) $errors[] = "Manufacturer is required";
    if (empty($expiry_date)) $errors[] = "Expiry date is required";

    // Handle image upload
    $image_url = $product['image_url']; // Keep existing image by default
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $min_dimensions = [200, 200]; // Minimum width and height
        $max_dimensions = [2000, 2000]; // Maximum width and height

        // Debug information
        error_log("File upload attempt - Type: " . $_FILES['image']['type'] . ", Size: " . $_FILES['image']['size']);

        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = "Invalid file type. Only JPG, PNG, GIF and WebP are allowed. Received: " . $_FILES['image']['type'];
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = "File size too large. Maximum size is 5MB. Received: " . ($_FILES['image']['size'] / 1024 / 1024) . "MB";
        } else {
            // Get image dimensions
            $image_info = getimagesize($_FILES['image']['tmp_name']);
            if ($image_info === false) {
                $errors[] = "Invalid image file.";
            } else {
                list($width, $height) = $image_info;
                if ($width < $min_dimensions[0] || $height < $min_dimensions[1]) {
                    $errors[] = "Image dimensions too small. Minimum size is 200x200 pixels. Received: {$width}x{$height}";
                } elseif ($width > $max_dimensions[0] || $height > $max_dimensions[1]) {
                    $errors[] = "Image dimensions too large. Maximum size is 2000x2000 pixels. Received: {$width}x{$height}";
                } else {
                    $upload_dir = 'uploads/products/';
                    $thumbnail_dir = $upload_dir . 'thumbnails/';
                    
                    // Create directories if they don't exist
                    if (!file_exists($upload_dir)) {
                        if (!mkdir($upload_dir, 0777, true)) {
                            $errors[] = "Failed to create upload directory. Please check permissions.";
                            error_log("Failed to create directory: " . $upload_dir);
                        }
                    }
                    
                    if (!file_exists($thumbnail_dir)) {
                        if (!mkdir($thumbnail_dir, 0777, true)) {
                            $errors[] = "Failed to create thumbnail directory. Please check permissions.";
                            error_log("Failed to create directory: " . $thumbnail_dir);
                        }
                    }

                    // Check if directories are writable
                    if (!is_writable($upload_dir)) {
                        $errors[] = "Upload directory is not writable. Please check permissions.";
                        error_log("Directory not writable: " . $upload_dir);
                    }
                    
                    if (!is_writable($thumbnail_dir)) {
                        $errors[] = "Thumbnail directory is not writable. Please check permissions.";
                        error_log("Directory not writable: " . $thumbnail_dir);
                    }

                    if (empty($errors)) {
                        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                        $file_name = uniqid('product_') . '.' . $file_extension;
                        $target_path = $upload_dir . $file_name;

                        // Create image resource based on file type
                        switch ($_FILES['image']['type']) {
                            case 'image/jpeg':
                                $source_image = imagecreatefromjpeg($_FILES['image']['tmp_name']);
                                break;
                            case 'image/png':
                                $source_image = imagecreatefrompng($_FILES['image']['tmp_name']);
                                break;
                            case 'image/gif':
                                $source_image = imagecreatefromgif($_FILES['image']['tmp_name']);
                                break;
                            case 'image/webp':
                                $source_image = imagecreatefromwebp($_FILES['image']['tmp_name']);
                                break;
                        }

                        if ($source_image) {
                            // Create thumbnail
                            $thumbnail_width = 300;
                            $thumbnail_height = 300;
                            $thumbnail = imagecreatetruecolor($thumbnail_width, $thumbnail_height);

                            // Preserve transparency for PNG and GIF
                            if (in_array($_FILES['image']['type'], ['image/png', 'image/gif'])) {
                                imagealphablending($thumbnail, false);
                                imagesavealpha($thumbnail, true);
                                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                                imagefilledrectangle($thumbnail, 0, 0, $thumbnail_width, $thumbnail_height, $transparent);
                            }

                            // Resize image
                            imagecopyresampled(
                                $thumbnail, $source_image,
                                0, 0, 0, 0,
                                $thumbnail_width, $thumbnail_height,
                                $width, $height
                            );

                            // Save thumbnail
                            $thumbnail_path = $thumbnail_dir . $file_name;
                            
                            $thumbnail_saved = false;
                            switch ($_FILES['image']['type']) {
                                case 'image/jpeg':
                                    $thumbnail_saved = imagejpeg($thumbnail, $thumbnail_path, 90);
                                    break;
                                case 'image/png':
                                    $thumbnail_saved = imagepng($thumbnail, $thumbnail_path, 9);
                                    break;
                                case 'image/gif':
                                    $thumbnail_saved = imagegif($thumbnail, $thumbnail_path);
                                    break;
                                case 'image/webp':
                                    $thumbnail_saved = imagewebp($thumbnail, $thumbnail_path, 90);
                                    break;
                            }

                            if (!$thumbnail_saved) {
                                error_log("Failed to save thumbnail: " . $thumbnail_path);
                                $errors[] = "Failed to save thumbnail.";
                            }

                            // Save original image
                            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                                // Delete old image and thumbnail if they exist
                                if (!empty($product['image_url']) && file_exists($product['image_url'])) {
                                    unlink($product['image_url']);
                                    $old_thumbnail = str_replace('uploads/products/', 'uploads/products/thumbnails/', $product['image_url']);
                                    if (file_exists($old_thumbnail)) {
                                        unlink($old_thumbnail);
                                    }
                                }
                                $image_url = $target_path;
                            } else {
                                error_log("Failed to move uploaded file to: " . $target_path);
                                $errors[] = "Failed to upload image. Error: " . error_get_last()['message'];
                            }

                            // Clean up
                            imagedestroy($source_image);
                            imagedestroy($thumbnail);
                        } else {
                            error_log("Failed to create image resource from uploaded file");
                            $errors[] = "Failed to process image.";
                        }
                    }
                }
            }
        }
    } else if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error_message = getUploadErrorMessage($_FILES['image']['error']);
        error_log("File upload error: " . $error_message);
        $errors[] = "Error uploading image: " . $error_message;
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, image_url = ?, manufacturer = ?, expiry_date = ? WHERE product_id = ?");
        $stmt->bind_param("issdiissi", $category_id, $name, $description, $price, $stock, $image_url, $manufacturer, $expiry_date, $product_id);
        
        if ($stmt->execute()) {
            $success = true;
            // Redirect to admin dashboard after successful update
            header("Location: adminhome.php");
            exit();
        } else {
            $errors[] = "Failed to update product: " . $mysqli->error;
        }
        $stmt->close();
    }
}

function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Rhine Lab</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="img&css/style.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group textarea {
            height: 100px;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
        .success {
            color: green;
            margin-bottom: 10px;
        }
        .btn {
            padding: 10px 20px;
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
            background-color: #666;
            color: white;
        }
        .current-image {
            max-width: 200px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Edit Product</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <p>Product updated successfully!</p>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Product Name:</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($product['name']); ?>">
            </div>

            <div class="form-group">
                <label for="category_id">Category:</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select Category</option>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <option value="<?php echo $category['category_id']; ?>" <?php echo ($product['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="price">Price:</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($product['price']); ?>">
            </div>

            <div class="form-group">
                <label for="stock">Stock:</label>
                <input type="number" id="stock" name="stock" min="0" required value="<?php echo htmlspecialchars($product['stock']); ?>">
            </div>

            <div class="form-group">
                <label for="manufacturer">Manufacturer:</label>
                <input type="text" id="manufacturer" name="manufacturer" required value="<?php echo htmlspecialchars($product['manufacturer']); ?>">
            </div>

            <div class="form-group">
                <label for="expiry_date">Expiry Date:</label>
                <input type="date" id="expiry_date" name="expiry_date" required value="<?php echo htmlspecialchars($product['expiry_date']); ?>">
            </div>

            <div class="form-group">
                <label for="image">Product Image:</label>
                <?php if (!empty($product['image_url'])): ?>
                    <div class="mb-2">
                        <p>Current Image:</p>
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                             alt="Current product image" 
                             class="current-image"
                             style="max-width: 200px; max-height: 200px; object-fit: contain;">
                    </div>
                <?php endif; ?>
                <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(this);">
                <small class="form-text text-muted">
                    Allowed formats: JPG, PNG, GIF, WebP. Max size: 5MB. Min dimensions: 200x200px. Max dimensions: 2000x2000px.
                    Leave empty to keep current image.
                </small>
                <div id="imagePreview" class="mt-2" style="display: none;">
                    <p>New Image Preview:</p>
                    <img id="preview" src="#" alt="Preview" style="max-width: 200px; max-height: 200px; object-fit: contain;">
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Update Product</button>
                <a href="adminhome.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    function previewImage(input) {
        const preview = document.getElementById('preview');
        const previewDiv = document.getElementById('imagePreview');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewDiv.style.display = 'block';
            }
            
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.src = '#';
            previewDiv.style.display = 'none';
        }
    }
    </script>
</body>
</html> 