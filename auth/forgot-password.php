<?php
require_once '../config/db.php';

$step = 1;
$error = '';
$success = '';
$email = '';
$security_question = '';
$user_id = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['check_email'])) {
        $email = $_POST['email'];
        $stmt = $pdo->prepare("SELECT id, security_question, role FROM users WHERE email = ? AND role != 'admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if($user) {
            $user_id = $user['id'];
            $security_question = $user['security_question'];
            $step = 2;
        } else {
            $error = 'Email not found or admin account cannot reset password';
        }
    } elseif(isset($_POST['verify_answer'])) {
        $user_id = $_POST['user_id'];
        $answer = $_POST['security_answer'];
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND security_answer = ?");
        $stmt->execute([$user_id, $answer]);
        if($stmt->fetch()) {
            $step = 3;
        } else {
            $error = 'Incorrect security answer';
        }
    } elseif(isset($_POST['reset_password'])) {
        $user_id = $_POST['user_id'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password, $user_id]);
        $success = 'Password reset successfully! <a href="login.php">Login here</a>';
        $step = 4;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="auth-container">
        <div class="auth-card">
            <h2>Reset Password</h2>
            <?php if($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($step == 1): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required>
                    </div>
                    <button type="submit" name="check_email" class="btn-primary btn-block">Continue</button>
                </form>
            <?php elseif($step == 2): ?>
                <form method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <div class="form-group">
                        <label>Security Question</label>
                        <input type="text" value="<?php echo htmlspecialchars($security_question); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Your Answer</label>
                        <input type="text" name="security_answer" required>
                    </div>
                    <button type="submit" name="verify_answer" class="btn-primary btn-block">Verify</button>
                </form>
            <?php elseif($step == 3): ?>
                <form method="POST">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn-primary btn-block">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>