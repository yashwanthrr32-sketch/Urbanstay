<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db.php';
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;

if($tenant_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid tenant ID']);
    exit;
}

// Get tenant basic info
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.phone, u.created_at, u.status
    FROM users u
    WHERE u.id = ? AND u.role = 'tenant'
");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$tenant) {
    echo json_encode(['success' => false, 'error' => 'Tenant not found']);
    exit;
}

// Get booking info with personal_info_json
$stmt = $pdo->prepare("
    SELECT b.*, pg.name as pg_name, r.room_number, bed.bed_label
    FROM bookings b
    LEFT JOIN pgs pg ON b.pg_id = pg.id
    LEFT JOIN beds bed ON b.bed_id = bed.id
    LEFT JOIN rooms r ON bed.room_id = r.id
    WHERE b.tenant_id = ? 
    ORDER BY b.requested_at DESC 
    LIMIT 1
");
$stmt->execute([$tenant_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

// Parse personal_info_json
$personal_info = [];
if($booking && !empty($booking['personal_info_json'])) {
    $personal_info = json_decode($booking['personal_info_json'], true);
    if(json_last_error() !== JSON_ERROR_NONE) {
        $personal_info = [];
    }
}

// Get parent details
$stmt = $pdo->prepare("
    SELECT parent.name, parent.email, parent.phone
    FROM parent_tenant pt
    JOIN users parent ON pt.parent_id = parent.id
    WHERE pt.tenant_id = ?
");
$stmt->execute([$tenant_id]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

// Build response - USE DATA FROM personal_info_json FIRST
$response = [
    'success' => true,
    'data' => [
        // From personal_info_json (booking form data) - THESE ARE THE IMPORTANT ONES
        'full_name' => isset($personal_info['full_name']) ? $personal_info['full_name'] : $tenant['name'],
        'phone' => isset($personal_info['phone']) ? $personal_info['phone'] : $tenant['phone'],
        'id_type' => isset($personal_info['id_type']) ? $personal_info['id_type'] : 'Not provided',
        'id_number' => isset($personal_info['id_number']) ? $personal_info['id_number'] : 'Not provided',
        'address' => isset($personal_info['address']) ? $personal_info['address'] : 'Not provided',
        'profile_photo' => isset($personal_info['profile_photo']) ? $personal_info['profile_photo'] : null,
        'emergency_name' => isset($personal_info['emergency_name']) ? $personal_info['emergency_name'] : 'Not provided',
        'emergency_phone' => isset($personal_info['emergency_phone']) ? $personal_info['emergency_phone'] : 'Not provided',
        
        // From users table (registration data)
        'email' => $tenant['email'],
        'registered_name' => $tenant['name'],
        'registered_phone' => $tenant['phone'],
        'registered_date' => date('d M Y', strtotime($tenant['created_at'])),
        'account_status' => $tenant['status'],
        
        // Booking details
        'booking_status' => $booking ? ucfirst($booking['status']) : 'No active booking',
        'pg_name' => $booking ? $booking['pg_name'] : 'N/A',
        'room_number' => $booking ? $booking['room_number'] : 'N/A',
        'bed_label' => $booking ? $booking['bed_label'] : 'N/A',
        
        // Parent info
        'parent' => $parent ? [
            'name' => $parent['name'],
            'email' => $parent['email'],
            'phone' => $parent['phone']
        ] : null
    ]
];

echo json_encode($response);
?>