<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

$stmt = $pdo->query("
    SELECT c.*, u.name as tenant_name, pg.name as pg_name
    FROM complaints c
    JOIN users u ON c.tenant_id = u.id
    JOIN pgs pg ON c.pg_id = pg.id
    ORDER BY c.created_at DESC
");
$complaints = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Complaints - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="managers.php">PG Managers</a></li>

        <li><a href="tenants.php">All Tenants</a></li>
<li><a href="parents.php">Parents</a></li>
        
                <li><a href="pg-listings.php">PG Listings</a></li>
                <li><a href="complaints.php" class="active">Complaints</a></li>
                <li><a href="payments.php">Payments</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>All Complaints (System-wide)</h1>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr><th>Date</th><th>PG</th><th>Tenant</th><th>Category</th><th>Description</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($complaints as $complaint): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($complaint['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($complaint['pg_name']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['tenant_name']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['category']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></td>
                                <td><?php echo ucfirst($complaint['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>