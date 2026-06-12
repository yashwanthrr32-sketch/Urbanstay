<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db.php';
header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only managers and admins can unlink parents
if($_SESSION['role'] != 'manager' && $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Only managers and admins can unlink parents']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$tenant_id = isset($data['tenant_id']) ? (int)$data['tenant_id'] : 0;

if($tenant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid tenant ID']);
    exit;
}

// For manager: check if tenant belongs to their PG
if($_SESSION['role'] == 'manager') {
    $stmt = $pdo->prepare("
        SELECT pg.id as pg_id 
        FROM pgs pg 
        WHERE pg.manager_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $manager_pg = $stmt->fetch();
    
    if($manager_pg) {
        $stmt = $pdo->prepare("
            SELECT b.id FROM bookings b
            WHERE b.tenant_id = ? AND b.pg_id = ?
        ");
        $stmt->execute([$tenant_id, $manager_pg['pg_id']]);
        if(!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized - Tenant does not belong to your PG']);
            exit;
        }
    }
}

// Get parent info before unlinking for notification
$stmt = $pdo->prepare("
    SELECT parent_id FROM parent_tenant WHERE tenant_id = ?
");
$stmt->execute([$tenant_id]);
$parent = $stmt->fetch();

$parent_id = $parent ? $parent['parent_id'] : 0;

// Delete the parent-tenant relationship
$stmt = $pdo->prepare("DELETE FROM parent_tenant WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);

if($stmt->rowCount() > 0) {
    // Notify the parent that they have been unlinked
    if($parent_id) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, message, created_at) 
            VALUES (?, 'You have been unlinked from your child. Please contact the PG manager for assistance.', NOW())
        ");
        $stmt->execute([$parent_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Parent unlinked successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'No parent was linked to this tenant']);
}
?>