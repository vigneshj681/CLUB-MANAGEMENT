<?php
require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/admin_dashboard.php' : 'dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        flash('error', 'Enter the admin username and password.');
        redirect('admin_login.php');
    }

    $stmt = db()->prepare(
        'SELECT l.*, s.full_name
         FROM `LOGIN` l
         JOIN `STAFF` s ON s.staff_id = l.staff_id
         WHERE l.role = "admin" AND l.username = ?'
    );
    $stmt->execute([$username]);
    $login = $stmt->fetch();

    if (!$login || !password_verify($password, $login['password_hash'])) {
        flash('error', 'Invalid admin credentials.');
        redirect('admin_login.php');
    }

    login_user($login);
    flash('success', 'Welcome back, ' . ($login['full_name'] ?? 'Admin') . '.');
    redirect('admin/admin_dashboard.php');
}

auth_header('Admin Login', 'admin-auth');
?>
<section class="auth-shell admin-login-shell">
    <div class="auth-side">
        <a class="brand auth-brand" href="index.php">
            <span class="brand-mark">SC</span>
            <span><strong>Smart Clubs</strong><small>Admin Module</small></span>
        </a>
        <p class="eyebrow">Administrative Access</p>
        <h1>Control students, clubs, events, payments, and reports.</h1>
        <p>Admins have complete visibility over registered students, club memberships, event registrations, attendance, staff assignments, and payment records.</p>
        <div class="mini-metrics">
            <span>Analytics</span>
            <span>Data tables</span>
            <span>CRUD controls</span>
        </div>
    </div>
    <div class="auth-card admin-login-card">
        <?php render_flashes(); ?>
        <div class="auth-mode-label">Admin Login</div>
        <form method="POST" class="form" data-validate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="role" value="admin">
            <label>
                <span>Admin Username</span>
                <input type="text" name="username" required placeholder="admin@college.edu">
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required minlength="6" placeholder="Enter password">
            </label>
            <button class="btn primary full" type="submit">Login to Admin Dashboard</button>
        </form>
        <p class="form-note">
            Student? <a href="student_login.php">Use student login</a>
        </p>
    </div>
</section>
<?php auth_footer(); ?>
