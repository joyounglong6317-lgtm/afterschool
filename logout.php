<?php
require_once __DIR__ . '/includes/security.php';
secure_session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
