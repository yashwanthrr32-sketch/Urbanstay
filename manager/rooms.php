<?php
require_once '../config/db.php';
$required_role = 'manager';
include '../includes/session-check.php';

$manager_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM pgs WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$pg = $stmt->fetch();
$pg_id = $pg['id'];

// Handle add room
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_room'])) {
    $room_number = $_POST['room_number'];
    $total_beds = $_POST['total_beds'];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO rooms (pg_id, room_number, total_beds) VALUES (?, ?, ?)");
        $stmt->execute([$pg_id, $room_number, $total_beds]);
        $room_id = $pdo->lastInsertId();
        
        for($i = 1; $i <= $total_beds; $i++) {
            $label = chr(64 + $i);
            $stmt = $pdo->prepare("INSERT INTO beds (room_id, bed_label, status) VALUES (?, ?, 'available')");
            $stmt->execute([$room_id, $label]);
        }
        $pdo->commit();
        $message = '<div class="success-message">Room added successfully!</div>';
    } catch(Exception $e) {
        $pdo->rollBack();
        $message = '<div class="error-message">Error adding room</div>';
    }
}

// Get rooms and beds
$stmt = $pdo->prepare("
    SELECT r.*, b.id as bed_id, b.bed_label, b.status as bed_status,
           u.name as tenant_name
    FROM rooms r
    LEFT JOIN beds b ON r.id = b.room_id
    LEFT JOIN bookings bk ON b.id = bk.bed_id AND bk.status IN ('processing', 'confirmed')
    LEFT JOIN users u ON bk.tenant_id = u.id
    WHERE r.pg_id = ?
    ORDER BY r.room_number, b.bed_label
");
$stmt->execute([$pg_id]);
$beds = $stmt->fetchAll();

$rooms = [];
foreach($beds as $bed) {
    if(!isset($rooms[$bed['room_number']])) {
        $rooms[$bed['room_number']] = [
            'room_id' => $bed['id'],
            'total_beds' => $bed['total_beds'],
            'beds' => []
        ];
    }
    $rooms[$bed['room_number']]['beds'][] = $bed;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms & Beds - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .room-card {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .beds-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .bed {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-align: center;
            min-width: 60px;
        }
        .bed.available { background: #28a745; color: white; }
        .bed.occupied { background: #dc3545; color: white; cursor: pointer; }
        .bed-info { font-size: 0.75rem; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="tenants.php">Tenants</a></li>
                <li><a href="rooms.php" class="active">Rooms & Beds</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="complaints.php">Complaints</a></li>
<li><a href="pg-images.php">PG Images</a></li>
<li><a href="profile.php">My Profile</a></li>

                <li><a href="pg-settings.php">PG Settings</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Rooms & Beds Layout</h1>
            
            <?php echo $message ?? ''; ?>
            
            <div class="add-room-form">
                <h2>Add New Room</h2>
                <form method="POST" style="display:flex; gap:1rem; align-items:flex-end;">
                    <div class="form-group">
                        <label>Room Number</label>
                        <input type="text" name="room_number" required>
                    </div>
                    <div class="form-group">
                        <label>Number of Beds</label>
                        <select name="total_beds">
                            <option value="1">1 Bed (Single)</option>
                            <option value="2">2 Beds (Double Sharing)</option>
                            <option value="3">3 Beds (Triple Sharing)</option>
                            <option value="4">4 Beds (4 Sharing)</option>
                        </select>
                    </div>
                    <button type="submit" name="add_room" class="btn-primary">Add Room</button>
                </form>
            </div>
            
            <div class="rooms-list">
                <h2>Current Rooms</h2>
                <?php foreach($rooms as $roomNum => $room): ?>
                    <div class="room-card">
                        <h3>Room <?php echo htmlspecialchars($roomNum); ?></h3>
                        <div class="beds-grid">
                            <?php foreach($room['beds'] as $bed): ?>
                                <div class="bed <?php echo $bed['bed_status']; ?>" 
                                     onclick="<?php echo $bed['bed_status'] == 'occupied' ? "showTenantInfo('" . addslashes($bed['tenant_name']) . "')" : ''; ?>">
                                    Bed <?php echo $bed['bed_label']; ?>
                                    <?php if($bed['bed_status'] == 'occupied' && $bed['tenant_name']): ?>
                                        <div class="bed-info"><?php echo htmlspecialchars($bed['tenant_name']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if($bed['bed_status'] == 'occupied'): ?>
                            <button onclick="freeBed(<?php echo $bed['bed_id']; ?>)" class="btn-danger" style="margin-top:1rem;">Free Bed</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div id="tenantModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Tenant Information</h2>
            <p id="tenantInfo"></p>
        </div>
    </div>
    
    <script>
        function showTenantInfo(name) {
            document.getElementById('tenantInfo').innerHTML = 'Tenant: ' + name;
            document.getElementById('tenantModal').style.display = 'block';
        }
        
        function freeBed(bedId) {
            if(confirm('Free this bed? The tenant will be marked as vacated.')) {
                fetch('../ajax/free_bed.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({bed_id: bedId})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) location.reload();
                    else alert(data.message);
                });
            }
        }
        
        function closeModal() {
            document.getElementById('tenantModal').style.display = 'none';
        }
    </script>
</body>
</html>