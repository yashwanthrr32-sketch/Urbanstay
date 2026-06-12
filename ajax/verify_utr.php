<?php
require_once '../config/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$payment_id = $data['payment_id'];
$action = $data['action'];

$status = $action == 'verify' ? 'verified' : 'rejected';
$stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
$stmt->execute([$status, $payment_id]);

// Get tenant_id for notification
$stmt = $pdo->prepare("SELECT tenant_id FROM payments WHERE id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

$message = $action == 'verify' ? 'Your payment has been verified' : 'Your payment UTR was rejected. Please try again.';
$stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
$stmt->execute([$payment['tenant_id'], $message]);

echo json_encode(['success' => true, 'message' => 'UTR ' . $status]);
?>