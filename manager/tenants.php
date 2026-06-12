<?php
require_once '../config/db.php';
$required_role = 'manager';
include '../includes/session-check.php';

$manager_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, name FROM pgs WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$pg = $stmt->fetch();

if(!$pg) {
    echo "<div class='container'><h1>No PG found. Please contact admin.</h1></div>";
    exit;
}

$pg_id = $pg['id'];
$pg_name = $pg['name'];

// Get all pending and confirmed tenants
$stmt = $pdo->prepare("
    SELECT 
        b.id as booking_id, 
        u.id as user_id, 
        u.name, 
        u.email, 
        u.phone,
        r.room_number, 
        bed.bed_label, 
        td.rent_amount, 
        td.due_date,
        pt.parent_id,
        parent.name as parent_name,
        parent.email as parent_email,
        parent.phone as parent_phone,
        b.personal_info_json,
        b.status as booking_status,
        b.requested_at
    FROM bookings b
    JOIN users u ON b.tenant_id = u.id
    JOIN beds bed ON b.bed_id = bed.id
    JOIN rooms r ON bed.room_id = r.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    LEFT JOIN parent_tenant pt ON u.id = pt.tenant_id
    LEFT JOIN users parent ON pt.parent_id = parent.id
    WHERE b.pg_id = ? AND b.status IN ('processing', 'confirmed')
    ORDER BY FIELD(b.status, 'processing', 'confirmed'), b.requested_at ASC
");
$stmt->execute([$pg_id]);
$tenants = $stmt->fetchAll();

$all_parents = $pdo->query("SELECT id, name, email, phone FROM users WHERE role = 'parent' ORDER BY name")->fetchAll();
$message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['set_rent'])) {
        $booking_id = $_POST['booking_id'];
        $rent_amount = $_POST['rent_amount'];
        $due_date = $_POST['due_date'];
        $stmt = $pdo->prepare("INSERT INTO tenant_details (booking_id, rent_amount, due_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rent_amount = ?, due_date = ?");
        $stmt->execute([$booking_id, $rent_amount, $due_date, $rent_amount, $due_date]);
        $message = '<div class="success-message">Rent details updated!</div>';
        header('Location: tenants.php?updated=1');
        exit;
    }
    
    if(isset($_POST['link_parent'])) {
        $tenant_id = $_POST['tenant_id'];
        $parent_id = $_POST['parent_id'];
        if($parent_id) {
            $stmt = $pdo->prepare("INSERT INTO parent_tenant (parent_id, tenant_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE parent_id = ?");
            $stmt->execute([$parent_id, $tenant_id, $parent_id]);
            $message = '<div class="success-message">Parent linked!</div>';
            header('Location: tenants.php?linked=1');
            exit;
        }
    }
}

