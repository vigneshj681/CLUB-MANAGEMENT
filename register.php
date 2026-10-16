<?php
require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/admin_dashboard.php' : 'dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $usn = strtoupper(trim($_POST['usn'] ?? ''));
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $department = trim($_POST['department'] ?? '');
    $year = (int) ($_POST['study_year'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];
    if ($usn === '' || $fullName === '' || $phone === '' || $email === '' || $department === '' || $year < 1 || $year > 4 || $address === '') {
        $errors[] = 'Please fill all required fields.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Phone number must contain exactly 10 digits.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        redirect('register.php');
    }

    try {
        db()->beginTransaction();

        $photo = handle_profile_upload('profile_photo');
        $stmt = db()->prepare(
            'INSERT INTO `MEMBER` (usn, full_name, phone, email, department, study_year, address, profile_photo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$usn, $fullName, $phone, $email, $department, $year, $address, $photo]);
        $memberId = (int) db()->lastInsertId();

        $details = db()->prepare('INSERT INTO `MEMBER_DETAILS` (member_id, bio, interests) VALUES (?, ?, ?)');
        $details->execute([$memberId, '', '']);

        $login = db()->prepare(
            'INSERT INTO `LOGIN` (username, password_hash, role, member_id)
             VALUES (?, ?, "student", ?)'
        );
        $login->execute([$email, password_hash($password, PASSWORD_DEFAULT), $memberId]);
        $loginId = (int) db()->lastInsertId();

        db()->commit();

        login_user([
            'login_id' => $loginId,
            'role' => 'student',
            'member_id' => $memberId,
            'staff_id' => null,
        ]);
        flash('success', 'Profile created successfully. Start joining clubs.');
        redirect('dashboard.php');
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('error', 'Registration failed. USN or email may already exist.');
        redirect('register.php');
    }
}

auth_header('Register');
?>
<section class="auth-shell wide">
    <div class="auth-side">
        <a class="brand auth-brand" href="index.php">
            <span class="brand-mark">SC</span>
            <span><strong>Smart Clubs</strong><small>Student Registration</small></span>
        </a>
        <h1>Create your student profile</h1>
        <p>Register once, then manage club memberships, event participation, payments, and attendance from your dashboard.</p>
    </div>
    <div class="auth-card register-card">
        <?php render_flashes(); ?>
        <form method="POST" class="form grid-form" enctype="multipart/form-data" data-validate data-password-match>
            <?php echo csrf_field(); ?>
            <label>
                <span>USN</span>
                <input type="text" name="usn" required placeholder="1XX22CS001">
            </label>
            <label>
                <span>Full Name</span>
                <input type="text" name="full_name" required placeholder="Student name">
            </label>
            <label>
                <span>Phone Number</span>
                <input type="tel" name="phone" required pattern="[0-9]{10}" placeholder="9876543210">
            </label>
            <label>
                <span>Email</span>
                <input type="email" name="email" required placeholder="student@college.edu">
            </label>
            <label>
                <span>Department</span>
                <select name="department" required>
                    <option value="">Select department</option>
                    <option>Computer Science</option>
                    <option>Information Science</option>
                    <option>Electronics and Communication</option>
                    <option>Electrical and Electronics</option>
                    <option>Mechanical</option>
                    <option>Civil</option>
                    <option>Artificial Intelligence</option>
                </select>
            </label>
            <label>
                <span>Year</span>
                <select name="study_year" required>
                    <option value="">Select year</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </label>
            <label class="full-row">
                <span>Address</span>
                <textarea name="address" required rows="3" placeholder="Current address"></textarea>
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" required minlength="6" placeholder="Create password">
            </label>
            <label>
                <span>Confirm Password</span>
                <input type="password" name="confirm_password" required minlength="6" placeholder="Repeat password">
            </label>
            <label class="full-row file-field">
                <span>Profile Photo (optional)</span>
                <input type="file" name="profile_photo" accept="image/png,image/jpeg,image/webp" data-photo-preview>
            </label>
            <button class="btn primary full-row" type="submit">Create Profile</button>
        </form>
        <p class="form-note">Already registered? <a href="student_login.php">Login here</a></p>
    </div>
</section>
<?php auth_footer(); ?>
