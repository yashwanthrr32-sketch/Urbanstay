<?php
require_once '../config/db.php';
$required_role = 'manager';
include '../includes/session-check.php';

$manager_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM pgs WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$pg = $stmt->fetch();
$pg_id = $pg['id'];

$today = date('Y-m-d');
$message = '';

// Get all confirmed tenants
$stmt = $pdo->prepare("
    SELECT u.id, u.name, b.id as booking_id
    FROM bookings b
    JOIN users u ON b.tenant_id = u.id
    WHERE b.pg_id = ? AND b.status = 'confirmed'
");
$stmt->execute([$pg_id]);
$tenants = $stmt->fetchAll();

// Handle marking attendance
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_attendance'])) {
    $date = $_POST['attendance_date'];
    
    // Delete existing attendance for this date
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE pg_id = ? AND date = ?");
    $stmt->execute([$pg_id, $date]);
    
    foreach($_POST['status'] as $tenant_id => $status) {
        $stmt = $pdo->prepare("INSERT INTO attendance (tenant_id, pg_id, date, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $pg_id, $date, $status]);
    }
    $message = '<div class="success-message">Attendance saved for ' . $date . '</div>';
}

// Get today's attendance status
$todayStatus = [];
$stmt = $pdo->prepare("SELECT tenant_id, status FROM attendance WHERE pg_id = ? AND date = ?");
$stmt->execute([$pg_id, $today]);
while($row = $stmt->fetch()) {
    $todayStatus[$row['tenant_id']] = $row['status'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Urban Stay</title>
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
                <li><a href="attendance.php" class="active">Attendance</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="complaints.php">Complaints</a></li>
<li><a href="pg-images.php">PG Images</a></li>
<li><a href="profile.php">My Profile</a></li>

                <li><a href="pg-settings.php">PG Settings</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Mark Attendance</h1>
            
            <?php echo $message; ?>
            
            <div class="attendance-form">
                <form method="POST">
                    <div class="form-group">
                        <label>Select Date</label>
                        <input type="date" name="attendance_date" value="<?php echo $today; ?>" required>
                        <small>Note: You can only edit today's attendance. Previous days are locked.</small>
                    </div>
                    
                    <table class="data-table">
                        <thead>
                            <tr><th>Tenant Name</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($tenants as $tenant): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tenant['name']); ?></td>
                                    <td>
                                        <label>
                                            <input type="radio" name="status[<?php echo $tenant['id']; ?>]" value="present" 
                                                <?php echo (($todayStatus[$tenant['id']] ?? '') == 'present') ? 'checked' : ''; ?>> Present
                                        </label>
                                        <label>
                                            <input type="radio" name="status[<?php echo $tenant['id']; ?>]" value="absent"
                                                <?php echo (($todayStatus[$tenant['id']] ?? '') == 'absent') ? 'checked' : ''; ?>> Absent
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <button type="submit" name="save_attendance" class="btn-primary">Save Attendance</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>