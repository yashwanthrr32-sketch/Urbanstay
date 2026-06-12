<?php
require_once '../config/db.php';

if(isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if($role == 'admin') header('Location: ../admin/dashboard.php');
    elseif($role == 'manager') header('Location: ../manager/dashboard.php');
    elseif($role == 'tenant') header('Location: ../tenant/dashboard.php');
    elseif($role == 'parent') header('Location: ../parent/dashboard.php');
    exit;
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Debug - check if email exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if($user) {
        // Verify password
        if(password_verify($password, $user['password'])) {
            if($user['role'] == 'manager' && $user['status'] != 'active') {
                $error = 'Your account is pending admin approval.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                if($user['role'] == 'admin') header('Location: ../admin/dashboard.php');
                elseif($user['role'] == 'manager') header('Location: ../manager/dashboard.php');
                elseif($user['role'] == 'tenant') header('Location: ../tenant/dashboard.php');
                elseif($user['role'] == 'parent') header('Location: ../parent/dashboard.php');
                exit;
            }
        } else {
            $error = 'Invalid email or password';
        }
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="auth-container">
        <div class="auth-card">
            <h2>Login to Urban Stay</h2>
            <?php if($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn-primary btn-block">Login</button>
                <p class="auth-link"><a href="forgot-password.php">Forgot Password?</a></p>
                <p class="auth-link">Don't have an account? <a href="register.php">Register here</a></p>
            </form>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>