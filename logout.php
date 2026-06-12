<?php
require_once 'config.php';
$role = clean($_GET['role'] ?? 'admin');
session_destroy();
redirect('login.php?role=' . $role);
?>
