<?php
require_once '../config/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$booking_id = $data['booking_id'];
$action = $data['action'];

if($action == 'approve') {
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', approved_at = NOW() WHERE id = ?");
    $stmt->execute([$booking_id]);
    
    // Get tenant_id for notification
    $stmt = $pdo->prepare("SELECT tenant_id FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, 'Your booking has been confirmed!')");
    $stmt->execute([$booking['tenant_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Booking approved']);
} else {
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$booking_id]);
    
    // Free the bed
    $stmt = $pdo->prepare("
        UPDATE beds SET status = 'available' 
        WHERE id = (SELECT bed_id FROM bookings WHERE id = ?)
    ");
    $stmt->execute([$booking_id]);
    
    echo json_encode(['success' => true, 'message' => 'Booking rejected']);
}
?>