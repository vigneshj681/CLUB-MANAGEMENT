<?php
require_once __DIR__ . '/db.php';

$wasAdmin = is_admin();
logout_user();
session_start();
flash('success', 'You have been logged out safely.');
redirect($wasAdmin ? 'admin_login.php' : 'student_login.php');
?>
