<?php
require_once '../config/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tenant') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$tenant_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? null;

if(!$booking_id) {
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE tenant_id = ? AND status = 'confirmed' ORDER BY requested_at DESC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $booking = $stmt->fetch();
    $booking_id = $booking['id'];
}

// Check if request already exists
$stmt = $pdo->prepare("SELECT id FROM vacate_requests WHERE tenant_id = ? AND status = 'pending'");
$stmt->execute([$tenant_id]);
if($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Vacate request already pending']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO vacate_requests (tenant_id, booking_id, status) VALUES (?, ?, 'pending')");
$stmt->execute([$tenant_id, $booking_id]);

echo json_encode(['success' => true, 'message' => 'Vacate request submitted']);
?>