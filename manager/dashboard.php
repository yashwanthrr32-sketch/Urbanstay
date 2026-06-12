<?php
require_once '../config/db.php';
$required_role = 'manager';
include '../includes/session-check.php';

$manager_id = $_SESSION['user_id'];

// Get manager's PG
$stmt = $pdo->prepare("SELECT id, name FROM pgs WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$pg = $stmt->fetch();

if(!$pg) {
    echo "<div class='container'><h1>No PG assigned yet. Please wait for admin approval.</h1></div>";
    exit;
}

$pg_id = $pg['id'];

// Statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings b WHERE b.pg_id = ? AND b.status = 'confirmed'");
$stmt->execute([$pg_id]);
$totalTenants = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM beds WHERE room_id IN (SELECT id FROM rooms WHERE pg_id = ?) AND status = 'available'");
$stmt->execute([$pg_id]);
$freeBeds = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM complaints WHERE pg_id = ? AND status = 'open'");
$stmt->execute([$pg_id]);
$openComplaints = $stmt->fetch()['total'];

// Pending booking requests
$stmt = $pdo->prepare("
    SELECT b.*, u.name as tenant_name, u.email, bed.bed_label, r.room_number
    FROM bookings b
    JOIN users u ON b.tenant_id = u.id
    JOIN beds bed ON b.bed_id = bed.id
    JOIN rooms r ON bed.room_id = r.id
    WHERE b.pg_id = ? AND b.status = 'processing'
    ORDER BY b.requested_at ASC
");
$stmt->execute([$pg_id]);
$pendingBookings = $stmt->fetchAll();

// Pending UTR verifications
$stmt = $pdo->prepare("
    SELECT p.*, u.name as tenant_name 
    FROM payments p
    JOIN users u ON p.tenant_id = u.id
    WHERE p.pg_id = ? AND p.status = 'pending' AND p.payment_type = 'upi'
    ORDER BY p.created_at ASC
");
$stmt->execute([$pg_id]);
$pendingUTRs = $stmt->fetchAll();

// Vacate requests
$stmt = $pdo->prepare("
    SELECT vr.*, u.name as tenant_name, b.bed_id, bed.bed_label, r.room_number
    FROM vacate_requests vr
    JOIN bookings b ON vr.booking_id = b.id
    JOIN users u ON vr.tenant_id = u.id
    JOIN beds bed ON b.bed_id = bed.id
    JOIN rooms r ON bed.room_id = r.id
    WHERE b.pg_id = ? AND vr.status = 'pending'
");
$stmt->execute([$pg_id]);
$vacateRequests = $stmt->fetchAll();


// Get pending unlink requests
$stmt = $pdo->prepare("
    SELECT ur.*, u.name as parent_name, u.email as parent_email, 
           t.name as child_name, t.email as child_email
    FROM unlink_requests ur
    JOIN users u ON ur.parent_id = u.id
    JOIN users t ON ur.tenant_id = t.id
    JOIN bookings b ON t.id = b.tenant_id
    WHERE b.pg_id = ? AND ur.status = 'pending'
");
$stmt->execute([$pg_id]);
$unlink_requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        .data-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .data-table h2 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #185FA5;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .btn-primary {
            background: #185FA5;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-success {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
        }
        .request-card {
            background: #f8f9fa;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .request-info {
            flex: 1;
        }
        .request-info strong {
            color: #185FA5;
        }
        .badge {
            background: #ffc107;
            color: #856404;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="tenants.php">Tenants</a></li>
                <li><a href="rooms.php">Rooms & Beds</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="pg-images.php">PG Images</a></li>
                <li><a href="pg-settings.php">PG Settings</a></li>
<li><a href="profile.php">My Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1><?php echo htmlspecialchars($pg['name']); ?> - Dashboard</h1>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Tenants</h3>
                    <div class="stat-number"><?php echo $totalTenants; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Free Beds</h3>
                    <div class="stat-number"><?php echo $freeBeds; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Open Complaints</h3>
                    <div class="stat-number"><?php echo $openComplaints; ?></div>
                </div>
            </div>
            
            <!-- Unlink Requests Section -->
            <?php if(count($unlink_requests) > 0): ?>
            <div class="data-table">
                <h2>🔗 Parent Unlink Requests <span class="badge"><?php echo count($unlink_requests); ?> pending</span></h2>
                <?php foreach($unlink_requests as $request): ?>
                    <div class="request-card" id="request-<?php echo $request['id']; ?>">
                        <div class="request-info">
                            <strong><?php echo htmlspecialchars($request['parent_name']); ?></strong> 
                            wants to unlink from <strong><?php echo htmlspecialchars($request['child_name']); ?></strong><br>
                            <small>Requested on: <?php echo date('d M Y, h:i A', strtotime($request['requested_at'])); ?></small>
                        </div>
                        <div class="request-actions">
                            <button onclick="processUnlinkRequest(<?php echo $request['id']; ?>, 'approve')" class="btn-success">✓ Approve</button>
                            <button onclick="processUnlinkRequest(<?php echo $request['id']; ?>, 'reject')" class="btn-danger">✗ Reject</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Pending Booking Requests -->
            <?php if(count($pendingBookings) > 0): ?>
            <div class="data-table">
                <h2>📋 Pending Booking Requests</h2>
                <table>
                    <thead>
                        <tr><th>Tenant</th><th>Room/Bed</th><th>Requested On</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($pendingBookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['tenant_name']); ?><br><small><?php echo $booking['email']; ?></small></td>
                                <td>Room <?php echo $booking['room_number']; ?>, Bed <?php echo $booking['bed_label']; ?></td>
                                <td><?php echo date('d M Y', strtotime($booking['requested_at'])); ?></td>
                                <td>
                                    <button onclick="approveBooking(<?php echo $booking['id']; ?>)" class="btn-success">Approve</button>
                                    <button onclick="rejectBooking(<?php echo $booking['id']; ?>)" class="btn-danger">Reject</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Pending UTR Verifications -->
            <?php if(count($pendingUTRs) > 0): ?>
            <div class="data-table">
                <h2>💰 Pending UTR Verifications</h2>
                <table>
                    <thead>
                        <tr><th>Tenant</th><th>Amount</th><th>UTR Number</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($pendingUTRs as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                                <td>₹<?php echo number_format($payment['amount']); ?></td>
                                <td><?php echo $payment['utr_number']; ?></td>
                                <td>
                                    <button onclick="verifyUTR(<?php echo $payment['id']; ?>, 'verify')" class="btn-success">Verify</button>
                                    <button onclick="verifyUTR(<?php echo $payment['id']; ?>, 'reject')" class="btn-danger">Reject</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Vacate Requests -->
            <?php if(count($vacateRequests) > 0): ?>
            <div class="data-table">
                <h2>🚪 Vacate Requests</h2>
                <table>
                    <thead>
                        <tr><th>Tenant</th><th>Room/Bed</th><th>Requested On</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($vacateRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['tenant_name']); ?></td>
                                <td>Room <?php echo $request['room_number']; ?>, Bed <?php echo $request['bed_label']; ?></td>
                                <td><?php echo date('d M Y', strtotime($request['requested_at'])); ?></td>
                                <td>
                                    <button onclick="approveVacate(<?php echo $request['id']; ?>)" class="btn-success">Approve Vacate</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function approveBooking(bookingId) {
            fetch('../ajax/process_booking.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({booking_id: bookingId, action: 'approve'})
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) location.reload();
                else alert(data.message);
            });
        }
        
        function rejectBooking(bookingId) {
            if(confirm('Reject this booking?')) {
                fetch('../ajax/process_booking.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({booking_id: bookingId, action: 'reject'})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) location.reload();
                    else alert(data.message);
                });
            }
        }
        
        function verifyUTR(paymentId, action) {
            fetch('../ajax/verify_utr.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({payment_id: paymentId, action: action})
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) location.reload();
                else alert(data.message);
            });
        }
        
        function approveVacate(requestId) {
            if(confirm('Approve vacate request? This will free the bed.')) {
                fetch('../ajax/approve_vacate.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({request_id: requestId})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) location.reload();
                    else alert(data.message);
                });
            }
        }
        
        function processUnlinkRequest(requestId, action) {
            if(confirm(action === 'approve' ? 'Approve this unlink request? Parent will no longer see this child.' : 'Reject this unlink request?')) {
                fetch('../ajax/process_unlink_request.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({request_id: requestId, action: action})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        // Remove the request card from display
                        const requestCard = document.getElementById('request-' + requestId);
                        if(requestCard) {
                            requestCard.remove();
                        }
                        alert(data.message);
                        location.reload();
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