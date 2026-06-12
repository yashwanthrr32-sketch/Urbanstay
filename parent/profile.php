<?php
require_once '../config/db.php';
$required_role = 'parent';
include '../includes/session-check.php';

$parent_id = $_SESSION['user_id'];

// Get parent info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'parent'");
$stmt->execute([$parent_id]);
$parent = $stmt->fetch();

if(!$parent) {
    header('Location: dashboard.php');
    exit;
}

// Get documents
$stmt = $pdo->prepare("SELECT * FROM user_documents WHERE user_id = ? AND user_role = 'parent'");
$stmt->execute([$parent_id]);
$documents = $stmt->fetchAll();

// Get linked children
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.phone 
    FROM parent_tenant pt
    JOIN users u ON pt.tenant_id = u.id
    WHERE pt.parent_id = ?
");
$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();

$message = '';

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $parent_id]);
        $_SESSION['user_name'] = $name;
        $message = '<div class="success-message">Profile updated successfully!</div>';
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$parent_id]);
        $parent = $stmt->fetch();
    }
    
    if(isset($_POST['update_photo'])) {
        if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $upload_dir = '../assets/images/uploads/documents/';
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
            if(in_array($_FILES['profile_photo']['type'], $allowed)) {
                if($parent['profile_photo'] && file_exists('../' . $parent['profile_photo'])) {
                    unlink('../' . $parent['profile_photo']);
                }
                
                $filename = 'parent_' . $parent_id . '_profile_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['profile_photo']['name']);
                $filepath = $upload_dir . $filename;
                
                if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $filepath)) {
                    $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    $stmt->execute(['assets/images/uploads/documents/' . $filename, $parent_id]);
                    $message = '<div class="success-message">Profile photo updated!</div>';
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$parent_id]);
                    $parent = $stmt->fetch();
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
        } elseif(password_verify($current, $parent['password'])) {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $parent_id]);
            $message = '<div class="success-message">Password changed!</div>';
        } else {
            $message = '<div class="error-message">Current password is incorrect</div>';
        }
    }
}

$profile_photo = $parent['profile_photo'] ?? null;
$profile_photo_path = $profile_photo ? '../' . $profile_photo : null;
$has_photo = $profile_photo_path && file_exists($profile_photo_path);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Parent</title>
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
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
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
        .children-list { margin-top: 15px; }
        .child-item { padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e0e0e0; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="children.php">My Children</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="payment.php">Rent & Payment</a></li>
                <li><a href="profile.php" class="active">Profile</a></li>
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
                                <?php echo strtoupper(substr($parent['name'], 0, 1)); ?>
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
                            <input type="text" name="name" value="<?php echo htmlspecialchars($parent['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" value="<?php echo htmlspecialchars($parent['email']); ?>" class="readonly-field" readonly disabled>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($parent['phone']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <!-- Linked Children Section -->
                <div class="profile-section">
                    <h2>👪 Linked Children</h2>
                    <?php if(count($children) > 0): ?>
                        <div class="children-list">
                            <?php foreach($children as $child): ?>
                                <div class="child-item">
                                    <strong><?php echo htmlspecialchars($child['name']); ?></strong><br>
                                    <small>Email: <?php echo htmlspecialchars($child['email']); ?></small><br>
                                    <small>Phone: <?php echo htmlspecialchars($child['phone']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No children linked yet. Please contact the PG Manager to link your child.</p>
                    <?php endif; ?>
                </div>
                
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