<?php
if(!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$required_role = $required_role ?? null;
if($required_role && $_SESSION['role'] != $required_role) {
    header('Location: ../index.php');
    exit;
}
?>