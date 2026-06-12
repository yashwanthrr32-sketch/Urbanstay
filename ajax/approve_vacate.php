<?php
require_once '../config/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$request_id = $data['request_id'];

$stmt = $pdo->prepare("UPDATE vacate_requests SET status = 'approved', approved_at = NOW() WHERE id = ?");
$stmt->execute([$request_id]);

// Get booking and bed info
$stmt = $pdo->prepare("
    SELECT vr.tenant_id, vr.booking_id, b.bed_id 
    FROM vacate_requests vr
    JOIN bookings b ON vr.booking_id = b.id
    WHERE vr.id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

// Update booking status
$stmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
$stmt->execute([$request['booking_id']]);

// Free the bed
$stmt = $pdo->prepare("UPDATE beds SET status = 'available' WHERE id = ?");
$stmt->execute([$request['bed_id']]);

// Notify tenant
$stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, 'Your vacate request has been approved')");
$stmt->execute([$request['tenant_id']]);

echo json_encode(['success' => true, 'message' => 'Vacate approved']);
?>