<?php
require_once '../config/db.php';
$required_role = 'manager';
include '../includes/session-check.php';

$manager_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM pgs WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$pg = $stmt->fetch();
$pg_id = $pg['id'];

$message = '';

// Record cash payment
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_cash'])) {
    $tenant_id = $_POST['tenant_id'];
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    
    $stmt = $pdo->prepare("
        INSERT INTO payments (tenant_id, pg_id, amount, payment_type, payment_date, status, recorded_by)
        VALUES (?, ?, ?, 'cash', ?, 'verified', ?)
    ");
    $stmt->execute([$tenant_id, $pg_id, $amount, $payment_date, $manager_id]);
    $message = '<div class="success-message">Cash payment recorded successfully</div>';
}

// Get tenants for dropdown
$stmt = $pdo->prepare("
    SELECT u.id, u.name, b.id as booking_id
    FROM bookings b
    JOIN users u ON b.tenant_id = u.id
    WHERE b.pg_id = ? AND b.status = 'confirmed'
");
$stmt->execute([$pg_id]);
$tenants = $stmt->fetchAll();

// Get payment history
$stmt = $pdo->prepare("
    SELECT p.*, u.name as tenant_name
    FROM payments p
    JOIN users u ON p.tenant_id = u.id
    WHERE p.pg_id = ?
    ORDER BY p.payment_date DESC
");
$stmt->execute([$pg_id]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <li><a href="payments.php" class="active">Payments</a></li>
                <li><a href="complaints.php">Complaints</a></li>
<li><a href="pg-images.php">PG Images</a></li>
<li><a href="profile.php">My Profile</a></li>

                <li><a href="pg-settings.php">PG Settings</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Payment Management</h1>
            
            <?php echo $message; ?>
            
            <div class="cash-payment-form">
                <h2>Record Cash Payment</h2>
                <form method="POST" style="display:flex; gap:1rem; flex-wrap:wrap;">
                    <div class="form-group">
                        <label>Select Tenant</label>
                        <select name="tenant_id" required>
                            <option value="">Select Tenant</option>
                            <?php foreach($tenants as $tenant): ?>
                                <option value="<?php echo $tenant['id']; ?>"><?php echo htmlspecialchars($tenant['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount (₹)</label>
                        <input type="number" name="amount" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Date</label>
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <button type="submit" name="record_cash" class="btn-primary">Record Payment</button>
                </form>
            </div>
            
            <div class="payment-history">
                <h2>Payment History</h2>
                <div class="data-table">
                    <table>
                        <thead>
                            <tr><th>Date</th><th>Tenant</th><th>Amount</th><th>Type</th><th>Status</th><th>UTR</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($payments as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['payment_date']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                                    <td>₹<?php echo number_format($payment['amount']); ?></td>
                                    <td><?php echo ucfirst($payment['payment_type']); ?> </td>
                                    <td><?php echo ucfirst($payment['status']); ?> </td>
                                    <td><?php echo $payment['utr_number'] ?? '-'; ?> </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>