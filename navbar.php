<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?php 
            if (isset($_SESSION['logged_in'])) {
                echo $_SESSION['role'] === 'Admin' ? 'admin_dashboard.php' : 'user_dashboard.php';
            } else {
                echo 'index.php';
            }
        ?>">
            <i class="fas fa-pills text-primary me-2" style="font-size: 2.2rem;"></i>
            <span class="fw-bold" style="font-size: 1.8rem;">Rhine Lab</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link px-4 py-3 mx-1 rounded-3 <?php 
                        echo ($current_page === 'index.php' || 
                             $current_page === 'admin_dashboard.php' || 
                             $current_page === 'user_dashboard.php') ? 'active bg-primary text-white' : ''; 
                    ?>" href="<?php 
                        if (isset($_SESSION['logged_in'])) {
                            echo $_SESSION['role'] === 'Admin' ? 'admin_dashboard.php' : 'user_dashboard.php';
                        } else {
                            echo 'index.php';
                        }
                    ?>">
                        <i class="fas fa-home me-2" style="font-size: 1.2rem;"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-4 py-3 mx-1 rounded-3 <?php echo $current_page === 'products.php' ? 'active bg-primary text-white' : ''; ?>" href="products.php">
                        <i class="fas fa-box me-2" style="font-size: 1.2rem;"></i> Products
                    </a>
                </li>
                <?php if (isset($_SESSION['logged_in'])): ?>
                    <li class="nav-item">
                        <a class="nav-link px-4 py-3 mx-1 rounded-3 <?php echo $current_page === 'cart.php' ? 'active bg-primary text-white' : ''; ?>" href="cart.php">
                            <i class="fas fa-shopping-cart me-2" style="font-size: 1.2rem;"></i> Cart
                            <?php
                            // Get cart item count
                            if (isset($_SESSION['id'])) {
                                $stmt = $mysqli->prepare("SELECT SUM(quantity) as count FROM cart WHERE account_id = ?");
                                $stmt->bind_param("i", $_SESSION['id']);
                                $stmt->execute();
                                $count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
                                if ($count > 0) {
                                    echo '<span class="badge bg-danger rounded-pill ms-1">' . $count . '</span>';
                                }
                            }
                            ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-4 py-3 mx-1 rounded-3 <?php echo $current_page === 'wishlist.php' ? 'active bg-primary text-white' : ''; ?>" href="wishlist.php">
                            <i class="fas fa-heart me-2" style="font-size: 1.2rem;"></i> Wishlist
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-4 py-3 mx-1 rounded-3 <?php echo $current_page === 'orders.php' ? 'active bg-primary text-white' : ''; ?>" href="orders.php">
                            <i class="fas fa-box me-2" style="font-size: 1.2rem;"></i> Orders
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['logged_in'])): ?>
                    <?php if ($_SESSION['role'] === 'Admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link px-4 py-3 mx-1 rounded-3 <?php echo strpos($current_page, 'admin_') === 0 ? 'active bg-primary text-white' : ''; ?>" href="admin_dashboard.php">
                                <i class="fas fa-tachometer-alt me-2" style="font-size: 1.2rem;"></i> Admin Dashboard
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle px-4 py-3 mx-1 rounded-3 d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2" style="font-size: 1.2rem;"></i> 
                            <span class="d-none d-lg-inline"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                            <li>
                                <a class="dropdown-item py-3" href="profile.php">
                                    <i class="fas fa-user-circle me-2" style="font-size: 1.1rem;"></i> Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-3" href="orders.php">
                                    <i class="fas fa-shopping-bag me-2" style="font-size: 1.1rem;"></i> Orders
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-3 text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2" style="font-size: 1.1rem;"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link px-4 py-3 mx-1 rounded-3" href="index.php#login">
                            <i class="fas fa-sign-in-alt me-2" style="font-size: 1.2rem;"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-4 py-3 mx-1 rounded-3 btn btn-primary text-white" href="index.php#register">
                            <i class="fas fa-user-plus me-2" style="font-size: 1.2rem;"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<style>
.navbar {
    padding: 1rem 0;
    transition: all 0.3s ease;
}

.navbar-brand {
    font-size: 1.8rem;
    transition: all 0.3s ease;
}

.navbar-brand:hover {
    transform: translateY(-2px);
}

.nav-link {
    font-weight: 500;
    font-size: 1.1rem;
    transition: all 0.2s ease;
    position: relative;
    padding: 0.8rem 1.2rem !important;
}

.nav-link:hover {
    transform: translateY(-2px);
}

.nav-link.active {
    font-weight: 600;
}

.dropdown-menu {
    min-width: 220px;
    padding: 0.8rem;
    margin-top: 0.5rem;
}

.dropdown-item {
    border-radius: 8px;
    transition: all 0.2s ease;
    font-size: 1.1rem;
    padding: 0.8rem 1rem;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.badge {
    font-size: 0.85rem;
    padding: 0.4em 0.7em;
}

@media (max-width: 991.98px) {
    .navbar-collapse {
        background: white;
        padding: 1.2rem;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-top: 1rem;
    }
    
    .nav-link {
        padding: 1rem 1.2rem !important;
        margin: 0.3rem 0 !important;
    }
}
</style>

<!-- Add padding to body to account for fixed navbar -->
<style>
body {
    padding-top: 100px;
}
</style> 