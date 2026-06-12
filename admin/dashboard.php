<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';
$base_path='../../';

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pgs WHERE status = 'approved'");
$totalPGs = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'manager' AND status = 'active'");
$totalManagers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'tenant'");
$totalTenants = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM complaints WHERE status = 'open'");
$openComplaints = $stmt->fetch()['total'];

// Pending managers
$stmt = $pdo->query("SELECT u.*, p.name as pg_name FROM users u JOIN pgs p ON u.id = p.manager_id WHERE u.role = 'manager' AND u.status = 'pending'");
$pendingManagers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="managers.php">PG Managers</a></li>
                <li><a href="tenants.php">All Tenants</a></li>
<li><a href="parents.php">Parents</a></li>
        
                <li><a href="pg-listings.php">PG Listings</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="payments.php">Payments</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Admin Dashboard</h1>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total PGs</h3>
                    <div class="stat-number"><?php echo $totalPGs; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Managers</h3>
                    <div class="stat-number"><?php echo $totalManagers; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Tenants</h3>
                    <div class="stat-number"><?php echo $totalTenants; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Open Complaints</h3>
                    <div class="stat-number"><?php echo $openComplaints; ?></div>
                </div>
            </div>
            
            <?php if(count($pendingManagers) > 0): ?>
                <div class="data-table">
                    <h2>Pending Manager Approvals</h2>
                    <table>
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>PG Name</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($pendingManagers as $manager): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($manager['name']); ?></td>
                                    <td><?php echo htmlspecialchars($manager['email']); ?></td>
                                    <td><?php echo htmlspecialchars($manager['pg_name']); ?></td>
                                    <td>
                                        <button onclick="approveManager(<?php echo $manager['id']; ?>)" class="btn-primary">Approve</button>
                                        <button onclick="rejectManager(<?php echo $manager['id']; ?>)" class="btn-danger">Reject</button>
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
        function approveManager(userId) {
            if(confirm('Approve this manager?')) {
                fetch('../ajax/approve_manager.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({user_id: userId, action: 'approve'})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) location.reload();
                    else alert(data.message);
                });
            }
        }
        
        function rejectManager(userId) {
            if(confirm('Reject this manager?')) {
                fetch('../ajax/approve_manager.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({user_id: userId, action: 'reject'})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) location.reload();
                    else alert(data.message);
                });
            }
        }
    </script>
</body>
</html>