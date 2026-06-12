<?php
require_once '../config/db.php';
$required_role = 'parent';
include '../includes/session-check.php';

$parent_id = $_SESSION['user_id'];

// Get linked children with details
$stmt = $pdo->prepare("
    SELECT 
        u.id as child_id,
        u.name as child_name,
        u.email as child_email,
        b.id as booking_id,
        b.status as booking_status,
        pg.name as pg_name,
        pg.address as pg_address,
        r.room_number,
        bed.bed_label,
        td.rent_amount,
        td.due_date,
        (SELECT COUNT(*) FROM attendance WHERE tenant_id = u.id AND status = 'present' AND MONTH(date) = MONTH(CURDATE())) as present_days,
        (SELECT COUNT(*) FROM attendance WHERE tenant_id = u.id AND MONTH(date) = MONTH(CURDATE())) as total_days
    FROM parent_tenant pt
    JOIN users u ON pt.tenant_id = u.id
    LEFT JOIN bookings b ON u.id = b.tenant_id AND b.status IN ('processing', 'confirmed')
    LEFT JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN beds bed ON b.bed_id = bed.id
    LEFT JOIN rooms r ON bed.room_id = r.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    WHERE pt.parent_id = ?
");
$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();

// Get notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$stmt->execute([$parent_id]);
$notifications = $stmt->fetchAll();

// Mark notifications as read
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->execute([$parent_id]);

// Check for due rents
$rent_due_soon = false;
foreach($children as $child) {
    if($child['due_date'] && $child['due_date'] <= date('Y-m-d', strtotime('+3 days'))) {
        $rent_due_soon = true;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #185FA5;
        }
        .child-card {
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .child-header {
            background: #185FA5;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .child-header h3 {
            margin: 0;
        }
        .child-body {
            padding: 20px;
        }
        .info-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
            color: #185FA5;
        }
        .info-value {
            flex: 1;
            color: #333;
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
            transition: width 0.3s;
        }
        .alert {
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .btn-view {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        .btn-view:hover {
            background: #138496;
        }
        .no-children {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="children.php">My Children</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="payment.php">Rent & Payment</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
            
            <?php if($rent_due_soon): ?>
                <div class="alert">
                    ⚠️ Rent payment is due soon for one of your children. Please make the payment.
                </div>
            <?php endif; ?>
            
            <?php if(count($notifications) > 0): ?>
                <?php foreach($notifications as $notif): ?>
                    <div class="alert" style="background: #d1ecf1; color: #0c5460; border-left-color: #185FA5;">
                        📢 <?php echo htmlspecialchars($notif['message']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Children Linked</h3>
                    <div class="stat-number"><?php echo count($children); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Bookings</h3>
                    <div class="stat-number">
                        <?php 
                        $active = 0;
                        foreach($children as $child) {
                            if($child['booking_status'] == 'confirmed') $active++;
                        }
                        echo $active;
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Avg Attendance</h3>
                    <div class="stat-number">
                        <?php
                        $total_percent = 0;
                        foreach($children as $child) {
                            if($child['total_days'] > 0) {
                                $total_percent += ($child['present_days'] / $child['total_days']) * 100;
                            }
                        }
                        echo count($children) > 0 ? round($total_percent / count($children)) . '%' : '0%';
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Children List -->
            <h2>👨‍👩‍👧‍👦 My Children</h2>
            
            <?php if(count($children) > 0): ?>
                <?php foreach($children as $child): ?>
                    <div class="child-card">
                        <div class="child-header">
                            <h3><?php echo htmlspecialchars($child['child_name']); ?></h3>
                            <span class="status-badge" style="background: <?php echo $child['booking_status'] == 'confirmed' ? '#28a745' : '#ffc107'; ?>; color: white; padding: 4px 12px; border-radius: 20px;">
                                <?php echo ucfirst($child['booking_status'] ?? 'No Booking'); ?>
                            </span>
                        </div>
                        <div class="child-body">
                            <div class="info-row">
                                <span class="info-label">📧 Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($child['child_email']); ?></span>
                            </div>
                            <?php if($child['pg_name']): ?>
                                <div class="info-row">
                                    <span class="info-label">🏠 PG Name:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($child['pg_name']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">📍 Address:</span>
                                    <span class="info-value"><?php echo htmlspecialchars(substr($child['pg_address'], 0, 80)); ?>...</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">🛏️ Room/Bed:</span>
                                    <span class="info-value">Room <?php echo $child['room_number']; ?>, Bed <?php echo $child['bed_label']; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">💰 Monthly Rent:</span>
                                    <span class="info-value">₹<?php echo number_format($child['rent_amount'] ?? 0); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">📅 Due Date:</span>
                                    <span class="info-value"><?php echo $child['due_date'] ?? 'Not set'; ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">📊 Attendance:</span>
                                    <span class="info-value">
                                        <?php 
                                        $percent = $child['total_days'] > 0 ? round(($child['present_days'] / $child['total_days']) * 100) : 0;
                                        echo $child['present_days'] . '/' . $child['total_days'] . ' days (' . $percent . '%)';
                                        ?>
                                        <div class="attendance-bar">
                                            <div class="attendance-fill" style="width: <?php echo $percent; ?>%;"></div>
                                        </div>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="info-row">
                                    <span class="info-label">📋 Status:</span>
                                    <span class="info-value">No active PG accommodation. Please contact manager.</span>
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 15px;">
                                <a href="child_details.php?id=<?php echo $child['child_id']; ?>" class="btn-view">View Complete Details →</a>
                                <?php if($child['pg_name']): ?>
                                    <a href="payment.php?child_id=<?php echo $child['child_id']; ?>" class="btn-view" style="background: #28a745;">💰 Pay Rent</a>
                                    <a href="attendance.php?child_id=<?php echo $child['child_id']; ?>" class="btn-view" style="background: #17a2b8;">📅 View Attendance</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-children">
                    <p style="font-size: 48px; margin-bottom: 20px;">👶</p>
                    <h3>No Children Linked Yet</h3>
                    <p>Please contact the PG Manager to link your child to your account.</p>
                    <p style="color: #666; margin-top: 20px;">Once linked, you'll be able to:</p>
                    <ul style="display: inline-block; text-align: left;">
                        <li>Track daily attendance</li>
                        <li>Pay rent online via UPI</li>
                        <li>View payment history</li>
                        <li>Monitor accommodation details</li>
                        <li>Receive notifications</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>