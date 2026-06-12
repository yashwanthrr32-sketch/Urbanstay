<?php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;

if($tenant_id <= 0) {
    echo json_encode(['error' => 'Invalid tenant ID']);
    exit;
}

// Get tenant basic info
$stmt = $pdo->prepare("
    SELECT u.*, 
           b.personal_info_json,
           b.status as booking_status,
           b.requested_at as booking_date
    FROM users u
    LEFT JOIN bookings b ON u.id = b.tenant_id AND b.status IN ('processing', 'confirmed')
    WHERE u.id = ? AND u.role = 'tenant'
");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

if(!$tenant) {
    echo json_encode(['error' => 'Tenant not found']);
    exit;
}

$personal_info = [];
if($tenant['personal_info_json']) {
    $personal_info = json_decode($tenant['personal_info_json'], true);
}

// Get booking details
$stmt = $pdo->prepare("
    SELECT b.*, pg.name as pg_name, r.room_number, bed.bed_label, td.rent_amount, td.due_date
    FROM bookings b
    LEFT JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN beds bed ON b.bed_id = bed.id
    LEFT JOIN rooms r ON bed.room_id = r.id
    LEFT JOIN tenant_details td ON b.id = td.booking_id
    WHERE b.tenant_id = ? AND b.status IN ('processing', 'confirmed')
    ORDER BY b.requested_at DESC LIMIT 1
");
$stmt->execute([$tenant_id]);
$booking = $stmt->fetch();

// Get parent details
$stmt = $pdo->prepare("
    SELECT pt.*, u.name, u.email, u.phone, pt.requested_at as linked_date
    FROM parent_tenant pt
    JOIN users u ON pt.parent_id = u.id
    WHERE pt.tenant_id = ?
");
$stmt->execute([$tenant_id]);
$parent = $stmt->fetch();

$response = [
    'id' => $tenant['id'],
    'full_name' => $personal_info['full_name'] ?? $tenant['name'],
    'email' => $tenant['email'],
    'phone' => $personal_info['phone'] ?? $tenant['phone'],
    'id_type' => $personal_info['id_type'] ?? 'Not provided',
    'id_number' => $personal_info['id_number'] ?? 'Not provided',
    'address' => $personal_info['address'] ?? 'Not provided',
    'profile_photo' => $personal_info['profile_photo'] ?? null,
    'emergency_name' => $personal_info['emergency_name'] ?? 'Not provided',
    'emergency_phone' => $personal_info['emergency_phone'] ?? 'Not provided',
    'registered_date' => date('d M Y', strtotime($tenant['created_at'])),
    'status' => $tenant['status'],
    'booking' => $booking ? [
        'pg_name' => $booking['pg_name'],
        'room_number' => $booking['room_number'],
        'bed_label' => $booking['bed_label'],
        'status' => $booking['status'],
        'rent_amount' => $booking['rent_amount'],
        'due_date' => $booking['due_date']
    ] : null,
    'parent' => $parent ? [
        'name' => $parent['name'],
        'email' => $parent['email'],
        'phone' => $parent['phone'],
        'linked_date' => date('d M Y', strtotime($parent['linked_date']))
    ] : null
];

echo json_encode($response);
?>