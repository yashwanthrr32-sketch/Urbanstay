<?php
require_once '../config/db.php';
$required_role = 'admin';
include '../includes/session-check.php';

$pg_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($pg_id <= 0) {
    header('Location: pg-listings.php');
    exit;
}

// Get PG details with manager info
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.id as manager_id,
        u.name as manager_name,
        u.email as manager_email,
        u.phone as manager_phone,
        u.status as manager_status,
        (SELECT COUNT(*) FROM rooms WHERE pg_id = p.id) as total_rooms,
        (SELECT COUNT(*) FROM beds b JOIN rooms r ON b.room_id = r.id WHERE r.pg_id = p.id AND b.status = 'occupied') as occupied_beds,
        (SELECT COUNT(*) FROM beds b JOIN rooms r ON b.room_id = r.id WHERE r.pg_id = p.id AND b.status = 'available') as available_beds,
        (SELECT COUNT(*) FROM bookings WHERE pg_id = p.id AND status = 'confirmed') as total_tenants,
        (SELECT COUNT(*) FROM complaints WHERE pg_id = p.id AND status = 'open') as open_complaints,
        (SELECT AVG(rating) FROM feedback WHERE pg_id = p.id) as avg_rating,
        (SELECT COUNT(*) FROM feedback WHERE pg_id = p.id) as total_reviews
    FROM pgs p
    LEFT JOIN users u ON p.manager_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$pg_id]);
$pg = $stmt->fetch();

if(!$pg) {
    header('Location: pg-listings.php');
    exit;
}

// Get PG images
$stmt = $pdo->prepare("SELECT image_path FROM pg_images WHERE pg_id = ?");
$stmt->execute([$pg_id]);
$images = $stmt->fetchAll();

// Parse amenities
$amenities_list = [];
if($pg['amenities']) {
    $amenities_list = explode(',', $pg['amenities']);
}

