<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'parent') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$child_id = isset($data['child_id']) ? (int)$data['child_id'] : 0;
$parent_id = $_SESSION['user_id'];

if($child_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid child ID']);
    exit;
}

// Verify this child is linked to the parent
$stmt = $pdo->prepare("
    SELECT tenant_id FROM parent_tenant WHERE parent_id = ? AND tenant_id = ?
");
$stmt->execute([$parent_id, $child_id]);
if(!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'This child is not linked to your account']);
    exit;
}

// Check if there's already a pending request
$stmt = $pdo->prepare("
    SELECT id FROM unlink_requests WHERE parent_id = ? AND tenant_id = ? AND status = 'pending'
");
$stmt->execute([$parent_id, $child_id]);
if($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending unlink request for this child']);
    exit;
}

// Create unlink request
$stmt = $pdo->prepare("
    INSERT INTO unlink_requests (parent_id, tenant_id, status, requested_at) 
    VALUES (?, ?, 'pending', NOW())
");
$stmt->execute([$parent_id, $child_id]);

// Get child name and manager ID for notification
$stmt = $pdo->prepare("
    SELECT u.name as child_name, pg.manager_id 
    FROM users u
    JOIN bookings b ON u.id = b.tenant_id
    JOIN pgs pg ON b.pg_id = pg.id
    WHERE u.id = ?
");
$stmt->execute([$child_id]);
$child_data = $stmt->fetch();

if($child_data && $child_data['manager_id']) {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, message, created_at) 
        VALUES (?, ?, NOW())
    ");
    $message = "A parent has requested to unlink from child: " . $child_data['child_name'] . ". Please review the request.";
    $stmt->execute([$child_data['manager_id'], $message]);
}

echo json_encode(['success' => true, 'message' => 'Unlink request sent to manager. You will be notified once processed.']);
?>