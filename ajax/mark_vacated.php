<?php
require_once '../config/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$booking_id = $data['booking_id'];

$stmt = $pdo->prepare("SELECT bed_id, tenant_id FROM bookings WHERE id = ?");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

$stmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
$stmt->execute([$booking_id]);

$stmt = $pdo->prepare("UPDATE beds SET status = 'available' WHERE id = ?");
$stmt->execute([$booking['bed_id']]);

echo json_encode(['success' => true, 'message' => 'Tenant marked as vacated']);
?>