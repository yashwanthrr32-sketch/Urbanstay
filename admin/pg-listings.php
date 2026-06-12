<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

$stmt = $pdo->query("SELECT p.*, u.name as manager_name FROM pgs p LEFT JOIN users u ON p.manager_id = u.id ORDER BY p.created_at DESC");
$pgs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PG Listings - Urban Stay</title>
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
        
                <li><a href="pg-listings.php" class="active">PG Listings</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="payments.php">Payments</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>PG Listings</h1>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr><th>PG Name</th><th>Location</th><th>Manager</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($pgs as $pg): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pg['name']); ?></td>
                                <td><?php echo htmlspecialchars($pg['address']); ?></td>
                                <td><?php echo htmlspecialchars($pg['manager_name'] ?? 'N/A'); ?></td>
                                <td><?php echo ucfirst($pg['status']); ?></td>
                                <td>
    <a href="view_pg.php?id=<?php echo $pg['id']; ?>" class="btn-view">View Details</a>
    <button onclick="removePG(<?php echo $pg['id']; ?>)" class="btn-danger">Remove PG</button>
</td>                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function removePG(pgId) {
            if(confirm('Remove this PG listing? This action cannot be undone.')) {
                fetch('../ajax/remove_pg.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({pg_id: pgId})
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