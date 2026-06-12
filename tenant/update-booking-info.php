<?php
require_once '../config/db.php';
$required_role = 'tenant';
include '../includes/session-check.php';

$tenant_id = $_SESSION['user_id'];
$message = '';

// Get current booking info
$stmt = $pdo->prepare("
    SELECT b.id, b.personal_info_json, pg.name as pg_name
    FROM bookings b
    JOIN pgs pg ON b.pg_id = pg.id
    WHERE b.tenant_id = ? AND b.status IN ('processing', 'confirmed')
    ORDER BY b.requested_at DESC LIMIT 1
");
$stmt->execute([$tenant_id]);
$booking = $stmt->fetch();

if(!$booking) {
    header('Location: dashboard.php');
    exit;
}

$personal_info = json_decode($booking['personal_info_json'], true);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $updated_info = [
        'full_name' => $_POST['full_name'],
        'phone' => $_POST['phone'],
        'id_type' => $personal_info['id_type'],
        'id_number' => $personal_info['id_number'],
        'address' => $_POST['address'],
        'profile_photo' => $personal_info['profile_photo'],
        'emergency_name' => $_POST['emergency_name'],
        'emergency_phone' => $_POST['emergency_phone']
    ];
    
    // Handle new photo upload
    if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = '../assets/images/uploads/';
        $filename = time() . '_' . $_FILES['profile_photo']['name'];
        move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $filename);
        $updated_info['profile_photo'] = 'assets/images/uploads/' . $filename;
    }
    
    $stmt = $pdo->prepare("UPDATE bookings SET personal_info_json = ? WHERE id = ?");
    $stmt->execute([json_encode($updated_info), $booking['id']]);
    $message = '<div class="success-message">Information updated successfully!</div>';
    $personal_info = $updated_info;
}
?>