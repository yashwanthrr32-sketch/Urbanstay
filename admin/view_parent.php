<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

$parent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($parent_id <= 0) {
    header('Location: parents.php');
    exit;
}

// Get parent details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'parent'");
$stmt->execute([$parent_id]);
$parent = $stmt->fetch();

if(!$parent) {
    header('Location: parents.php');
    exit;
}

// Get parent documents
$stmt = $pdo->prepare("SELECT * FROM user_documents WHERE user_id = ? AND user_role = 'parent'");
$stmt->execute([$parent_id]);
$documents = $stmt->fetchAll();

// Get linked children
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.phone 
    FROM parent_tenant pt
    JOIN users u ON pt.tenant_id = u.id
    WHERE pt.parent_id = ?
");
$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();

$profile_photo = $parent['profile_photo'] ? '../' . $parent['profile_photo'] : null;
$has_photo = $profile_photo && file_exists($profile_photo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Details - <?php echo htmlspecialchars($parent['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .details-container {
            max-width: 900px;
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
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
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
        .child-item {
            padding: 10px;
            background: white;
            border-radius: 5px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
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
                <li><a href="parents.php" class="active">Parents</a></li>
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
                            <?php echo strtoupper(substr($parent['name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1><?php echo htmlspecialchars($parent['name']); ?></h1>
                        <p><?php echo htmlspecialchars($parent['email']); ?></p>
                        <p><?php echo htmlspecialchars($parent['phone']); ?></p>
                        <span class="status-badge status-<?php echo $parent['status']; ?>">
                            Account: <?php echo ucfirst($parent['status']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Personal Information -->
                <div class="section">
                    <h3>Personal Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Full Name:</span>
                        <?php echo htmlspecialchars($parent['name']); ?>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <?php echo htmlspecialchars($parent['email']); ?>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <?php echo htmlspecialchars($parent['phone']); ?>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Registered On:</span>
                        <?php echo date('d M Y, h:i A', strtotime($parent['created_at'])); ?>
                    </div>
                </div>
                
                <!-- Linked Children -->
                <div class="section">
                    <h3>Linked Children</h3>
                    <?php if(count($children) > 0): ?>
                        <?php foreach($children as $child): ?>
                            <div class="child-item">
                                <strong><?php echo htmlspecialchars($child['name']); ?></strong><br>
                                <small>Email: <?php echo htmlspecialchars($child['email']); ?></small><br>
                                <small>Phone: <?php echo htmlspecialchars($child['phone']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No children linked yet.</p>
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
                
                <a href="parents.php" class="back-btn">← Back to Parents List</a>
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
    </script>
</body>
</html>