<?php
require_once '../config/db.php';
$required_role = 'manager';
include '../includes/session-check.php';

$manager_id = $_SESSION['user_id'];

// Get manager info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'manager'");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch();

if(!$manager) {
    header('Location: dashboard.php');
    exit;
}

// Get PG info
$stmt = $pdo->prepare("SELECT * FROM pgs WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$pg = $stmt->fetch();

// Get documents
$stmt = $pdo->prepare("SELECT * FROM user_documents WHERE user_id = ? AND user_role = 'manager'");
$stmt->execute([$manager_id]);
$documents = $stmt->fetchAll();

$message = '';

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $manager_id]);
        $_SESSION['user_name'] = $name;
        $message = '<div class="success-message">Profile updated successfully!</div>';
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$manager_id]);
        $manager = $stmt->fetch();
    }
    
    if(isset($_POST['update_photo'])) {
        if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $upload_dir = '../assets/images/uploads/documents/';
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
            if(in_array($_FILES['profile_photo']['type'], $allowed)) {
                if($manager['profile_photo'] && file_exists('../' . $manager['profile_photo'])) {
                    unlink('../' . $manager['profile_photo']);
                }
                
                $filename = 'manager_' . $manager_id . '_profile_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['profile_photo']['name']);
                $filepath = $upload_dir . $filename;
                
                if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $filepath)) {
                    $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    $stmt->execute(['assets/images/uploads/documents/' . $filename, $manager_id]);
                    $message = '<div class="success-message">Profile photo updated!</div>';
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$manager_id]);
                    $manager = $stmt->fetch();
                }
            }
        }
    }
    
    if(isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if($new !== $confirm) {
            $message = '<div class="error-message">Passwords do not match</div>';
        } elseif(strlen($new) < 4) {
            $message = '<div class="error-message">Password must be at least 4 characters</div>';
        } elseif(password_verify($current, $manager['password'])) {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $manager_id]);
            $message = '<div class="success-message">Password changed!</div>';
        } else {
            $message = '<div class="error-message">Current password is incorrect</div>';
        }
    }
}

$profile_photo = $manager['profile_photo'] ?? null;
$profile_photo_path = $profile_photo ? '../' . $profile_photo : null;
$has_photo = $profile_photo_path && file_exists($profile_photo_path);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-container { max-width: 800px; margin: 0 auto; }
        .profile-section { background: white; padding: 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .profile-section h2 { color: #185FA5; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #185FA5; }
        .photo-section { text-align: center; margin-bottom: 20px; }
        .profile-photo { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #185FA5; }
        .profile-placeholder { width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #185FA5 0%, #0d3b66 100%); display: flex; align-items: center; justify-content: center; font-size: 60px; font-weight: bold; color: white; margin: 0 auto; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn-primary { background: #185FA5; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .btn-primary:hover { background: #0d3b66; }
        .success-message { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .error-message { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc3545; }
        .readonly-field { background: #e9ecef; cursor: not-allowed; }
        .document-list { margin-top: 15px; }
        .document-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e0e0e0; }
        .document-info { flex: 1; }
        .document-action { margin-left: 15px; }
        .btn-view { background: #17a2b8; color: white; padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size: 12px; }
        .btn-view:hover { background: #138496; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-verified { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="tenants.php">Tenants</a></li>
                <li><a href="rooms.php">Rooms & Beds</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="pg-images.php">PG Images</a></li>
                <li><a href="pg-settings.php">PG Settings</a></li>
                <li><a href="profile.php" class="active">My Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="profile-container">
                <h1>My Profile</h1>
                <?php echo $message; ?>
                
                <!-- Profile Photo Section -->
                <div class="profile-section">
                    <h2>Profile Photo</h2>
                    <div class="photo-section">
                        <?php if($has_photo): ?>
                            <img src="<?php echo $profile_photo_path; ?>" class="profile-photo" alt="Profile Photo" onerror="this.src='../assets/images/default-avatar.jpg'">
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <?php echo strtoupper(substr($manager['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" style="text-align: center;">
                        <div class="form-group">
                            <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/jpg" required>
                            <small>Allowed: JPG, PNG. Max size: 2MB</small>
                        </div>
                        <button type="submit" name="update_photo" class="btn-primary">Update Photo</button>
                    </form>
                </div>
                
                <!-- Personal Information -->
                <div class="profile-section">
                    <h2>Personal Information</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($manager['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" value="<?php echo htmlspecialchars($manager['email']); ?>" class="readonly-field" readonly disabled>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($manager['phone']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <!-- PG Information -->
                <?php if($pg): ?>
                <div class="profile-section">
                    <h2>PG Information</h2>
                    <div class="form-group">
                        <label>PG Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($pg['name']); ?>" class="readonly-field" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label>PG Address</label>
                        <textarea class="readonly-field" readonly rows="2"><?php echo htmlspecialchars($pg['address']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>PG Status</label>
                        <input type="text" value="<?php echo ucfirst($pg['status']); ?>" class="readonly-field" readonly disabled>
                    </div>
                    <a href="pg-settings.php" class="btn-primary">Edit PG Settings</a>
                </div>
                <?php endif; ?>
                
                <!-- Uploaded Documents Section -->
                <div class="profile-section">
                    <h2>📄 Uploaded Documents</h2>
                    <?php if(count($documents) > 0): ?>
                        <div class="document-list">
                            <?php foreach($documents as $doc): ?>
                                <div class="document-item">
                                    <div class="document-info">
                                        <strong><?php echo htmlspecialchars($doc['document_type']); ?></strong><br>
                                        <small>Uploaded: <?php echo date('d M Y', strtotime($doc['uploaded_at'])); ?></small><br>
                                        <span class="status-badge status-<?php echo $doc['status']; ?>">
                                            Status: <?php echo ucfirst($doc['status']); ?>
                                        </span>
                                    </div>
                                    <div class="document-action">
                                        <a href="../<?php echo $doc['document_path']; ?>" target="_blank" class="btn-view">View Document</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No documents uploaded yet. Documents uploaded during registration will appear here.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Change Password -->
                <div class="profile-section">
                    <h2>Change Password</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                            <small>Minimum 4 characters</small>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>