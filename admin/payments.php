<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

$stmt = $pdo->query("
    SELECT pg.name as pg_name, 
           SUM(CASE WHEN MONTH(p.payment_date) = MONTH(CURDATE()) THEN p.amount ELSE 0 END) as monthly_collection,
           COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_count
    FROM pgs pg
    LEFT JOIN payments p ON pg.id = p.pg_id
    GROUP BY pg.id
");
$collections = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Overview - Urban Stay</title>
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
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="payments.php" class="active">Payments</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Payment Overview</h1>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr><th>PG Name</th><th>This Month Collection</th><th>Pending Payments</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($collections as $col): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($col['pg_name']); ?></td>
                                <td>₹<?php echo number_format($col['monthly_collection']); ?></td>
                                <td><?php echo $col['pending_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>