<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db.php';
header('Content-Type: application/json');

function sendResponse($success, $message, $redirect = null) {
    $response = ['success' => $success, 'message' => $message];
    if($redirect) {
        $response['redirect'] = $redirect;
    }
    echo json_encode($response);
    exit;
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tenant') {
    sendResponse(false, 'Please login as tenant to book');
}

$bed_id = (int)$_POST['bed_id'];
$pg_id = (int)$_POST['pg_id'];
$tenant_id = $_SESSION['user_id'];

$full_name = trim($_POST['full_name']);
$phone = trim($_POST['phone']);
$address = trim($_POST['address']);
$emergency_name = trim($_POST['emergency_name']);
$emergency_phone = trim($_POST['emergency_phone']);

if(empty($full_name)) sendResponse(false, 'Please enter your full name');
if(empty($phone)) sendResponse(false, 'Please enter your phone number');
if(empty($address)) sendResponse(false, 'Please enter your address');
if(empty($emergency_name)) sendResponse(false, 'Please enter emergency contact name');
if(empty($emergency_phone)) sendResponse(false, 'Please enter emergency contact phone');

$stmt = $pdo->prepare("SELECT id FROM bookings WHERE tenant_id = ? AND status IN ('processing', 'confirmed')");
$stmt->execute([$tenant_id]);
if($stmt->fetch()) {
    sendResponse(false, 'You already have an active booking');
}

$stmt = $pdo->prepare("SELECT status FROM beds WHERE id = ?");
$stmt->execute([$bed_id]);
$bed = $stmt->fetch();
if(!$bed || $bed['status'] != 'available') {
    sendResponse(false, 'Bed not available');
}

$upload_dir = '../assets/images/uploads/tenant_documents/';
if(!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$profile_photo = '';
if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
    $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
    if(in_array($_FILES['profile_photo']['type'], $allowed)) {
        $filename = 'tenant_' . $tenant_id . '_profile_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['profile_photo']['name']);
        if(move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $filename)) {
            $profile_photo = 'assets/images/uploads/tenant_documents/' . $filename;
        }
    }
}

$id_proof_document = '';
if(isset($_FILES['id_proof_document']) && $_FILES['id_proof_document']['error'] == 0) {
    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    if(in_array($_FILES['id_proof_document']['type'], $allowed)) {
        $filename = 'tenant_' . $tenant_id . '_idproof_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['id_proof_document']['name']);
        if(move_uploaded_file($_FILES['id_proof_document']['tmp_name'], $upload_dir . $filename)) {
            $id_proof_document = 'assets/images/uploads/tenant_documents/' . $filename;
        }
    }
}

if(empty($profile_photo)) sendResponse(false, 'Please upload a profile photo');
if(empty($id_proof_document)) sendResponse(false, 'Please upload your Government ID Proof document');

$personal_info = [
    'full_name' => $full_name,
    'phone' => $phone,
    'address' => $address,
    'profile_photo' => $profile_photo,
    'id_proof_document' => $id_proof_document,
    'id_proof_status' => 'pending',
    'emergency_name' => $emergency_name,
    'emergency_phone' => $emergency_phone,
    'submitted_at' => date('Y-m-d H:i:s')
];

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO bookings (tenant_id, bed_id, pg_id, personal_info_json, status, requested_at) VALUES (?, ?, ?, ?, 'processing', NOW())");
    $stmt->execute([$tenant_id, $bed_id, $pg_id, json_encode($personal_info)]);
    
    $stmt = $pdo->prepare("UPDATE beds SET status = 'occupied' WHERE id = ?");
    $stmt->execute([$bed_id]);
    
    $stmt = $pdo->prepare("SELECT manager_id FROM pgs WHERE id = ?");
    $stmt->execute([$pg_id]);
    $pg = $stmt->fetch();
    if($pg) {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$pg['manager_id'], "New booking request from " . $full_name . ". Please verify ID proof."]);
    }
    
    $pdo->commit();
    // No redirect - stay on same page
    sendResponse(true, 'Booking submitted successfully! Please wait for manager approval.', null);
    
} catch(Exception $e) {
    $pdo->rollBack();
    sendResponse(false, 'Booking failed: ' . $e->getMessage());
}
?>