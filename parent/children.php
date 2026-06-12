<?php
require_once '../config/db.php';
$required_role = 'parent';
include '../includes/session-check.php';

$parent_id = $_SESSION['user_id'];

// Get all linked children with complete details
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
        pg.id as pg_id,
        pg.name as pg_name,
        pg.address as pg_address,
        pg.type as pg_type,
        pg.price_per_month,
        r.room_number,
        bed.bed_label,
        td.rent_amount,
        td.due_date,
        td.move_in_date,
        (SELECT COUNT(*) FROM attendance WHERE tenant_id = u.id AND status = 'present' AND MONTH(date) = MONTH(CURDATE())) as present_days,
        (SELECT COUNT(*) FROM attendance WHERE tenant_id = u.id AND MONTH(date) = MONTH(CURDATE())) as total_days,
        (SELECT amount FROM payments WHERE tenant_id = u.id AND status = 'verified' ORDER BY payment_date DESC LIMIT 1) as last_payment,
        (SELECT payment_date FROM payments WHERE tenant_id = u.id AND status = 'verified' ORDER BY payment_date DESC LIMIT 1) as last_payment_date
    FROM parent_tenant pt
    JOIN users u ON pt.tenant_id = u.id
    LEFT JOIN bookings b ON u.id = b.tenant_id AND b.status IN ('processing', 'confirmed')
    LEFT JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN beds bed ON b.bed_id = bed.id
    LEFT JOIN rooms r ON bed.room_id = r.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    WHERE pt.parent_id = ?
    ORDER BY u.name
");
$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();

// Calculate attendance percentage for each child
foreach($children as &$child) {
    if($child['total_days'] > 0) {
        $child['attendance_percent'] = round(($child['present_days'] / $child['total_days']) * 100);
    } else {
        $child['attendance_percent'] = 0;
    }
}
// After getting children list, check for pending requests
$stmt = $pdo->prepare("
    SELECT tenant_id, status FROM unlink_requests 
    WHERE parent_id = ? AND status = 'pending'
");
$stmt->execute([$parent_id]);
$pending_requests = [];
while($row = $stmt->fetch()) {
    $pending_requests[$row['tenant_id']] = $row['status'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children - Parent Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .children-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .child-card {
            background: white;
            border-radius: 10px;
            margin-bottom: 25px;
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
        .child-header h2 {
            margin: 0;
            font-size: 1.3rem;
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
        .child-body {
            padding: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .info-section h4 {
            color: #185FA5;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        .detail-row {
            margin-bottom: 8px;
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
            color: #555;
        }
        .detail-value {
            display: inline-block;
            color: #333;
        }
        .stats-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .stat-box {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            text-align: center;
            flex: 1;
            min-width: 100px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-box .number {
            font-size: 24px;
            font-weight: bold;
            color: #185FA5;
        }
        .stat-box .label {
            font-size: 11px;
            color: #666;
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
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="children.php">My Children</a></li>
        <li><a href="attendance.php">Attendance</a></li>
        <li><a href="payment.php">Rent & Payment</a></li>
        <li><a href="profile.php">Profile</a></li>
    </ul>
</div>        
        <div class="main-content">
            <div class="children-container">
                <h1>👨‍👩‍👧‍👦 My Children</h1>
                
                <?php if(count($children) > 0): ?>
                    <?php foreach($children as $child): ?>
                        <div class="child-card">
<div style="margin-top: 15px;">
    <a href="child_details.php?id=<?php echo $child['child_id']; ?>" class="btn-view">View Details →</a>
    <?php if($child['pg_name']): ?>
        <a href="payment.php?child_id=<?php echo $child['child_id']; ?>" class="btn-view" style="background: #28a745;">💰 Pay Rent</a>
        <a href="attendance.php?child_id=<?php echo $child['child_id']; ?>" class="btn-view" style="background: #17a2b8;">📅 Attendance</a>
    <?php endif; ?>
    <button onclick="requestUnlink(<?php echo $child['child_id']; ?>)" class="btn-danger" style="padding: 8px 16px;">🔗 Request Unlink</button>
</div>
                            <div class="child-header">
                                <h2><?php echo htmlspecialchars($child['child_name']); ?></h2>
                                <span class="status-badge status-<?php echo $child['booking_status'] ?? 'no-booking'; ?>">
                                    <?php echo ucfirst($child['booking_status'] ?? 'No Active Booking'); ?>
                                </span>
                            </div>
                            <div class="child-body">
                                <div class="info-grid">
                                    <div class="info-section">
                                        <h4>📋 Personal Information</h4>
                                        <div class="detail-row">
                                            <span class="detail-label">Email:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($child['child_email']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Phone:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($child['child_phone']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Registered:</span>
                                            <span class="detail-value"><?php echo date('d M Y', strtotime($child['child_registered_date'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="info-section">
                                        <h4>🏠 PG & Accommodation</h4>
                                        <?php if($child['pg_name']): ?>
                                            <div class="detail-row">
                                                <span class="detail-label">PG Name:</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($child['pg_name']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Address:</span>
                                                <span class="detail-value"><?php echo htmlspecialchars(substr($child['pg_address'], 0, 60)); ?>...</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Room/Bed:</span>
                                                <span class="detail-value">Room <?php echo $child['room_number']; ?>, Bed <?php echo $child['bed_label']; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Move-in Date:</span>
                                                <span class="detail-value"><?php echo $child['move_in_date'] ?? 'Not set'; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <p style="color: #999;">No active PG accommodation</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="info-section">
                                        <h4>💰 Rent Details</h4>
                                        <?php if($child['rent_amount']): ?>
                                            <div class="detail-row">
                                                <span class="detail-label">Monthly Rent:</span>
                                                <span class="detail-value">₹<?php echo number_format($child['rent_amount']); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Due Date:</span>
                                                <span class="detail-value"><?php echo $child['due_date'] ?? 'End of month'; ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Last Payment:</span>
                                                <span class="detail-value">₹<?php echo number_format($child['last_payment'] ?? 0); ?> on <?php echo $child['last_payment_date'] ?? 'N/A'; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <p style="color: #999;">Rent details not set yet</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="stats-row">
                                    <div class="stat-box">
                                        <div class="number"><?php echo $child['attendance_percent']; ?>%</div>
                                        <div class="label">Attendance (This Month)</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="number"><?php echo $child['present_days']; ?>/<?php echo $child['total_days']; ?></div>
                                        <div class="label">Present/Total Days</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="number">
                                            <?php
                                            $due_date = $child['due_date'];
                                            if($due_date && $due_date < date('Y-m-d')) {
                                                echo "Overdue";
                                            } elseif($due_date && $due_date <= date('Y-m-d', strtotime('+3 days'))) {
                                                echo "Due Soon";
                                            } else {
                                                echo "On Track";
                                            }
                                            ?>
                                        </div>
                                        <div class="label">Payment Status</div>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 15px; text-align: right;">
                                    <a href="child_details.php?id=<?php echo $child['child_id']; ?>" class="btn-view">View Complete Details →</a>
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
                            <li>Track attendance</li>
                            <li>Pay rent online</li>
                            <li>View payment history</li>
                            <li>Monitor accommodation details</li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<script>
function requestUnlink(childId) {
    if(confirm('Are you sure you want to request to unlink from this child? The PG manager will need to approve this request.')) {
        fetch('../ajax/request_unlink.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({child_id: childId})
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Unlink request submitted. The PG manager will review it.');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
    }
}
</script>
</body>
</html>