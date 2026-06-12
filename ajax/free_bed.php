<?php
require_once '../config/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$bed_id = $data['bed_id'];

$stmt = $pdo->prepare("UPDATE beds SET status = 'available' WHERE id = ?");
$stmt->execute([$bed_id]);

echo json_encode(['success' => true, 'message' => 'Bed freed successfully']);
?>