if(isset($_GET['unlink'])) {
    $tenant_id = (int)$_GET['unlink'];
    $stmt = $pdo->prepare("DELETE FROM parent_tenant WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    header('Location: tenants.php?unlinked=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Tenants - <?php echo htmlspecialchars($pg_name); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 25px;
            width: 90%;
            max-width: 700px;
            border-radius: 10px;
            position: relative;
        }
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            cursor: pointer;
        }
        .detail-row { padding: 8px; border-bottom: 1px solid #eee; }
        .detail-label { font-weight: bold; display: inline-block; width: 160px; color: #185FA5; }
        .btn-view { background: #17a2b8; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; display: inline-block; margin: 2px; }
        .btn-success { background: #28a745; color: white; padding: 5px 10px; border-radius: 5px; border: none; cursor: pointer; margin: 2px; }
        .btn-danger { background: #dc3545; color: white; padding: 5px 10px; border-radius: 5px; border: none; cursor: pointer; margin: 2px; }
        .btn-primary { background: #185FA5; color: white; padding: 5px 10px; border-radius: 5px; border: none; cursor: pointer; margin: 2px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .status-processing { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .parent-linked { background: #e8f4fd; padding: 8px; border-radius: 5px; }
        .id-proof-status { font-size: 12px; margin-top: 5px; }
        .verified { color: #28a745; }
        .pending { color: #ffc107; }
        .rejected { color: #dc3545; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #185FA5; }
        tr:hover { background: #f5f5f5; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="sidebar">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="tenants.php" class="active">Tenants</a></li>
                <li><a href="rooms.php">Rooms & Beds</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="complaints.php">Complaints</a></li>
                <li><a href="pg-images.php">PG Images</a></li>
                <li><a href="pg-settings.php">PG Settings</a></li>
            </ul>
        </div>
        
        <div class="main-content">
            <h1>Manage Tenants - <?php echo htmlspecialchars($pg_name); ?></h1>
            <?php if(isset($_GET['updated'])) echo '<div class="success-message">Rent details updated!</div>'; ?>
            <?php if(isset($_GET['linked'])) echo '<div class="success-message">Parent linked!</div>'; ?>
            <?php if(isset($_GET['unlinked'])) echo '<div class="success-message">Parent unlinked!</div>'; ?>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Tenant</th><th>Room/Bed</th><th>Rent</th><th>Due Date</th><th>Parent</th><th>ID Proof</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($tenants as $tenant): 
                            $personal_info = json_decode($tenant['personal_info_json'], true);
                            $id_proof_status = $personal_info['id_proof_status'] ?? 'pending';
                            $id_proof_document = $personal_info['id_proof_document'] ?? null;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($tenant['name']); ?></strong><br>
                                    <small><?php echo $tenant['email']; ?></small><br>
                                    <small><?php echo $tenant['phone']; ?></small>
                                </td>
                                <td>Room <?php echo $tenant['room_number']; ?>, Bed <?php echo $tenant['bed_label']; ?> </a>
                                <td>
                                    <form method="POST" style="display:flex; gap:5px; flex-wrap:wrap;">
                                        <input type="hidden" name="booking_id" value="<?php echo $tenant['booking_id']; ?>">
                                        <input type="number" name="rent_amount" value="<?php echo $tenant['rent_amount']; ?>" style="width:100px; padding:5px;">
                                        <input type="date" name="due_date" value="<?php echo $tenant['due_date']; ?>" style="padding:5px;">
                                        <button type="submit" name="set_rent" class="btn-primary">Set</button>
                                    </form>
                                 </a>
                                <td><?php echo $tenant['due_date'] ?? 'Not set'; ?> </a>
                                <td>
                                    <?php if($tenant['parent_id']): ?>
                                        <div class="parent-linked">
                                            <?php echo htmlspecialchars($tenant['parent_name']); ?><br>
                                            <small><?php echo $tenant['parent_email']; ?></small><br>
                                            <a href="?unlink=<?php echo $tenant['user_id']; ?>" class="btn-danger" style="font-size:11px;" onclick="return confirm('Unlink parent?')">Unlink</a>
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" style="display:flex; gap:5px;">
                                            <input type="hidden" name="tenant_id" value="<?php echo $tenant['user_id']; ?>">
                                            <select name="parent_id" style="padding:5px;">
                                                <option value="">Select Parent</option>
                                                <?php foreach($all_parents as $parent): ?>
                                                    <option value="<?php echo $parent['id']; ?>"><?php echo htmlspecialchars($parent['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="link_parent" class="btn-primary">Link</button>
                                        </form>
                                    <?php endif; ?>
                                 </a>
                                <td>
                                    <?php if($id_proof_document): ?>
                                        <a href="../<?php echo $id_proof_document; ?>" target="_blank" class="btn-view">📄 View ID Proof</a>
                                        <div class="id-proof-status">
                                            <?php if($id_proof_status == 'pending'): ?>
                                                <span class="pending">⏳ Pending Verification</span><br>
                                                <button onclick="verifyIdProof(<?php echo $tenant['booking_id']; ?>, 'verify')" class="btn-success" style="margin-top:5px;">✓ Verify</button>
                                                <button onclick="verifyIdProof(<?php echo $tenant['booking_id']; ?>, 'reject')" class="btn-danger" style="margin-top:5px;">✗ Reject</button>
                                            <?php elseif($id_proof_status == 'verified'): ?>
                                                <span class="verified">✓ ID Verified</span>
                                            <?php else: ?>
                                                <span class="rejected">✗ ID Rejected</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:red;">No document uploaded</span>
                                    <?php endif; ?>
                                 </a>
                                <td>
                                    <span class="status-badge status-<?php echo $tenant['booking_status']; ?>">
                                        <?php echo ucfirst($tenant['booking_status']); ?>
                                    </span>
                                 </a>
                                <td>
                                    <a href="view_tenant.php?id=<?php echo $tenant['user_id']; ?>" class="btn-view">View Details</a>
                                    <button onclick="markVacated(<?php echo $tenant['booking_id']; ?>)" class="btn-danger">Vacate</button>
                                 </a>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="tenantModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal()">&times;</span><h2>Tenant Details</h2><div id="tenantDetails"></div></div></div>
    
    <script>
        function verifyIdProof(bookingId, action) {
            if(confirm(action === 'verify' ? 'Verify this ID proof? The tenant will be confirmed.' : 'Reject this ID proof? The tenant will need to resubmit.')) {
                fetch('../ajax/verify_id_proof.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({booking_id: bookingId, action: action})
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) { alert(data.message); location.reload(); }
                    else alert('Error: ' + data.message);
                });
            }
        }
        
        function markVacated(bookingId) {
            if(confirm('Mark this tenant as vacated?')) {
                fetch('../ajax/mark_vacated.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({booking_id: bookingId})
                })
                .then(response => response.json())
                .then(data => { if(data.success) location.reload(); else alert(data.message); });
            }
        }
        
        function closeModal() { document.getElementById('tenantModal').style.display = 'none'; }
        window.onclick = function(event) { if(event.target === document.getElementById('tenantModal')) closeModal(); }
    </script>
</body>
</html>