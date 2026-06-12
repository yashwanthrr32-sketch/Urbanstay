<?php
require_once '../config/db.php';
$required_role = 'tenant';
include '../includes/session-check.php';

$bed_id = isset($_GET['bed_id']) ? (int)$_GET['bed_id'] : 0;
$pg_id = isset($_GET['pg_id']) ? (int)$_GET['pg_id'] : 0;

// Get bed and PG details
$stmt = $pdo->prepare("
    SELECT b.*, r.room_number, pg.name as pg_name, pg.address 
    FROM beds b
    JOIN rooms r ON b.room_id = r.id
    JOIN pgs pg ON r.pg_id = pg.id
    WHERE b.id = ? AND b.status = 'available'
");
$stmt->execute([$bed_id]);
$bed = $stmt->fetch();

if(!$bed) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $id_type = $_POST['id_type'];
    $id_number = $_POST['id_number'];
    $address = $_POST['address'];
    $emergency_name = $_POST['emergency_name'];
    $emergency_phone = $_POST['emergency_phone'];
    
    // Handle photo upload
    $profile_photo = '';
    if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = '../assets/images/uploads/';
        $filename = time() . '_' . $_FILES['profile_photo']['name'];
        move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $filename);
        $profile_photo = 'assets/images/uploads/' . $filename;
    }
    
    $personal_info = json_encode([
        'full_name' => $full_name,
        'phone' => $phone,
        'id_type' => $id_type,
        'id_number' => $id_number,
        'address' => $address,
        'profile_photo' => $profile_photo,
        'emergency_name' => $emergency_name,
        'emergency_phone' => $emergency_phone
    ]);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO bookings (tenant_id, bed_id, pg_id, personal_info_json, status, requested_at) VALUES (?, ?, ?, ?, 'processing', NOW())");
        $stmt->execute([$_SESSION['user_id'], $bed_id, $pg_id, $personal_info]);
        
        $stmt = $pdo->prepare("UPDATE beds SET status = 'occupied' WHERE id = ?");
        $stmt->execute([$bed_id]);
        
        $pdo->commit();
        
        header('Location: dashboard.php?booking_success=1');
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Booking failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Booking - Urban Stay</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="booking-confirmation">
            <h1>Complete Your Booking</h1>
            
            <div class="booking-details">
                <h3>PG: <?php echo htmlspecialchars($bed['pg_name']); ?></h3>
                <p>Room: <?php echo $bed['room_number']; ?> | Bed: <?php echo $bed['bed_label']; ?></p>
                <p>Address: <?php echo htmlspecialchars($bed['address']); ?></p>
            </div>
            
            <?php if($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="phone" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Govt ID Type *</label>
                        <select name="id_type" required>
                            <option value="">Select</option>
                            <option value="Aadhar">Aadhar Card</option>
                            <option value="PAN">PAN Card</option>
                            <option value="Driving License">Driving License</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Govt ID Number *</label>
                        <input type="text" name="id_number" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Current Address *</label>
                    <textarea name="address" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Profile Photo *</label>
                    <input type="file" name="profile_photo" accept="image/*" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Emergency Contact Name *</label>
                        <input type="text" name="emergency_name" required>
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Phone *</label>
                        <input type="tel" name="emergency_phone" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">Confirm Booking</button>
                <a href="../pg-detail.php?id=<?php echo $pg_id; ?>" class="btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>