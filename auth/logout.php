<?php
session_start();
session_destroy();

// Redirect to home page with correct path
header('Location: ../index.php');
exit;
?>