// Get recent bookings
$stmt = $pdo->prepare("
    SELECT b.*, u.name as tenant_name, u.email as tenant_email, r.room_number, bed.bed_label
    FROM bookings b
    JOIN users u ON b.tenant_id = u.id
    JOIN beds bed ON b.bed_id = bed.id
    JOIN rooms r ON bed.room_id = r.id
    WHERE b.pg_id = ? AND b.status = 'confirmed'
    ORDER BY b.requested_at DESC LIMIT 5
");
$stmt->execute([$pg_id]);
$recent_bookings = $stmt->fetchAll();

// Get recent complaints
$stmt = $pdo->prepare("
    SELECT c.*, u.name as tenant_name
    FROM complaints c
    JOIN users u ON c.tenant_id = u.id
    WHERE c.pg_id = ?
    ORDER BY c.created_at DESC LIMIT 5
");
$stmt->execute([$pg_id]);
$recent_complaints = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PG Details - <?php echo htmlspecialchars($pg['name']); ?> - Urban Stay</title>
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
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #185FA5;
        }
        .header h1 {
            color: #185FA5;
            margin-bottom: 10px;
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
            width: 180px;
            color: #185FA5;
        }
        .detail-value {
            display: inline-block;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-active { background: #d4edda; color: #155724; }
        .amenity-tag {
            display: inline-block;
            background: #e0e7ff;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            margin: 3px;
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
            margin-top: 20px;
        }
        .back-btn:hover {
            background: #0d3b66;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .btn-danger:hover {
            background: #c82333;
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #185FA5;
        }
        .stat-card .label {
            font-size: 12px;
            color: #666;
        }
        .image-gallery {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .gallery-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        .rating {
            color: #f5a623;
            font-size: 18px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #e9ecef;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="details-container">
        <div class="header">
            <h1>🏠 PG Details</h1>
            <p><?php echo htmlspecialchars($pg['name']); ?></p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $pg['total_tenants']; ?></div>
                <div class="label">Total Tenants</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $pg['total_rooms']; ?></div>
                <div class="label">Total Rooms</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $pg['available_beds']; ?></div>
                <div class="label">Available Beds</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $pg['occupied_beds']; ?></div>
                <div class="label">Occupied Beds</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $pg['open_complaints']; ?></div>
                <div class="label">Open Complaints</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo round($pg['avg_rating'], 1); ?></div>
                <div class="label">Rating (<?php echo $pg['total_reviews']; ?> reviews)</div>
            </div>
        </div>
        
        <!-- PG Information -->
        <div class="section">
            <h3>📋 PG Information</h3>
            <div class="detail-row">
                <span class="detail-label">PG Name:</span>
                <span class="detail-value"><?php echo htmlspecialchars($pg['name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Address:</span>
                <span class="detail-value"><?php echo nl2br(htmlspecialchars($pg['address'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">PG Type:</span>
                <span class="detail-value"><?php echo htmlspecialchars($pg['type']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Price per Month:</span>
                <span class="detail-value">₹<?php echo number_format($pg['price_per_month']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">PG Status:</span>
                <span class="detail-value">
                    <span class="status-badge status-<?php echo $pg['status']; ?>">
                        <?php echo strtoupper($pg['status']); ?>
                    </span>
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created On:</span>
                <span class="detail-value"><?php echo date('d M Y', strtotime($pg['created_at'])); ?></span>
            </div>
            <?php if($pg['upi_id']): ?>
            <div class="detail-row">
                <span class="detail-label">UPI ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($pg['upi_id']); ?></span>
            </div>
            <?php endif; ?>
            <?php if(!empty($amenities_list)): ?>
                <div class="detail-row">
                    <span class="detail-label">Amenities:</span>
                    <span class="detail-value">
                        <?php foreach($amenities_list as $amenity): ?>
                            <?php if(trim($amenity)): ?>
                                <span class="amenity-tag">✓ <?php echo htmlspecialchars(trim($amenity)); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Manager Information -->
        <div class="section">
            <h3>👤 Manager Information</h3>
            <?php if($pg['manager_id']): ?>
                <div class="detail-row">
                    <span class="detail-label">Manager Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pg['manager_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Manager Email:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pg['manager_email']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Manager Phone:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pg['manager_phone']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Manager Status:</span>
                    <span class="detail-value">
                        <span class="status-badge status-<?php echo $pg['manager_status']; ?>">
                            <?php echo strtoupper($pg['manager_status']); ?>
                        </span>
                    </span>
                </div>
            <?php else: ?>
                <p style="color: #999; text-align: center;">No manager assigned to this PG yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- PG Images -->
        <?php if(count($images) > 0): ?>
        <div class="section">
            <h3>🖼️ PG Gallery</h3>
            <div class="image-gallery">
                <?php foreach($images as $image): ?>
                    <img src="../<?php echo $image['image_path']; ?>" class="gallery-image" alt="PG Image">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Bookings -->
        <?php if(count($recent_bookings) > 0): ?>
        <div class="section">
            <h3>📅 Recent Tenants</h3>
            <table>
                <thead>
                    <tr><th>Tenant Name</th><th>Room/Bed</th><th>Booked On</th></tr>
                </thead>
                <tbody>
                    <?php foreach($recent_bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['tenant_name']); ?></td>
                            <td>Room <?php echo $booking['room_number']; ?>, Bed <?php echo $booking['bed_label']; ?></td>
                            <td><?php echo date('d M Y', strtotime($booking['requested_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Recent Complaints -->
        <?php if(count($recent_complaints) > 0): ?>
        <div class="section">
            <h3>📋 Recent Complaints</h3>
            <table>
                <thead>
                    <tr><th>Tenant</th><th>Category</th><th>Description</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach($recent_complaints as $complaint): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($complaint['tenant_name']); ?></td>
                            <td><?php echo htmlspecialchars($complaint['category']); ?></td>
                            <td><?php echo htmlspecialchars(substr($complaint['description'], 0, 50)); ?>...</td>
                            <td><?php echo ucfirst($complaint['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 15px; justify-content: center;">
            <a href="pg-listings.php" class="back-btn">← Back to PG Listings</a>
            <a href="javascript:void(0)" onclick="removePG(<?php echo $pg['id']; ?>)" class="btn-danger">🗑️ Remove PG</a>
        </div>
    </div>
    
    <script>
        function removePG(pgId) {
            if(confirm('Are you sure you want to remove this PG? This action cannot be undone and will delete all related data (rooms, beds, bookings, etc.).')) {
                fetch('../ajax/remove_pg.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({pg_id: pgId})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert('PG removed successfully');
                        window.location.href = 'pg-listings.php';
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