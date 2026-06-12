<?php
require_once '../config/db.php';
$required_role = 'tenant';
include '../includes/session-check.php';

$tenant_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$tenant_id]);
$user = $stmt->fetch();

// Get booking info with personal details
$stmt = $pdo->prepare("
    SELECT b.*, pg.name as pg_name, r.room_number, bed.bed_label
    FROM bookings b
    LEFT JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN beds bed ON b.bed_id = bed.id
    LEFT JOIN rooms r ON bed.room_id = r.id
    WHERE b.tenant_id = ? AND b.status IN ('processing', 'confirmed')
    ORDER BY b.requested_at DESC LIMIT 1
");
$stmt->execute([$tenant_id]);
$booking = $stmt->fetch();

$personal_info = [];
if($booking && $booking['personal_info_json']) {
    $personal_info = json_decode($booking['personal_info_json'], true);
}

$message = '';

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        
        if(empty($name) || empty($phone)) {
            $message = '<div class="error-message">Name and phone are required</div>';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $tenant_id]);
            $_SESSION['user_name'] = $name;
            $message = '<div class="success-message">Profile updated successfully!</div>';
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$tenant_id]);
            $user = $stmt->fetch();
        }
    }
    
    if(isset($_POST['update_photo'])) {
        if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $upload_dir = '../assets/images/uploads/tenant_documents/';
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
            if(in_array($_FILES['profile_photo']['type'], $allowed)) {
                // Delete old photo if exists
                if($user['profile_photo'] && file_exists('../' . $user['profile_photo'])) {
                    unlink('../' . $user['profile_photo']);
                }
                
                $filename = 'tenant_' . $tenant_id . '_profile_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['profile_photo']['name']);
                $filepath = $upload_dir . $filename;
                
                if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $filepath)) {
                    $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    $stmt->execute(['assets/images/uploads/tenant_documents/' . $filename, $tenant_id]);
                    $message = '<div class="success-message">Profile photo updated successfully!</div>';
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$tenant_id]);
                    $user = $stmt->fetch();
                } else {
                    $message = '<div class="error-message">Failed to upload photo</div>';
                }
            } else {
                $message = '<div class="error-message">Only JPG, PNG images are allowed</div>';
            }
        } else {
            $message = '<div class="error-message">Please select a photo to upload</div>';
        }
    }
    
    if(isset($_POST['update_booking_info']) && $booking) {
        $updated_info = [
            'full_name' => trim($_POST['full_name']),
            'phone' => trim($_POST['phone']),
            'address' => trim($_POST['address']),
            'profile_photo' => $personal_info['profile_photo'] ?? '',
            'id_proof_document' => $personal_info['id_proof_document'] ?? '',
            'id_proof_status' => $personal_info['id_proof_status'] ?? 'pending',
            'emergency_name' => trim($_POST['emergency_name']),
            'emergency_phone' => trim($_POST['emergency_phone'])
        ];
        
        // Handle new profile photo upload for booking
        if(isset($_FILES['booking_photo']) && $_FILES['booking_photo']['error'] == 0) {
            $upload_dir = '../assets/images/uploads/tenant_documents/';
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
            if(in_array($_FILES['booking_photo']['type'], $allowed)) {
                $filename = 'tenant_' . $tenant_id . '_booking_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['booking_photo']['name']);
                $filepath = $upload_dir . $filename;
                if(move_uploaded_file($_FILES['booking_photo']['tmp_name'], $filepath)) {
                    // Delete old photo if exists
                    if(!empty($updated_info['profile_photo']) && file_exists('../' . $updated_info['profile_photo'])) {
                        unlink('../' . $updated_info['profile_photo']);
                    }
                    $updated_info['profile_photo'] = 'assets/images/uploads/tenant_documents/' . $filename;
                }
            }
        }
        
        // Handle new ID proof upload
        if(isset($_FILES['new_id_proof']) && $_FILES['new_id_proof']['error'] == 0) {
            $upload_dir = '../assets/images/uploads/tenant_documents/';
            $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            if(in_array($_FILES['new_id_proof']['type'], $allowed)) {
                $filename = 'tenant_' . $tenant_id . '_idproof_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['new_id_proof']['name']);
                $filepath = $upload_dir . $filename;
                if(move_uploaded_file($_FILES['new_id_proof']['tmp_name'], $filepath)) {
                    // Delete old ID proof if exists
                    if(!empty($updated_info['id_proof_document']) && file_exists('../' . $updated_info['id_proof_document'])) {
                        unlink('../' . $updated_info['id_proof_document']);
                    }
                    $updated_info['id_proof_document'] = 'assets/images/uploads/tenant_documents/' . $filename;
                    $updated_info['id_proof_status'] = 'pending';
                    $message = '<div class="success-message">ID proof updated! It will be verified by manager.</div>';
                }
            } else {
                $message = '<div class="error-message">Only JPG, PNG, PDF allowed for ID proof</div>';
            }
        }
        
        $stmt = $pdo->prepare("UPDATE bookings SET personal_info_json = ? WHERE id = ?");
        $stmt->execute([json_encode($updated_info), $booking['id']]);
        
        if(empty($message)) {
            $message = '<div class="success-message">Booking information updated successfully!</div>';
        }
        
        // Refresh personal info
        $personal_info = $updated_info;
    }
    
    if(isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if(empty($current) || empty($new) || empty($confirm)) {
            $message = '<div class="error-message">All password fields are required</div>';
        } elseif($new !== $confirm) {
            $message = '<div class="error-message">New passwords do not match</div>';
        } elseif(strlen($new) < 4) {
            $message = '<div class="error-message">Password must be at least 4 characters</div>';
        } elseif(password_verify($current, $user['password'])) {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $tenant_id]);
            $message = '<div class="success-message">Password changed successfully!</div>';
        } else {
            $message = '<div class="error-message">Current password is incorrect</div>';
        }
    }
}

