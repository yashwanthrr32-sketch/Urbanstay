<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

$manager_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($manager_id <= 0) {
    header('Location: managers.php');
    exit;
}

// Get manager details
$stmt = $pdo->prepare("
    SELECT u.*, p.id as pg_id, p.name as pg_name, p.address as pg_address, 
           p.status as pg_status, p.created_at as pg_created
    FROM users u
    LEFT JOIN pgs p ON u.id = p.manager_id
    WHERE u.id = ? AND u.role = 'manager'
");
$stmt->execute([$manager_id]);
$manager = $stmt->fetch();

if(!$manager) {
    header('Location: managers.php');
    exit;
}

// Get manager documents
$stmt = $pdo->prepare("SELECT * FROM user_documents WHERE user_id = ? AND user_role = 'manager'");
$stmt->execute([$manager_id]);
$documents = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM bookings b JOIN pgs pg ON b.pg_id = pg.id WHERE pg.manager_id = ? AND b.status = 'confirmed') as total_tenants,
        (SELECT COUNT(*) FROM rooms r JOIN pgs pg ON r.pg_id = pg.id WHERE pg.manager_id = ?) as total_rooms,
        (SELECT COUNT(*) FROM complaints c JOIN pgs pg ON c.pg_id = pg.id WHERE pg.manager_id = ? AND c.status = 'open') as open_complaints
");
$stmt->execute([$manager_id, $manager_id, $manager_id]);
$stats = $stmt->fetch();

$profile_photo = $manager['profile_photo'] ? '../' . $manager['profile_photo'] : null;
$has_photo = $profile_photo && file_exists($profile_photo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Details - <?php echo htmlspecialchars($manager['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .details-container {
            max-width: 1000px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #185FA5;
            flex-wrap: wrap;
        }
        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #185FA5;
        }
        .profile-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #185FA5 0%, #0d3b66 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            color: white;
            border: 3px solid #185FA5;
        }
        .section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section h3 {
            color: #185FA5;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .detail-row {
            margin-bottom: 12px;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            display: inline-block;
            width: 160px;
            color: #185FA5;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 28px;
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
        .status-approved { background: #d4edda; color: #155724; }
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
        }
        .document-info {
            flex: 1;
        }
        .document-actions {
            display: flex;
            gap: 10px;
        }
        .btn-view {
            background: #17a2b8;
            color: white;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
        }
        .btn-verify {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        .back-btn {
            background: #185FA5;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-approve-manager {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        .btn-reject-manager {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="managers.php" class="active">PG Managers</a></li>
                <li><a href="parents.php">Parents</a></li>
                <li><a href="tenants.php">All Tenants</a></li>
                <li><a href="pg-listings.php">PG Listings</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="payments.php">Payments</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <div class="details-container">
                <div class="header">
                    <?php if($has_photo): ?>
                        <img src="<?php echo $profile_photo; ?>" class="profile-photo" alt="Profile Photo">
                    <?php else: ?>
                        <div class="profile-placeholder">
                            <?php echo strtoupper(substr($manager['name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1><?php echo htmlspecialchars($manager['name']); ?></h1>
                        <p><?php echo htmlspecialchars($manager['email']); ?></p>
                        <p><?php echo htmlspecialchars($manager['phone']); ?></p>
                        <span class="status-badge status-<?php echo $manager['status']; ?>">
                            Account: <?php echo ucfirst($manager['status']); ?>
                        </span>
                        <?php if($manager['status'] == 'pending'): ?>
                            <div style="margin-top: 15px;">
                                <button onclick="updateManagerStatus(<?php echo $manager['id']; ?>, 'approve')" class="btn-approve-manager">✓ Approve Manager</button>
                                <button onclick="updateManagerStatus(<?php echo $manager['id']; ?>, 'reject')" class="btn-reject-manager">✗ Reject Manager</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total_tenants']; ?></div>
                        <div class="label">Total Tenants</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total_rooms']; ?></div>
                        <div class="label">Total Rooms</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['open_complaints']; ?></div>
                        <div class="label">Open Complaints</div>
                    </div>
                </div>
                
                <!-- Personal Information -->
                <div class="section">
                    <h3>Personal Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Full Name:</span>
                        <?php echo htmlspecialchars($manager['name']); ?>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <?php echo htmlspecialchars($manager['email']); ?>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <?php echo htmlspecialchars($manager['phone']); ?>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Registered On:</span>
                        <?php echo date('d M Y, h:i A', strtotime($manager['created_at'])); ?>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Security Question:</span>
                        <?php echo htmlspecialchars($manager['security_question']); ?>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Security Answer:</span>
                        <?php echo htmlspecialchars($manager['security_answer']); ?>
                    </div>
                </div>
                
                <!-- PG Information -->
                <div class="section">
                    <h3>PG Information</h3>
                    <?php if($manager['pg_id']): ?>
                        <div class="detail-row">
                            <span class="detail-label">PG Name:</span>
                            <?php echo htmlspecialchars($manager['pg_name']); ?>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">PG Address:</span>
                            <?php echo nl2br(htmlspecialchars($manager['pg_address'])); ?>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">PG Status:</span>
                            <span class="status-badge status-<?php echo $manager['pg_status']; ?>">
                                <?php echo ucfirst($manager['pg_status']); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">PG Created:</span>
                            <?php echo date('d M Y', strtotime($manager['pg_created'])); ?>
                        </div>
                    <?php else: ?>
                        <p>No PG assigned yet.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Uploaded Documents Section with Verification -->
                <div class="section">
                    <h3>📄 Uploaded Documents (ID Proof & Address Proof)</h3>
                    <?php if(count($documents) > 0): ?>
                        <?php foreach($documents as $doc): ?>
                            <div class="document-item" id="doc-<?php echo $doc['id']; ?>">
                                <div class="document-info">
                                    <strong><?php echo htmlspecialchars($doc['document_type']); ?></strong><br>
                                    <small>Uploaded: <?php echo date('d M Y, h:i A', strtotime($doc['uploaded_at'])); ?></small><br>
                                    <span class="status-badge status-<?php echo $doc['status']; ?>">
                                        Current Status: <?php echo ucfirst($doc['status']); ?>
                                    </span>
                                </div>
                                <div class="document-actions">
                                    <a href="../<?php echo $doc['document_path']; ?>" target="_blank" class="btn-view">View Document</a>
                                    <?php if($doc['status'] == 'pending'): ?>
                                        <button onclick="verifyDocument(<?php echo $doc['id']; ?>, 'verify')" class="btn-verify">✓ Verify</button>
                                        <button onclick="verifyDocument(<?php echo $doc['id']; ?>, 'reject')" class="btn-reject">✗ Reject</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No documents uploaded yet.</p>
                    <?php endif; ?>
                </div>
                
                <a href="managers.php" class="back-btn">← Back to Managers List</a>
            </div>
        </div>
    </div>
    
    <script>
        // Function to verify/reject documents
        function verifyDocument(docId, action) {
            if(confirm(action === 'verify' ? 'Verify this document?' : 'Reject this document?')) {
                fetch('../ajax/verify_document.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({document_id: docId, action: action})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
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
        
        // Function to approve/reject manager
        function updateManagerStatus(managerId, action) {
            if(confirm(action === 'approve' ? 'Approve this manager?' : 'Reject this manager?')) {
                fetch('../ajax/approve_manager.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({user_id: managerId, action: action})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert(data.message);
                        window.location.href = 'managers.php';
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