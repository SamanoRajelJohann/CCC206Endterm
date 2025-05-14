<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-pills"></i> Rhine Lab
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'products.php' ? 'active' : ''; ?>" href="products.php">
                        <i class="fas fa-box"></i> Products
                    </a>
                </li>
                <?php if (isset($_SESSION['logged_in'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'cart.php' ? 'active' : ''; ?>" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php
                            // Get cart item count
                            if (isset($_SESSION['id'])) {
                                $stmt = $mysqli->prepare("SELECT SUM(quantity) as count FROM cart WHERE account_id = ?");
                                $stmt->bind_param("i", $_SESSION['id']);
                                $stmt->execute();
                                $count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
                                if ($count > 0) {
                                    echo '<span class="badge bg-primary">' . $count . '</span>';
                                }
                            }
                            ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['logged_in'])): ?>
                    <?php if ($_SESSION['role'] === 'Admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($current_page, 'admin_') === 0 ? 'active' : ''; ?>" href="admin_dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user-circle"></i> Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="orders.php">
                                    <i class="fas fa-shopping-bag"></i> Orders
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#login">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#register">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 