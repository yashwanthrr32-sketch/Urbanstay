<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

// Get all parents
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM parent_tenant WHERE parent_id = u.id) as children_count,
           (SELECT COUNT(*) FROM user_documents WHERE user_id = u.id AND user_role = 'parent' AND status = 'pending') as pending_docs
    FROM users u
    WHERE u.role = 'parent'
    ORDER BY u.created_at DESC
");
$stmt->execute();
$parents = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parents - Admin</title>
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
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .btn-view {
            background: #17a2b8;
            color: white;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .pending-badge {
            background: #ffc107;
            color: #856404;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            margin-left: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #185FA5;
        }
        tr:hover {
            background: #f5f5f5;
        }
    </style>
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
                <li><a href="payments.php">Payments</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Parents</h1>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Parents</h3>
                    <div class="stat-number"><?php echo count($parents); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Parents</h3>
                    <div class="stat-number">
                        <?php 
                        $active = 0;
                        foreach($parents as $p) if($p['status'] == 'active') $active++;
                        echo $active;
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Pending Documents</h3>
                    <div class="stat-number">
                        <?php 
                        $pending_docs = 0;
                        foreach($parents as $p) $pending_docs += $p['pending_docs'];
                        echo $pending_docs;
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Parents Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Children</th>
                            <th>Documents</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($parents as $parent): ?>
                            <tr>
                                <td><?php echo $parent['id']; ?></a></td>
                                <td><strong><?php echo htmlspecialchars($parent['name']); ?></strong></a></td>
                                <td><?php echo htmlspecialchars($parent['email']); ?></a></td>
                                <td><?php echo htmlspecialchars($parent['phone']); ?></a></td>
                                <td><?php echo $parent['children_count']; ?></a></td>
                                <td>
                                    <?php if($parent['pending_docs'] > 0): ?>
                                        <span class="pending-badge"><?php echo $parent['pending_docs']; ?> pending</span>
                                    <?php else: ?>
                                        <span style="color:green;">✓ Verified</span>
                                    <?php endif; ?>
                                 </a>
                                <td>
                                    <span class="status-badge status-<?php echo $parent['status']; ?>">
                                        <?php echo ucfirst($parent['status']); ?>
                                    </span>
                                 </a>
                                <td>
                                    <a href="view_parent.php?id=<?php echo $parent['id']; ?>" class="btn-view">View Details</a>
                                 </a>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>