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
        flash('error', 'Enter your email or USN and password.');
        redirect('student_login.php');
    }

    $stmt = db()->prepare(
        'SELECT l.*, m.full_name, m.usn, m.email
         FROM `LOGIN` l
         JOIN `MEMBER` m ON m.member_id = l.member_id
         WHERE l.role = "student" AND (l.username = ? OR m.email = ? OR m.usn = ?)'
    );
    $stmt->execute([$username, $username, strtoupper($username)]);
    $login = $stmt->fetch();

    if (!$login || !password_verify($password, $login['password_hash'])) {
        flash('error', 'Invalid student credentials.');
        redirect('student_login.php');
    }

    login_user($login);
    flash('success', 'Welcome back, ' . ($login['full_name'] ?? 'Student') . '.');
    redirect('dashboard.php');
}

auth_header('Student Login', 'student-auth');
?>
<section class="auth-shell student-login-shell">
    <div class="auth-side">
        <a class="brand auth-brand" href="index.php">
            <span class="brand-mark">SC</span>
            <span><strong>Smart Clubs</strong><small>Student Module</small></span>
        </a>
        <p class="eyebrow">Student Portal</p>
        <h1>Manage your profile, clubs, events, and payments.</h1>
        <p>Students can only view their own profile, memberships, event participation, attendance, and payment records.</p>
        <div class="mini-metrics">
            <span>Join clubs</span>
            <span>Register events</span>
            <span>Track history</span>
        </div>
    </div>
    <div class="auth-card">
        <?php render_flashes(); ?>
        <div class="auth-mode-label">Student Login</div>
        <form method="POST" class="form" data-validate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="role" value="student">
            <label>
                <span>Email or USN</span>
                <input type="text" name="username" required placeholder="student@college.edu or 1XX22CS001">
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required minlength="6" placeholder="Enter password">
            </label>
            <button class="btn primary full" type="submit">Login to Student Dashboard</button>
        </form>
        <p class="form-note">
            New student? <a href="register.php">Create your profile</a>
        </p>
        <p class="form-note">
            Staff member? <a href="admin_login.php">Use admin login</a>
        </p>
    </div>
</section>
<?php auth_footer(); ?>
