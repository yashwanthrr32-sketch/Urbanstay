<?php
require_once '../config/db.php';
$required_role = 'parent';
include '../includes/session-check.php';

$parent_id = $_SESSION['user_id'];
$child_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify child is linked to this parent
$stmt = $pdo->prepare("
    SELECT tenant_id FROM parent_tenant WHERE parent_id = ? AND tenant_id = ?
");
$stmt->execute([$parent_id, $child_id]);
if(!$stmt->fetch()) {
    header('Location: children.php');
    exit;
}

// Get child complete details
$stmt = $pdo->prepare("
    SELECT 
        u.id as child_id,
        u.name as child_name,
        u.email as child_email,
        u.phone as child_phone,
        u.created_at as child_registered_date,
        b.id as booking_id,
        b.status as booking_status,
        b.requested_at as booking_date,
        b.personal_info_json,
        pg.id as pg_id,
        pg.name as pg_name,
        pg.address as pg_address,
        pg.type as pg_type,
        pg.price_per_month,
        pg.amenities,
        r.room_number,
        bed.bed_label,
        td.rent_amount,
        td.due_date,
        td.move_in_date,
        (SELECT COUNT(*) FROM attendance WHERE tenant_id = u.id AND status = 'present' AND MONTH(date) = MONTH(CURDATE())) as present_days,
        (SELECT COUNT(*) FROM attendance WHERE tenant_id = u.id AND MONTH(date) = MONTH(CURDATE())) as total_days
    FROM users u
    LEFT JOIN bookings b ON u.id = b.tenant_id AND b.status IN ('processing', 'confirmed')
    LEFT JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN beds bed ON b.bed_id = bed.id
    LEFT JOIN rooms r ON bed.room_id = r.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    WHERE u.id = ?
");
$stmt->execute([$child_id]);
$child = $stmt->fetch();

if(!$child) {
    header('Location: children.php');
    exit;
}

// Parse personal_info_json
$personal_info = [];
if($child['personal_info_json']) {
    $personal_info = json_decode($child['personal_info_json'], true);
}

// Get payment history
$stmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE tenant_id = ? 
    ORDER BY payment_date DESC LIMIT 10
");
$stmt->execute([$child_id]);
$payments = $stmt->fetchAll();

// Get complaint history
$stmt = $pdo->prepare("
    SELECT * FROM complaints 
    WHERE tenant_id = ? 
    ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$child_id]);
$complaints = $stmt->fetchAll();

$attendance_percent = $child['total_days'] > 0 ? round(($child['present_days'] / $child['total_days']) * 100) : 0;

// Get profile photo path
$profile_photo = !empty($personal_info['profile_photo']) ? $personal_info['profile_photo'] : null;
$profile_photo_path = $profile_photo ? '../' . $profile_photo : null;
$has_photo = $profile_photo_path && file_exists($profile_photo_path);

// Parse amenities
$amenities_list = [];
if($child['amenities']) {
    $amenities_list = explode(',', $child['amenities']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($child['child_name']); ?> - Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .details-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
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
            font-weight: bold;
            color: white;
            border: 3px solid #185FA5;
        }
        .header-info h1 {
            color: #185FA5;
            margin-bottom: 5px;
        }
        .header-info p {
            color: #666;
            margin: 5px 0;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-processing { background: #fff3cd; color: #856404; }
        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section h3 {
            color: #185FA5;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #185FA5;
        }
        .detail-row {
            margin-bottom: 12px;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 160px;
            color: #185FA5;
        }
        .detail-value {
            display: inline-block;
            color: #333;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #185FA5;
        }
        .stat-card .label {
            font-size: 12px;
            color: #666;
        }
        .attendance-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 10px;
            margin-top: 10px;
            overflow: hidden;
        }
        .attendance-fill {
            background: #28a745;
            height: 100%;
            border-radius: 10px;
            width: 0%;
        }
        .amenity-tag {
            display: inline-block;
            background: #e0e7ff;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            margin: 3px;
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
            margin-top: 10px;
        }
        .back-btn:hover {
            background: #0d3b66;
        }
        .btn-primary {
            background: #185FA5;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover {
            background: #0d3b66;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #185FA5;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }
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
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="details-container">
                <!-- Header with Profile Photo -->
                <div class="header">
                    <?php if($has_photo): ?>
                        <img src="<?php echo $profile_photo_path; ?>" class="profile-photo" alt="Profile Photo" onerror="this.src='../assets/images/default-avatar.jpg'">
                    <?php else: ?>
                        <div class="profile-placeholder">
                            <?php echo strtoupper(substr($child['child_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="header-info">
                        <h1><?php echo htmlspecialchars($personal_info['full_name'] ?? $child['child_name']); ?></h1>
                        <p><?php echo htmlspecialchars($child['child_email']); ?></p>
                        <p><?php echo htmlspecialchars($child['child_phone']); ?></p>
                        <?php if($child['booking_status']): ?>
                            <span class="status-badge status-<?php echo $child['booking_status']; ?>">
                                <?php echo ucfirst($child['booking_status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo $attendance_percent; ?>%</div>
                        <div class="label">Attendance Rate</div>
                        <div class="attendance-bar">
                            <div class="attendance-fill" style="width: <?php echo $attendance_percent; ?>%;"></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $child['present_days']; ?>/<?php echo $child['total_days']; ?></div>
                        <div class="label">Present / Total Days</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">₹<?php echo number_format($child['rent_amount'] ?? $child['price_per_month']); ?></div>
                        <div class="label">Monthly Rent</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo count($payments); ?></div>
                        <div class="label">Payments Made</div>
                    </div>
                </div>
                
                <!-- Personal Information Section -->
                <div class="section">
                    <h3>📋 Personal Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Full Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($personal_info['full_name'] ?? $child['child_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email Address:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($child['child_email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($personal_info['phone'] ?? $child['child_phone']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Registered On:</span>
                        <span class="detail-value"><?php echo date('d M Y', strtotime($child['child_registered_date'])); ?></span>
                    </div>
                </div>
                
                <!-- Identification Details -->
                <div class="section">
    <h3>🆔 Government ID Proof</h3>
    <div class="detail-row">
        <span class="detail-label">ID Document:</span>
        <span class="detail-value">
            <?php 
            $id_proof_document = isset($personal_info['id_proof_document']) ? $personal_info['id_proof_document'] : null;
            $id_proof_status = isset($personal_info['id_proof_status']) ? $personal_info['id_proof_status'] : 'pending';
            ?>
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
                <!-- Emergency Contact -->
                <div class="section">
                    <h3>🚨 Emergency Contact</h3>
                    <div class="detail-row">
                        <span class="detail-label">Contact Person:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($personal_info['emergency_name'] ?? 'Not provided'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contact Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($personal_info['emergency_phone'] ?? 'Not provided'); ?></span>
                    </div>
                </div>
                
                <!-- PG & Accommodation Details -->
                <?php if($child['pg_name']): ?>
                <div class="section">
                    <h3>🏠 PG & Accommodation</h3>
                    <div class="detail-row">
                        <span class="detail-label">PG Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($child['pg_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">PG Address:</span>
                        <span class="detail-value"><?php echo nl2br(htmlspecialchars($child['pg_address'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">PG Type:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($child['pg_type']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Room/Bed:</span>
                        <span class="detail-value">Room <?php echo $child['room_number']; ?>, Bed <?php echo $child['bed_label']; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Monthly Rent:</span>
                        <span class="detail-value">₹<?php echo number_format($child['rent_amount'] ?? $child['price_per_month']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Due Date:</span>
                        <span class="detail-value"><?php echo $child['due_date'] ?? 'End of month'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Move-in Date:</span>
                        <span class="detail-value"><?php echo $child['move_in_date'] ?? 'Not set'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Booking Date:</span>
                        <span class="detail-value"><?php echo date('d M Y', strtotime($child['booking_date'])); ?></span>
                    </div>
                    <?php if(!empty($amenities_list)): ?>
                        <div class="detail-row">
                            <span class="detail-label">Amenities:</span>
                            <span class="detail-value">
                                <?php foreach($amenities_list as $amenity): ?>
                                    <?php if(trim($amenity)): ?>
                                        <span class="amenity-tag">✓ <?php echo htmlspecialchars(trim($amenity)); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Payment History -->
                <?php if(count($payments) > 0): ?>
                <div class="section">
                    <h3>💰 Payment History</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                    <td>₹<?php echo number_format($payment['amount']); ?></td>
                                    <td><?php echo ucfirst($payment['payment_type']); ?></td>
                                    <td>
                                        <?php if($payment['status'] == 'verified'): ?>
                                            <span style="color: green;">✓ Verified</span>
                                        <?php elseif($payment['status'] == 'pending'): ?>
                                            <span style="color: orange;">⏳ Pending</span>
                                        <?php else: ?>
                                            <span style="color: red;">✗ Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- Complaint History -->
                <?php if(count($complaints) > 0): ?>
                <div class="section">
                    <h3>📋 Complaint History</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($complaints as $complaint): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['category']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($complaint['description'], 0, 50)); ?>...</td>
                                    <td>
                                        <?php if($complaint['status'] == 'resolved'): ?>
                                            <span style="color: green;">✓ Resolved</span>
                                        <?php else: ?>
                                            <span style="color: orange;">⏳ Open</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="attendance.php?child_id=<?php echo $child['child_id']; ?>" class="btn-primary">📅 View Full Attendance</a>
                    <a href="payment.php?child_id=<?php echo $child['child_id']; ?>" class="btn-primary">💰 Pay Rent</a>
                    <a href="children.php" class="back-btn">← Back to Children</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>