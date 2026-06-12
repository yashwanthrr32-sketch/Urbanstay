<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db.php';
header('Content-Type: application/json');

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$document_id = isset($data['document_id']) ? (int)$data['document_id'] : 0;
$action = isset($data['action']) ? $data['action'] : '';

if($document_id <= 0 || !in_array($action, ['verify', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$status = ($action == 'verify') ? 'verified' : 'rejected';
$admin_id = $_SESSION['user_id'];

// Update document status
$stmt = $pdo->prepare("UPDATE user_documents SET status = ?, verified_at = NOW(), verified_by = ? WHERE id = ?");
$stmt->execute([$status, $admin_id, $document_id]);

if($stmt->rowCount() > 0) {
    echo json_encode(['success' => true, 'message' => 'Document ' . $status . ' successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update document status']);
}
?>