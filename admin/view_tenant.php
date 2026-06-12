<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

$tenant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($tenant_id <= 0) {
    header('Location: tenants.php');
    exit;
}

// Get tenant complete details
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name as registered_name,
        u.email,
        u.phone as registered_phone,
        u.created_at as registered_date,
        u.status as account_status,
        u.profile_photo,
        b.id as booking_id,
        b.status as booking_status,
        b.requested_at as booking_date,
        b.approved_at as vacated_date,
        b.personal_info_json,
        pg.id as pg_id,
        pg.name as pg_name,
        pg.address as pg_address,
        pg.type as pg_type,
        pg.price_per_month,
        r.room_number,
        bed.bed_label,
        td.rent_amount,
        td.due_date,
        td.move_in_date
    FROM users u
    LEFT JOIN bookings b ON u.id = b.tenant_id
    LEFT JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN beds bed ON b.bed_id = bed.id
    LEFT JOIN rooms r ON bed.room_id = r.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    WHERE u.id = ? AND u.role = 'tenant'
    ORDER BY b.requested_at DESC
");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

if(!$tenant) {
    header('Location: tenants.php');
    exit;
}

// Parse personal_info_json
$personal_info = [];
if($tenant['personal_info_json']) {
    $personal_info = json_decode($tenant['personal_info_json'], true);
}

// Get parent details
$stmt = $pdo->prepare("
    SELECT parent.name, parent.email, parent.phone
    FROM parent_tenant pt
    JOIN users parent ON pt.parent_id = parent.id
    WHERE pt.tenant_id = ?
");
$stmt->execute([$tenant_id]);
$parent = $stmt->fetch();

// Get profile photo path
$profile_photo = $tenant['profile_photo'] ?? null;
$profile_photo_path = $profile_photo ? '../' . $profile_photo : null;
$has_photo = $profile_photo_path && file_exists($profile_photo_path);

// Get ID proof document from booking
$id_proof_document = isset($personal_info['id_proof_document']) ? $personal_info['id_proof_document'] : null;
$id_proof_status = isset($personal_info['id_proof_status']) ? $personal_info['id_proof_status'] : 'pending';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Details - <?php echo htmlspecialchars($tenant['registered_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .details-container {
            max-width: 1000px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #185FA5;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #185FA5;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .profile-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #185FA5 0%, #0d3b66 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            border: 3px solid #185FA5;
        }
        .header-info h1 {
            color: #185FA5;
            margin-bottom: 5px;
        }
        .section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section h3 {
            color: #185FA5;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .detail-row {
            margin-bottom: 12px;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 180px;
            color: #185FA5;
        }
        .detail-value {
            display: inline-block;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-verified { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .document-link {
            background: #185FA5;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .document-link:hover {
            background: #0d3b66;
        }
        .back-btn {
            background: #185FA5;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="managers.php">PG Managers</a></li>
                <li><a href="parents.php">Parents</a></li>
                <li><a href="tenants.php" class="active">All Tenants</a></li>
                <li><a href="pg-listings.php">PG Listings</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="payments.php">Payments</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="details-container">
                <div class="header">
                    <?php if($has_photo): ?>
                        <img src="<?php echo $profile_photo_path; ?>" class="profile-photo" alt="Profile Photo">
                    <?php else: ?>
                        <div class="profile-placeholder">
                            <?php echo strtoupper(substr($tenant['registered_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="header-info">
                        <h1><?php echo htmlspecialchars($personal_info['full_name'] ?? $tenant['registered_name']); ?></h1>
                        <p><?php echo htmlspecialchars($tenant['email']); ?></p>
                        <span class="status-badge status-<?php echo $tenant['account_status']; ?>">
                            <?php echo strtoupper($tenant['account_status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="section">
                    <h3>📋 Personal Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Full Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($personal_info['full_name'] ?? $tenant['registered_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($tenant['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($personal_info['phone'] ?? $tenant['registered_phone']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Registered On:</span>
                        <span class="detail-value"><?php echo date('d M Y, h:i A', strtotime($tenant['registered_date'])); ?></span>
                    </div>
                </div>
                
                <div class="section">
                    <h3>🆔 Government ID Proof</h3>
                    <div class="detail-row">
                        <span class="detail-label">ID Document:</span>
                        <span class="detail-value">
                            <?php if($id_proof_document && file_exists('../' . $id_proof_document)): ?>
                                <a href="../<?php echo $id_proof_document; ?>" target="_blank" class="document-link">📄 View Uploaded ID Proof</a>
                                <br>
                                <span class="status-badge status-<?php echo $id_proof_status; ?>">
                                    Status: <?php echo ucfirst($id_proof_status); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">No ID proof document uploaded</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div class="section">
                    <h3>📍 Address Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Current Address:</span>
                        <span class="detail-value"><?php echo nl2br(htmlspecialchars($personal_info['address'] ?? 'Not provided')); ?></span>
                    </div>
                </div>
                
                <div class="section">
                    <h3>🚨 Emergency Contact</h3>
                    <div class="detail-row">
                        <span class="detail-label">Contact Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($personal_info['emergency_name'] ?? 'Not provided'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contact Phone:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($personal_info['emergency_phone'] ?? 'Not provided'); ?></span>
                    </div>
                </div>
                
                <div class="section">
                    <h3>🏠 Accommodation Details</h3>
                    <?php if($tenant['pg_name']): ?>
                        <div class="detail-row">
                            <span class="detail-label">PG Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($tenant['pg_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">PG Address:</span>
                            <span class="detail-value"><?php echo nl2br(htmlspecialchars($tenant['pg_address'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Room/Bed:</span>
                            <span class="detail-value">Room <?php echo $tenant['room_number']; ?>, Bed <?php echo $tenant['bed_label']; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Monthly Rent:</span>
                            <span class="detail-value">₹<?php echo number_format($tenant['rent_amount'] ?? $tenant['price_per_month']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Due Date:</span>
                            <span class="detail-value"><?php echo $tenant['due_date'] ?? 'Not set'; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Move-in Date:</span>
                            <span class="detail-value"><?php echo $tenant['move_in_date'] ?? 'Not set'; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Booking Status:</span>
                            <span class="detail-value">
                                <span class="status-badge status-<?php echo $tenant['booking_status']; ?>">
                                    <?php echo ucfirst($tenant['booking_status']); ?>
                                </span>
                            </span>
                        </div>
                    <?php else: ?>
                        <p style="color: #999;">No active booking found.</p>
                    <?php endif; ?>
                </div>
                
                <?php if($parent): ?>
                <div class="section">
                    <h3>👪 Parent Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Parent Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($parent['name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Parent Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($parent['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Parent Phone:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($parent['phone']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="tenants.php" class="back-btn">← Back to Tenants List</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>