// Get profile photo path
$profile_photo = $user['profile_photo'] ?? null;
$profile_photo_path = $profile_photo ? '../' . $profile_photo : null;
$has_photo = $profile_photo_path && file_exists($profile_photo_path);

// Get ID proof document
$id_proof_document = isset($personal_info['id_proof_document']) ? $personal_info['id_proof_document'] : null;
$id_proof_status = isset($personal_info['id_proof_status']) ? $personal_info['id_proof_status'] : 'pending';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Tenant</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .profile-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-section h2 {
            color: #185FA5;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #185FA5;
        }
        .photo-section {
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #185FA5;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .profile-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #185FA5 0%, #0d3b66 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            font-weight: bold;
            color: white;
            border: 3px solid #185FA5;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .form-group input, 
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn-primary {
            background: #185FA5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #0d3b66;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .readonly-field {
            background: #e9ecef;
            cursor: not-allowed;
        }
        .info-row {
            margin-bottom: 12px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 160px;
            color: #185FA5;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-verified { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .document-link {
            background: #185FA5;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 5px;
        }
        .document-link:hover {
            background: #0d3b66;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="booking.php">My Booking</a></li>
                <li><a href="payment.php">Rent & Payment</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="feedback.php">Feedback</a></li>
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
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
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
                
                <!-- Personal Information Section -->
                <div class="profile-section">
                    <h2>Personal Information</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="readonly-field" readonly disabled>
                            <small>Email cannot be changed</small>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <!-- Booking Information Section (only if has active booking) -->
                <?php if($booking && $personal_info): ?>
                <div class="profile-section">
                    <h2>Booking Information</h2>
                    
                    <div class="info-row">
                        <span class="info-label">PG Name:</span>
                        <?php echo htmlspecialchars($booking['pg_name'] ?? 'N/A'); ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Room/Bed:</span>
                        Room <?php echo $booking['room_number'] ?? 'N/A'; ?>, Bed <?php echo $booking['bed_label'] ?? 'N/A'; ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Booking Status:</span>
                        <?php echo ucfirst($booking['status'] ?? 'N/A'); ?>
                    </div>
                    
                    <hr>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Full Name (as per ID proof)</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($personal_info['full_name'] ?? $user['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($personal_info['phone'] ?? $user['phone']); ?>" required>
                        </div>
                        
                        <!-- Government ID Proof Section -->
                        <div class="form-group">
                            <label>Government ID Proof</label>
                            <?php if($id_proof_document && file_exists('../' . $id_proof_document)): ?>
                                <div style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                    <p><strong>Current ID Proof:</strong></p>
                                    <a href="../<?php echo $id_proof_document; ?>" target="_blank" class="document-link">📄 View Uploaded ID Proof</a>
                                    <br>
                                    <span class="status-badge status-<?php echo $id_proof_status; ?>" style="margin-top: 5px; display: inline-block;">
                                        Status: <?php echo ucfirst($id_proof_status); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom: 10px; padding: 10px; background: #fff3cd; border-radius: 5px;">
                                    <p>No ID proof uploaded yet.</p>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="new_id_proof" accept="image/jpeg,image/png,image/jpg,application/pdf">
                            <small>Upload new ID proof (JPG, PNG, PDF) - Max 5MB. Uploading new document will reset verification status.</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Current Address</label>
                            <textarea name="address" rows="3" required><?php echo htmlspecialchars($personal_info['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Booking Profile Photo</label>
                            <?php if(!empty($personal_info['profile_photo']) && file_exists('../' . $personal_info['profile_photo'])): ?>
                                <div style="margin-bottom: 10px;">
                                    <img src="../<?php echo $personal_info['profile_photo']; ?>" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="booking_photo" accept="image/jpeg,image/png,image/jpg">
                            <small>Leave empty to keep current photo</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Emergency Contact Name</label>
                            <input type="text" name="emergency_name" value="<?php echo htmlspecialchars($personal_info['emergency_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Emergency Contact Phone</label>
                            <input type="tel" name="emergency_phone" value="<?php echo htmlspecialchars($personal_info['emergency_phone'] ?? ''); ?>" required>
                        </div>
                        
                        <button type="submit" name="update_booking_info" class="btn-primary">Update Booking Information</button>
                    </form>
                </div>
                <?php else: ?>
                <div class="profile-section">
                    <h2>Booking Information</h2>
                    <p style="color: #666;">You don't have an active booking yet. <a href="../index.php">Browse PGs</a> to make a booking.</p>
                </div>
                <?php endif; ?>
                
                <!-- Change Password Section -->
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