<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kolkata');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'club_management_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '4SF23CI065@j');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_base(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return strpos($script, '/admin/') !== false ? '../' : '';
}

function path_to(string $path): string
{
    return app_base() . ltrim($path, '/');
}

function redirect(string $path)
{
    header('Location: ' . path_to($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flashes(): void
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    if (!$flashes) {
        return;
    }

    echo '<div class="flash-stack">';
    foreach ($flashes as $flash) {
        echo '<div class="flash ' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
    }
    echo '</div>';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $posted = $_POST['csrf_token'] ?? '';
    if (!$posted || !hash_equals($_SESSION['csrf_token'] ?? '', $posted)) {
        flash('error', 'Security token expired. Please try again.');
        if (is_logged_in()) {
            redirect(is_admin() ? 'admin/admin_dashboard.php' : 'dashboard.php');
        }

        redirect(($_POST['role'] ?? '') === 'admin' ? 'admin_login.php' : 'student_login.php');
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['login_id']) && !empty($_SESSION['role']);
}

function is_student(): bool
{
    return ($_SESSION['role'] ?? '') === 'student';
}

function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function require_student(): void
{
    if (!is_logged_in() || !is_student()) {
        flash('error', 'Please login as a student to continue.');
        redirect('student_login.php');
    }
}

function require_admin(): void
{
    if (!is_logged_in() || !is_admin()) {
        flash('error', 'Please login as an admin to continue.');
        redirect('admin_login.php');
    }
}

function current_student(): ?array
{
    if (!is_student() || empty($_SESSION['member_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT m.*, md.bio, md.interests, md.linkedin, md.github, l.username
         FROM `MEMBER` m
         JOIN `LOGIN` l ON l.member_id = m.member_id
         LEFT JOIN `MEMBER_DETAILS` md ON md.member_id = m.member_id
         WHERE m.member_id = ?'
    );
    $stmt->execute([$_SESSION['member_id']]);
    $student = $stmt->fetch();

    return $student ?: null;
}

function current_admin(): ?array
{
    if (!is_admin() || empty($_SESSION['staff_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT s.*, l.username
         FROM `STAFF` s
         JOIN `LOGIN` l ON l.staff_id = s.staff_id
         WHERE s.staff_id = ?'
    );
    $stmt->execute([$_SESSION['staff_id']]);
    $admin = $stmt->fetch();

    return $admin ?: null;
}

function login_user(array $login): void
{
    session_regenerate_id(true);
    $_SESSION['login_id'] = $login['login_id'];
    $_SESSION['role'] = $login['role'];
    $_SESSION['member_id'] = $login['member_id'] ?? null;
    $_SESSION['staff_id'] = $login['staff_id'] ?? null;

    $stmt = db()->prepare('UPDATE `LOGIN` SET last_login = NOW() WHERE login_id = ?');
    $stmt->execute([$login['login_id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function handle_profile_upload(string $field, ?string $currentPath = null): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $currentPath;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'Photo upload failed. Please try again.');
        return $currentPath;
    }

    $maxBytes = 2 * 1024 * 1024;
    if ($_FILES[$field]['size'] > $maxBytes) {
        flash('error', 'Profile photo must be under 2 MB.');
        return $currentPath;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        flash('error', 'Only JPG, PNG, or WEBP photos are allowed.');
        return $currentPath;
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'profile_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        flash('error', 'Could not save uploaded photo.');
        return $currentPath;
    }

    return 'uploads/' . $filename;
}

function student_avatar(?array $student): string
{
    if (!empty($student['profile_photo'])) {
        return '<img src="' . e(path_to($student['profile_photo'])) . '" alt="Profile photo">';
    }

    $name = trim((string) ($student['full_name'] ?? 'Student'));
    $parts = preg_split('/\s+/', $name);
    $initials = strtoupper(substr($parts[0] ?? 'S', 0, 1) . substr($parts[1] ?? '', 0, 1));
    return '<span>' . e($initials ?: 'S') . '</span>';
}

function admin_avatar(?array $admin): string
{
    $name = trim((string) ($admin['full_name'] ?? 'Admin'));
    $parts = preg_split('/\s+/', $name);
    $initials = strtoupper(substr($parts[0] ?? 'A', 0, 1) . substr($parts[1] ?? '', 0, 1));
    return '<span>' . e($initials ?: 'A') . '</span>';
}

function page_start(string $title, string $class = ''): void
{
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . e($title) . ' | Smart College Club Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="' . e(path_to('css/style.css')) . '">
</head>
<body class="' . e($class) . '">';
}

function page_end(): void
{
    echo '<script src="' . e(path_to('js/script.js')) . '"></script>
</body>
</html>';
}

function auth_header(string $title, string $class = ''): void
{
    page_start($title, trim('auth-page ' . $class));
}

function auth_footer(): void
{
    page_end();
}

function app_header(string $title, string $active = ''): void
{
    $isAdmin = is_admin();
    $student = current_student();
    $admin = current_admin();
    $name = $isAdmin ? ($admin['full_name'] ?? 'Admin') : ($student['full_name'] ?? 'Student');
    $subtitle = $isAdmin ? 'Admin Workspace' : (($student['department'] ?? 'Student') . ' Dashboard');
    $avatar = $isAdmin ? admin_avatar($admin) : student_avatar($student);

    page_start($title, $isAdmin ? 'app-page admin-page' : 'app-page student-page');

    $studentNav = [
        'dashboard' => ['Dashboard', 'dashboard.php'],
        'profile' => ['Profile', 'profile.php'],
        'clubs' => ['Clubs', 'clubs.php'],
        'events' => ['Events', 'events.php'],
        'payments' => ['Payments', 'payments.php'],
    ];

    $adminNav = [
        'admin_dashboard' => ['Dashboard', 'admin/admin_dashboard.php'],
        'manage_users' => ['Students', 'admin/manage_users.php'],
        'manage_clubs' => ['Clubs', 'admin/manage_clubs.php'],
        'manage_events' => ['Events', 'admin/manage_events.php'],
        'manage_payments' => ['Payments', 'admin/manage_payments.php'],
        'manage_staff' => ['Staff', 'admin/manage_staff.php'],
        'reports' => ['Reports', 'admin/reports.php'],
    ];

    $nav = $isAdmin ? $adminNav : $studentNav;

    echo '<div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="' . e(path_to($isAdmin ? 'admin/admin_dashboard.php' : 'dashboard.php')) . '">
                <span class="brand-mark">SC</span>
                <span><strong>Smart Clubs</strong><small>DBMS Mini Project</small></span>
            </a>
            <nav class="nav-list">';

    foreach ($nav as $key => [$label, $href]) {
        $selected = $active === $key ? ' active' : '';
        echo '<a class="nav-link' . $selected . '" href="' . e(path_to($href)) . '"><span>' . e(substr($label, 0, 1)) . '</span>' . e($label) . '</a>';
    }

    echo '  </nav>
            <a class="logout-link" href="' . e(path_to('logout.php')) . '">Logout</a>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="icon-button menu-toggle" type="button" data-sidebar-toggle aria-label="Toggle navigation">&#9776;</button>
                <div>
                    <p class="eyebrow">' . e($subtitle) . '</p>
                    <h1>' . e($title) . '</h1>
                </div>
                <div class="topbar-user">
                    <div class="avatar">' . $avatar . '</div>
                    <span>' . e($name) . '</span>
                </div>
            </header>';

    render_flashes();
}

function app_footer(): void
{
    echo '</main></div>';
    page_end();
}

function badge(string $text, string $class = ''): string
{
    return '<span class="badge ' . e($class) . '">' . e($text) . '</span>';
}

function money(float $amount): string
{
    return 'Rs. ' . number_format($amount, 2);
}
?>
