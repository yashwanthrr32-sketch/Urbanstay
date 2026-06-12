<?php
require_once '../config/db.php';
$required_role = 'tenant';
include '../includes/session-check.php';
$base_path='../../';

$tenant_id = $_SESSION['user_id'];
if(isset($_GET['booking_success'])) {
    echo "<script>alert('Booking submitted successfully! Please wait for manager approval.');</script>";
}


// Get active booking
$stmt = $pdo->prepare("
    SELECT b.*, pg.name as pg_name, pg.address, pg.price_per_month, r.room_number, bed.bed_label,
           td.rent_amount, td.due_date, td.move_in_date
    FROM bookings b
    JOIN pgs pg ON b.pg_id = pg.id
    JOIN beds bed ON b.bed_id = bed.id
    JOIN rooms r ON bed.room_id = r.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    WHERE b.tenant_id = ? AND b.status IN ('processing', 'confirmed')
    ORDER BY b.requested_at DESC LIMIT 1
");
$stmt->execute([$tenant_id]);
$activeBooking = $stmt->fetch();

// Get all bookings for history
$stmt = $pdo->prepare("
    SELECT b.*, pg.name as pg_name, r.room_number, bed.bed_label
    FROM bookings b
    JOIN pgs pg ON b.pg_id = pg.id
    JOIN beds bed ON b.bed_id = bed.id
    JOIN rooms r ON bed.room_id = r.id
    WHERE b.tenant_id = ? 
    ORDER BY b.requested_at DESC
");
$stmt->execute([$tenant_id]);
$allBookings = $stmt->fetchAll();

// Get notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$stmt->execute([$tenant_id]);
$notifications = $stmt->fetchAll();

// Mark notifications as read
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->execute([$tenant_id]);

// Get attendance stats for current month
$currentMonth = date('Y-m');
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
    FROM attendance 
    WHERE tenant_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
");
$stmt->execute([$tenant_id, $currentMonth]);
$attendance = $stmt->fetch();
$attendancePercent = $attendance['total'] > 0 ? round(($attendance['present'] / $attendance['total']) * 100) : 0;

// Get payment status for current month
$paymentStatus = 'No payment due';
$stmt = $pdo->prepare("
    SELECT status, amount, payment_date 
    FROM payments 
    WHERE tenant_id = ? 
    ORDER BY payment_date DESC LIMIT 1
");
$stmt->execute([$tenant_id]);
$lastPayment = $stmt->fetch();

// Check if rent is due
$rentDueSoon = false;
if($activeBooking && $activeBooking['due_date']) {
    $today = date('Y-m-d');
    $due_date = $activeBooking['due_date'];
    if($due_date < $today) {
        $paymentStatus = 'Overdue! Please pay immediately';
        $rentDueSoon = true;
    } elseif($due_date <= date('Y-m-d', strtotime('+3 days'))) {
        $paymentStatus = 'Due in ' . ceil((strtotime($due_date) - strtotime($today)) / 86400) . ' days';
        $rentDueSoon = true;
    } else {
        $paymentStatus = 'Due on ' . date('d M Y', strtotime($due_date));
    }
}

// Handle vacate request
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_vacate'])) {
    $booking_id = $_POST['booking_id'];
    
    // Check if request already exists
    $stmt = $pdo->prepare("SELECT id FROM vacate_requests WHERE tenant_id = ? AND status = 'pending'");
    $stmt->execute([$tenant_id]);
    if($stmt->fetch()) {
        $vacate_error = "You already have a pending vacate request.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO vacate_requests (tenant_id, booking_id, status, requested_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$tenant_id, $booking_id]);
        $vacate_success = "Vacate request submitted successfully. Manager will process it.";
        
        // Notify manager
        $stmt = $pdo->prepare("SELECT manager_id FROM pgs WHERE id = (SELECT pg_id FROM bookings WHERE id = ?)");
        $stmt->execute([$booking_id]);
        $pg = $stmt->fetch();
        if($pg) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$pg['manager_id'], "Vacate request received from " . $_SESSION['user_name']]);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .toast {
            background: white;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
        }
        .toast-success { border-left-color: #28a745; }
        .toast-error { border-left-color: #dc3545; }
        .toast-info { border-left-color: #185FA5; }
        .toast-warning { border-left-color: #ffc107; }
        .toast-success .toast-icon { color: #28a745; }
        .toast-error .toast-icon { color: #dc3545; }
        .toast-info .toast-icon { color: #185FA5; }
        .toast-warning .toast-icon { color: #ffc107; }
        .toast-icon { font-size: 24px; }
        .toast-content { flex: 1; }
        .toast-title { font-weight: bold; margin-bottom: 4px; }
        .toast-message { font-size: 14px; color: #666; }
        .toast-close { cursor: pointer; font-size: 18px; color: #999; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #185FA5;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-processing {
            background: #fff3cd;
            color: #856404;
        }
        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .booking-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="booking.php">My Booking</a></li>
                <li><a href="payment.php">Rent & Payment</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="feedback.php">Feedback</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
            
            <!-- Toast Notifications -->
            <div id="toast-container" class="toast-container"></div>
            
            <!-- Display success/error messages -->
            <?php if(isset($vacate_success)): ?>
                <div class="alert alert-success"><?php echo $vacate_success; ?></div>
            <?php endif; ?>
            <?php if(isset($vacate_error)): ?>
                <div class="alert alert-danger"><?php echo $vacate_error; ?></div>
            <?php endif; ?>
            
            <!-- Notifications from database -->
            <?php if(count($notifications) > 0): ?>
                <?php foreach($notifications as $notif): ?>
                    <div class="alert alert-info">
                        📢 <?php echo htmlspecialchars($notif['message']); ?>
                        <small style="display:block; margin-top:5px;"><?php echo date('d M Y h:i A', strtotime($notif['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <?php if($activeBooking): ?>
                    <div class="stat-card">
                        <h3>Room Number</h3>
                        <div class="stat-number"><?php echo htmlspecialchars($activeBooking['room_number']); ?></div>
                        <small>Bed: <?php echo htmlspecialchars($activeBooking['bed_label']); ?></small>
                    </div>
                    <div class="stat-card">
                        <h3>Rent Amount</h3>
                        <div class="stat-number">₹<?php echo number_format($activeBooking['rent_amount'] ?? $activeBooking['price_per_month']); ?></div>
                        <small><?php echo $paymentStatus; ?></small>
                    </div>
                <?php endif; ?>
                <div class="stat-card">
                    <h3>Attendance %</h3>
                    <div class="stat-number"><?php echo $attendancePercent; ?>%</div>
                    <small>This month (<?php echo date('F Y'); ?>)</small>
                </div>
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <div class="stat-number"><?php echo count($allBookings); ?></div>
                    <small>All time</small>
                </div>
            </div>
            
            <!-- Current Booking Section -->
            <?php if($activeBooking): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <h2>Current Stay</h2>
                        <span class="status-badge status-<?php echo $activeBooking['status']; ?>">
                            <?php echo ucfirst($activeBooking['status']); ?>
                        </span>
                    </div>
                    <p><strong>🏠 PG Name:</strong> <?php echo htmlspecialchars($activeBooking['pg_name']); ?></p>
                    <p><strong>📍 Address:</strong> <?php echo htmlspecialchars($activeBooking['address']); ?></p>
                    <p><strong>🛏️ Room/Bed:</strong> Room <?php echo $activeBooking['room_number']; ?>, Bed <?php echo $activeBooking['bed_label']; ?></p>
                    <p><strong>📅 Move-in Date:</strong> <?php echo $activeBooking['move_in_date'] ?? 'Not set yet'; ?></p>
                    
                    <?php if($activeBooking['status'] == 'confirmed'): ?>
                        <form method="POST" style="margin-top: 15px;" onsubmit="return confirm('Are you sure you want to request to vacate?');">
                            <input type="hidden" name="booking_id" value="<?php echo $activeBooking['id']; ?>">
                            <button type="submit" name="request_vacate" class="btn-danger">Request to Vacate</button>
                        </form>
                    <?php elseif($activeBooking['status'] == 'processing'): ?>
                        <div class="alert alert-warning" style="margin-top: 15px;">
                            ⏳ Your booking request is pending approval from the PG Manager. 
                            Please visit the PG to complete the process.
                        </div>
                    <?php endif; ?>

                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    🏠 You don't have an active booking. 
                    <a href="../index.php" style="color: #185FA5; font-weight: bold;">Browse PGs</a> to find your perfect stay.
                </div>
            <?php endif; ?>
            
            <!-- Booking History Section -->
            <?php if(count($allBookings) > 1): ?>
                <h2>Booking History</h2>
                <div class="data-table">
                    <table>
                        <thead>
                            <tr><th>PG Name</th><th>Room/Bed</th><th>Status</th><th>Requested Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($allBookings as $booking): ?>
                                <?php if($booking['status'] != 'processing' && $booking['status'] != 'confirmed'): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['pg_name']); ?></td>
                                        <td>Room <?php echo $booking['room_number']; ?>, Bed <?php echo $booking['bed_label']; ?></td>
                                        <td><span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                        <td><?php echo date('d M Y', strtotime($booking['requested_at'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Toast Notification System
        const Toast = {
            show: function(message, type = 'success', title = '') {
                let container = document.getElementById('toast-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'toast-container';
                    container.className = 'toast-container';
                    document.body.appendChild(container);
                }
                
                const titles = {
                    success: title || 'Success!',
                    error: title || 'Error!',
                    info: title || 'Information',
                    warning: title || 'Warning!'
                };
                
                const icons = {
                    success: '✓',
                    error: '✗',
                    info: 'ℹ',
                    warning: '⚠'
                };
                
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.innerHTML = `
                    <div class="toast-icon">${icons[type]}</div>
                    <div class="toast-content">
                        <div class="toast-title">${titles[type]}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                    <div class="toast-close" onclick="this.parentElement.remove()">×</div>
                `;
                
                container.appendChild(toast);
                
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.style.animation = 'fadeOut 0.3s ease';
                        setTimeout(() => {
                            if (toast.parentElement) toast.remove();
                        }, 300);
                    }
                }, 5000);
            }
        };
        
        // Check for URL parameters and show toasts
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if(urlParams.has('booking_success')) {
                Toast.show('Your booking request has been submitted! Please visit the PG for confirmation.', 'success', 'Booking Submitted! 🎉');
                // Remove parameter from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if(urlParams.has('booking_confirmed')) {
                Toast.show('Congratulations! Your booking has been confirmed. Welcome to your new home! 🏠', 'success', 'Booking Confirmed!');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if(urlParams.has('payment_success')) {
                Toast.show('Your payment has been recorded successfully!', 'success', 'Payment Received');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                if(!alert.innerHTML.includes('form')) {
                    setTimeout(() => {
                        alert.style.animation = 'fadeOut 0.3s ease';
                        setTimeout(() => alert.remove(), 300);
                    }, 5000);
                }
            });
        }, 1000);
    </script>
</body>
</html>