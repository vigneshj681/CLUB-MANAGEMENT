<?php
require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/admin_dashboard.php' : 'dashboard.php');
}

auth_header('Welcome');
?>
<section class="hero-auth">
    <div class="hero-copy">
        <a class="brand auth-brand" href="index.php">
            <span class="brand-mark">SC</span>
            <span><strong>Smart Clubs</strong><small>College Club Management</small></span>
        </a>
        <p class="eyebrow">BTech DBMS Mini Project</p>
        <h1>Smart College Club Management System</h1>
        <p class="hero-text">
            A modern PHP and MySQL platform for student profiles, club memberships, events,
            payments, attendance, and admin reporting.
        </p>
        <div class="hero-actions">
            <a class="btn primary" href="student_login.php">Student Login</a>
            <a class="btn ghost" href="admin_login.php">Admin Login</a>
            <a class="btn soft" href="register.php">Create Profile</a>
        </div>
        <div class="hero-stats">
            <div><strong>13</strong><span>Clubs</span></div>
            <div><strong>Live</strong><span>Event Tracking</span></div>
            <div><strong>Secure</strong><span>PDO Auth</span></div>
        </div>
    </div>
    <div class="hero-panel">
        <div class="dashboard-preview">
            <div class="preview-top"></div>
            <div class="preview-grid">
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="preview-list">
                <span></span><span></span><span></span>
            </div>
        </div>
    </div>
</section>
<?php auth_footer(); ?>
