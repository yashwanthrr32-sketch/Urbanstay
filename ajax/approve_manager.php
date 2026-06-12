<?php
require_once '../config/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'];
$action = $data['action'];

if($action == 'approve') {
    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Also approve the PG
    $stmt = $pdo->prepare("UPDATE pgs SET status = 'approved' WHERE manager_id = ?");
    $stmt->execute([$user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Manager approved']);
} elseif($action == 'reject') {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true, 'message' => 'Manager rejected']);
} elseif($action == 'deactivate') {
    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true, 'message' => 'Manager deactivated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>