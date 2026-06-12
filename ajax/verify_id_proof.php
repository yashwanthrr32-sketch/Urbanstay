<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$booking_id = $data['booking_id'];
$action = $data['action'];

$stmt = $pdo->prepare("SELECT b.*, pg.manager_id FROM bookings b JOIN pgs pg ON b.pg_id = pg.id WHERE b.id = ?");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if(!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

if($booking['manager_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$personal_info = json_decode($booking['personal_info_json'], true);

if($action == 'verify') {
    $personal_info['id_proof_status'] = 'verified';
    $personal_info['verified_at'] = date('Y-m-d H:i:s');
    $personal_info['verified_by'] = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', approved_at = NOW(), personal_info_json = ? WHERE id = ?");
    $stmt->execute([json_encode($personal_info), $booking_id]);
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$booking['tenant_id'], "Your ID proof has been verified and your booking is confirmed! Welcome to your new PG."]);
    
    echo json_encode(['success' => true, 'message' => 'ID proof verified and booking confirmed']);
} else {
    $personal_info['id_proof_status'] = 'rejected';
    $personal_info['rejected_at'] = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("UPDATE bookings SET personal_info_json = ? WHERE id = ?");
    $stmt->execute([json_encode($personal_info), $booking_id]);
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$booking['tenant_id'], "Your ID proof was rejected. Please contact the PG manager for assistance."]);
    
    echo json_encode(['success' => true, 'message' => 'ID proof rejected. Tenant notified.']);
}
?>