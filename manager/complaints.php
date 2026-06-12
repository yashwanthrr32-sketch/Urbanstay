<?php
require_once '../config/db.php';
$required_role = 'manager';
include '../includes/session-check.php';

$manager_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM pgs WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$pg = $stmt->fetch();
$pg_id = $pg['id'];

// Handle resolve complaint
if(isset($_GET['resolve'])) {
    $complaint_id = $_GET['resolve'];
    $stmt = $pdo->prepare("UPDATE complaints SET status = 'resolved', resolved_at = NOW() WHERE id = ? AND pg_id = ?");
    $stmt->execute([$complaint_id, $pg_id]);
    
    // Add notification for tenant
    $stmt = $pdo->prepare("SELECT tenant_id FROM complaints WHERE id = ?");
    $stmt->execute([$complaint_id]);
    $complaint = $stmt->fetch();
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, 'Your complaint has been resolved')");
    $stmt->execute([$complaint['tenant_id']]);
    
    header('Location: complaints.php');
    exit;
}

// Get complaints
$stmt = $pdo->prepare("
    SELECT c.*, u.name as tenant_name
    FROM complaints c
    JOIN users u ON c.tenant_id = u.id
    WHERE c.pg_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$pg_id]);
$complaints = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaints - Urban Stay</title>
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
                <li><a href="payments.php">Payments</a></li>
                <li><a href="complaints.php" class="active">Complaints</a></li>
<li><a href="pg-images.php">PG Images</a></li>
<li><a href="profile.php">My Profile</a></li>

                <li><a href="pg-settings.php">PG Settings</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Tenant Complaints</h1>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr><th>Date</th><th>Tenant</th><th>Category</th><th>Description</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($complaints as $complaint): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($complaint['tenant_name']); ?> </td>
                                <td><?php echo htmlspecialchars($complaint['category']); ?> </td>
                                <td><?php echo nl2br(htmlspecialchars($complaint['description'])); ?> </td>
                                <td>
                                    <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                        <?php echo ucfirst($complaint['status']); ?>
                                    </span>
                                 </td>
                                <td>
                                    <?php if($complaint['status'] == 'open'): ?>
                                        <a href="?resolve=<?php echo $complaint['id']; ?>" class="btn-primary" onclick="return confirm('Mark as resolved?')">Resolve</a>
                                    <?php endif; ?>
                                 </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>