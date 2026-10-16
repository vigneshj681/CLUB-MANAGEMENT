<?php
require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/admin_dashboard.php' : 'dashboard.php');
}

redirect(($_GET['role'] ?? '') === 'admin' ? 'admin_login.php' : 'student_login.php');
?>
