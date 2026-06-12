<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$request_id = isset($data['request_id']) ? (int)$data['request_id'] : 0;
$action = isset($data['action']) ? $data['action'] : '';

if($request_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Get request details
$stmt = $pdo->prepare("
    SELECT ur.*, pg.manager_id 
    FROM unlink_requests ur
    JOIN bookings b ON ur.tenant_id = b.tenant_id
    JOIN pgs pg ON b.pg_id = pg.id
    WHERE ur.id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if(!$request) {
    echo json_encode(['success' => false, 'message' => 'Request not found']);
    exit;
}

// Verify this manager owns this PG
if($request['manager_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if($action == 'approve') {
    // Delete the parent-tenant relationship
    $stmt = $pdo->prepare("DELETE FROM parent_tenant WHERE parent_id = ? AND tenant_id = ?");
    $stmt->execute([$request['parent_id'], $request['tenant_id']]);
    
    // Update request status
    $stmt = $pdo->prepare("UPDATE unlink_requests SET status = 'approved', processed_at = NOW() WHERE id = ?");
    $stmt->execute([$request_id]);
    
    // Notify parent
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, message, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$request['parent_id'], 'Your request to unlink from child has been approved. You can no longer view this child\'s details.']);
    
    echo json_encode(['success' => true, 'message' => 'Parent unlinked successfully']);
    
} else {
    // Reject the request
    $stmt = $pdo->prepare("UPDATE unlink_requests SET status = 'rejected', processed_at = NOW() WHERE id = ?");
    $stmt->execute([$request_id]);
    
    // Notify parent
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, message, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$request['parent_id'], 'Your request to unlink from child has been rejected. Please contact the PG manager for more information.']);
    
    echo json_encode(['success' => true, 'message' => 'Unlink request rejected']);
}
?>