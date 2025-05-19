<?php
// Include the database connection file
require_once("db.php");

// Start the session
session_start();

// Error messages
$errors = [];

// Handle Sign-Up
if (isset($_POST['signup'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $errors[] = "All fields are required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } else {
        // Check if the email already exists
        $stmt = $mysqli->prepare("SELECT account_id FROM accounts WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Email already registered.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user with default role 'User'
            $stmt = $mysqli->prepare("INSERT INTO accounts (username, email, password, role) VALUES (?, ?, ?, 'User')");
            $stmt->bind_param("sss", $name, $email, $hashed_password);

            if ($stmt->execute()) {
                $_SESSION['success'] = "Account created successfully. Please sign in.";
            } else {
                $errors[] = "Failed to create account.";
            }
        }

        $stmt->close();
    }
}

// Handle Sign-In
if (isset($_POST['signin'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($email) || empty($password)) {
        $errors[] = "Both email and password are required.";
    } else {
        // Prepare the statement to select the user based on email
        $stmt = $mysqli->prepare("SELECT account_id, username, password, role FROM accounts WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Debug information
            error_log("Attempting login for email: " . $email);
            error_log("Stored hash: " . $user['password']);
            error_log("Password verification result: " . (password_verify($password, $user['password']) ? 'true' : 'false'));
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct
                $_SESSION['id'] = $user['account_id'];
                $_SESSION['name'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;

                // Redirect based on role
                if (strtolower($user['role']) === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: products.php");
                }
                exit();
            } else {
                $errors[] = "Invalid email or password.";
            }
        } else {
            $errors[] = "Invalid email or password.";
        }

        $stmt->close();
    }
}

// Close the connection
$mysqli->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="img/Followers.png">
    <title>Rhine Lab</title>
</head>
<body>
    <div class="container" id="container">
        <div class="form-container sign-up">
            <form method="POST">
                <h1>Create Account</h1>
                <br>
                <input type="text" name="name" placeholder="Name" autocomplete="off" required>
                <input type="email" name="email" placeholder="Email" autocomplete="off" required>
                <input type="password" name="password" placeholder="Password" autocomplete="off" required>
                <br>
                <button type="submit" name="signup">Sign Up</button>
            </form>
        </div>
        <div class="form-container sign-in">
            <form method="POST">
                <h1>Sign In</h1>       
                <br>        
                <input type="email" name="email" placeholder="Email" autocomplete="off" required>
                <input type="password" name="password" placeholder="Password" autocomplete="off" required>
                <br>
                <button type="submit" name="signin">Sign In</button>
                <div class="text-center mt-3">
                    <a href="reset_password.php" class="text-muted">Forgot Password?</a>
                </div>
            </form>
        </div>
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <img src="img/Followers.png" alt="">
                    <button class="hidden" id="login">Sign In</button>
                </div>  
                <div class="toggle-panel toggle-right">
                    <img src="img/Followers.png" alt="">
                    <button class="hidden" id="register">Sign Up</button>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($errors)): ?>
    <script>
        let errorMessage = "<?php echo implode('\n', $errors); ?>";
        alert(errorMessage);
    </script>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <script>
        alert("<?php echo $_SESSION['success']; ?>");
    </script>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>


    <script>
        const container = document.getElementById('container');
        const registerBtn = document.getElementById('register');
        const loginBtn = document.getElementById('login');

        registerBtn.addEventListener('click', () => {
            container.classList.add("active");
        });

        loginBtn.addEventListener('click', () => {
            container.classList.remove("active");
        });
    </script>
</body>
</html>
