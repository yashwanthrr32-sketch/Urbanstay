<?php
require_once '../config/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$pg_id = $data['pg_id'];

$stmt = $pdo->prepare("DELETE FROM pgs WHERE id = ?");
$stmt->execute([$pg_id]);

echo json_encode(['success' => true, 'message' => 'PG removed successfully']);
?>