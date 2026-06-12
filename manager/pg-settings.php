<?php
require_once '../config/db.php';
$required_role = 'manager';
include '../includes/session-check.php';

$manager_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM pgs WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$pg = $stmt->fetch();

if(!$pg) {
    echo "<div class='container'><h1>No PG found. Please contact admin.</h1></div>";
    exit;
}

$message = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_settings'])) {
        $name = $_POST['name'];
        $address = $_POST['address'];
        $contact_phone = $_POST['contact_phone'];
        $contact_email = $_POST['contact_email'];
        $type = $_POST['type'];
        $price = $_POST['price_per_month'];
        
        // Handle amenities - check if it exists and is an array
        $amenities = isset($_POST['amenities']) && is_array($_POST['amenities']) ? $_POST['amenities'] : [];
        $amenities_str = !empty($amenities) ? implode(',', $amenities) : '';
        
        $upi_id = $_POST['upi_id'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE pgs SET name = ?, address = ?, contact_phone = ?, contact_email = ?, type = ?, price_per_month = ?, amenities = ?, upi_id = ? WHERE id = ?");
        $stmt->execute([$name, $address, $contact_phone, $contact_email, $type, $price, $amenities_str, $upi_id, $pg['id']]);
        
        // Handle QR code upload
        if(isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] == 0) {
            $upload_dir = '../assets/images/uploads/';
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $file_type = $_FILES['qr_code']['type'];
            
            if(in_array($file_type, $allowed)) {
                // Delete old QR code if exists
                if($pg['qr_code_image'] && file_exists('../' . $pg['qr_code_image'])) {
                    unlink('../' . $pg['qr_code_image']);
                }
                
                $filename = 'qr_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['qr_code']['name']);
                $filepath = $upload_dir . $filename;
                
                if(move_uploaded_file($_FILES['qr_code']['tmp_name'], $filepath)) {
                    $stmt = $pdo->prepare("UPDATE pgs SET qr_code_image = ? WHERE id = ?");
                    $stmt->execute(['assets/images/uploads/' . $filename, $pg['id']]);
                    $message = '<div class="success-message">PG settings and QR code updated successfully!</div>';
                } else {
                    $message = '<div class="error-message">Failed to upload QR code</div>';
                }
            } else {
                $message = '<div class="error-message">Only JPG, PNG, and GIF images are allowed for QR code</div>';
            }
        } else {
            $message = '<div class="success-message">PG settings updated successfully!</div>';
        }
        
        // Refresh PG data
        $stmt = $pdo->prepare("SELECT * FROM pgs WHERE id = ?");
        $stmt->execute([$pg['id']]);
        $pg = $stmt->fetch();
    }
    
    if(isset($_POST['delete_pg'])) {
        // Delete all related images first
        $stmt = $pdo->prepare("SELECT image_path FROM pg_images WHERE pg_id = ?");
        $stmt->execute([$pg['id']]);
        $images = $stmt->fetchAll();
        foreach($images as $image) {
            if(file_exists('../' . $image['image_path'])) {
                unlink('../' . $image['image_path']);
            }
        }
        
        // Delete PG
        $stmt = $pdo->prepare("DELETE FROM pgs WHERE id = ? AND manager_id = ?");
        $stmt->execute([$pg['id'], $manager_id]);
        
        header('Location: dashboard.php?deleted=1');
        exit;
    }
}

// Parse current amenities
$currentAmenities = [];
if(!empty($pg['amenities'])) {
    $currentAmenities = explode(',', $pg['amenities']);
}
$allAmenities = ['WiFi', 'Food/Mess', 'Laundry', 'Parking', 'AC/Non-AC', 'Attached Bathroom'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PG Settings - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input, 
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #185FA5;
        }
        .amenities-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .amenities-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            margin-bottom: 0;
        }
        .btn-primary {
            background: #185FA5;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-left: 10px;
        }
        .btn-primary:hover {
            background: #0d3b66;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .current-qr {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: center;
        }
        .current-qr img {
            max-width: 150px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
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
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        .contact-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 768px) {
            .contact-row {
                grid-template-columns: 1fr;
            }
        }
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
                <li><a href="pg-settings.php" class="active">PG Settings</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="settings-container">
                <h1>PG Settings</h1>
                <p>Manage your PG accommodation settings</p>
                
                <?php echo $message; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>PG Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($pg['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Address *</label>
                        <textarea name="address" rows="3" required><?php echo htmlspecialchars($pg['address']); ?></textarea>
                    </div>
                    
                    <!-- Contact Information Section -->
                    <div class="contact-row">
                        <div class="form-group">
                            <label>Contact Phone Number</label>
                            <input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($pg['contact_phone'] ?? ''); ?>" placeholder="e.g., +91 9876543210">
                            <small>This number will be displayed to tenants for inquiries</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" name="contact_email" value="<?php echo htmlspecialchars($pg['contact_email'] ?? ''); ?>" placeholder="e.g., contact@pgname.com">
                            <small>This email will be displayed to tenants for inquiries</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>PG Type *</label>
                        <select name="type" required>
                            <option value="Male" <?php echo $pg['type'] == 'Male' ? 'selected' : ''; ?>>Male Only</option>
                            <option value="Female" <?php echo $pg['type'] == 'Female' ? 'selected' : ''; ?>>Female Only</option>
                            <option value="Both" <?php echo $pg['type'] == 'Both' ? 'selected' : ''; ?>>Both (Separate Floors)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Price per Month (₹) *</label>
                        <input type="number" name="price_per_month" value="<?php echo $pg['price_per_month']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Amenities</label>
                        <div class="amenities-group">
                            <?php foreach($allAmenities as $amenity): ?>
                                <label>
                                    <input type="checkbox" name="amenities[]" value="<?php echo $amenity; ?>"
                                        <?php echo in_array($amenity, $currentAmenities) ? 'checked' : ''; ?>>
                                    <?php echo $amenity; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>UPI ID (for online payments)</label>
                        <input type="text" name="upi_id" value="<?php echo htmlspecialchars($pg['upi_id'] ?? ''); ?>" placeholder="example@upi">
                        <small>Leave empty if not using UPI payments</small>
                    </div>
                    
                    <div class="form-group">
                        <label>UPI QR Code Image</label>
                        <?php if(!empty($pg['qr_code_image']) && file_exists('../' . $pg['qr_code_image'])): ?>
                            <div class="current-qr">
                                <p>Current QR Code:</p>
                                <img src="../<?php echo $pg['qr_code_image']; ?>" alt="Current QR Code">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="qr_code" accept="image/jpeg,image/png,image/jpg,image/gif">
                        <small>Leave empty to keep current QR code. Allowed: JPG, PNG, GIF</small>
                    </div>
                    
                    <hr>
                    
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="update_settings" class="btn-primary">Update Settings</button>
                        <button type="submit" name="delete_pg" class="btn-danger" onclick="return confirm('Are you sure you want to delete your PG? This action cannot be undone and will delete all associated data (rooms, beds, bookings, etc.).')">Delete PG</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>