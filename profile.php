<?php
require_once __DIR__ . '/db.php';
require_student();

$memberId = (int) $_SESSION['member_id'];
$student = current_student();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $year = (int) ($_POST['study_year'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $interests = trim($_POST['interests'] ?? '');
        $linkedin = trim($_POST['linkedin'] ?? '');
        $github = trim($_POST['github'] ?? '');

        if ($fullName === '' || !preg_match('/^[0-9]{10}$/', $phone) || $department === '' || $year < 1 || $year > 4 || $address === '') {
            flash('error', 'Please enter valid profile details.');
            redirect('profile.php');
        }

        $photo = handle_profile_upload('profile_photo', $student['profile_photo'] ?? null);

        $stmt = db()->prepare(
            'UPDATE `MEMBER`
             SET full_name = ?, phone = ?, department = ?, study_year = ?, address = ?, profile_photo = ?
             WHERE member_id = ?'
        );
        $stmt->execute([$fullName, $phone, $department, $year, $address, $photo, $memberId]);

        $details = db()->prepare(
            'INSERT INTO `MEMBER_DETAILS` (member_id, bio, interests, linkedin, github)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE bio = VALUES(bio), interests = VALUES(interests), linkedin = VALUES(linkedin), github = VALUES(github)'
        );
        $details->execute([$memberId, $bio, $interests, $linkedin, $github]);

        flash('success', 'Profile updated successfully.');
        redirect('profile.php');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = db()->prepare('SELECT password_hash FROM `LOGIN` WHERE login_id = ? AND member_id = ?');
        $stmt->execute([$_SESSION['login_id'], $memberId]);
        $login = $stmt->fetch();

        if (!$login || !password_verify($current, $login['password_hash'])) {
            flash('error', 'Current password is incorrect.');
            redirect('profile.php');
        }
        if (strlen($new) < 6 || $new !== $confirm) {
            flash('error', 'New password must match and contain at least 6 characters.');
            redirect('profile.php');
        }

        $update = db()->prepare('UPDATE `LOGIN` SET password_hash = ? WHERE login_id = ?');
        $update->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['login_id']]);
        flash('success', 'Password changed successfully.');
        redirect('profile.php');
    }
}

$student = current_student();
$clubs = db()->prepare(
    'SELECT c.*, mc.joined_at, mc.membership_status
     FROM `MEMBER_CLUB` mc
     JOIN `CLUB` c ON c.club_id = mc.club_id
     WHERE mc.member_id = ? AND mc.membership_status <> "Removed"
     ORDER BY mc.joined_at DESC'
);
$clubs->execute([$memberId]);
$joinedClubs = $clubs->fetchAll();

app_header('Profile Management', 'profile');
?>
<section class="content-grid two profile-layout">
    <article class="panel">
        <div class="profile-hero stacked">
            <div class="avatar xlarge"><?php echo student_avatar($student); ?></div>
            <div>
                <p class="eyebrow">Student Identity</p>
                <h2><?php echo e($student['full_name']); ?></h2>
                <p><?php echo e($student['usn']); ?> - <?php echo e($student['email']); ?></p>
            </div>
        </div>
        <div class="info-list">
            <span>Department <strong><?php echo e($student['department']); ?></strong></span>
            <span>Year <strong><?php echo e((string) $student['study_year']); ?></strong></span>
            <span>Phone <strong><?php echo e($student['phone']); ?></strong></span>
            <span>Address <strong><?php echo e($student['address']); ?></strong></span>
        </div>
    </article>

    <article class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Security</p>
                <h2>Change Password</h2>
            </div>
        </div>
        <form method="POST" class="form" data-password-match>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="change_password">
            <label>
                <span>Current Password</span>
                <input type="password" name="current_password" required>
            </label>
            <label>
                <span>New Password</span>
                <input type="password" name="new_password" required minlength="6">
            </label>
            <label>
                <span>Confirm Password</span>
                <input type="password" name="confirm_password" required minlength="6">
            </label>
            <button class="btn primary" type="submit">Update Password</button>
        </form>
    </article>
</section>

<section class="content-grid two">
    <article class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Edit Details</p>
                <h2>Profile Information</h2>
            </div>
        </div>
        <form method="POST" class="form grid-form" enctype="multipart/form-data" data-validate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_profile">
            <label>
                <span>Full Name</span>
                <input type="text" name="full_name" value="<?php echo e($student['full_name']); ?>" required>
            </label>
            <label>
                <span>Phone</span>
                <input type="tel" name="phone" value="<?php echo e($student['phone']); ?>" required pattern="[0-9]{10}">
            </label>
            <label>
                <span>Department</span>
                <input type="text" name="department" value="<?php echo e($student['department']); ?>" required>
            </label>
            <label>
                <span>Year</span>
                <select name="study_year" required>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo (int) $student['study_year'] === $i ? 'selected' : ''; ?>><?php echo $i; ?> Year</option>
                    <?php endfor; ?>
                </select>
            </label>
            <label class="full-row">
                <span>Address</span>
                <textarea name="address" rows="3" required><?php echo e($student['address']); ?></textarea>
            </label>
            <label class="full-row">
                <span>Bio</span>
                <textarea name="bio" rows="3" placeholder="Short academic or club bio"><?php echo e($student['bio'] ?? ''); ?></textarea>
            </label>
            <label>
                <span>Interests</span>
                <input type="text" name="interests" value="<?php echo e($student['interests'] ?? ''); ?>" placeholder="Coding, music, sports">
            </label>
            <label>
                <span>LinkedIn</span>
                <input type="url" name="linkedin" value="<?php echo e($student['linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/name">
            </label>
            <label>
                <span>GitHub</span>
                <input type="url" name="github" value="<?php echo e($student['github'] ?? ''); ?>" placeholder="https://github.com/name">
            </label>
            <label class="file-field">
                <span>Profile Photo</span>
                <input type="file" name="profile_photo" accept="image/png,image/jpeg,image/webp" data-photo-preview>
            </label>
            <button class="btn primary full-row" type="submit">Save Changes</button>
        </form>
    </article>

    <article class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Memberships</p>
                <h2>Joined Clubs</h2>
            </div>
            <a href="clubs.php" class="text-link">Add New</a>
        </div>
        <div class="compact-club-list">
            <?php foreach ($joinedClubs as $club): ?>
                <?php $isPending = $club['membership_status'] === 'Pending'; ?>
                <div>
                    <span class="club-icon"><?php echo e($club['icon']); ?></span>
                    <div>
                        <strong><?php echo e($club['club_name']); ?></strong>
                        <small><?php echo $isPending ? 'Requested' : 'Joined'; ?> <?php echo date('d M Y', strtotime($club['joined_at'])); ?></small>
                    </div>
                    <?php echo badge($club['membership_status'], $isPending ? 'orange' : 'green'); ?>
                    <form action="join_club.php" method="POST" data-confirm="Remove this club from your account?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="club_id" value="<?php echo (int) $club['club_id']; ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="return_to" value="profile.php">
                        <button class="tiny-button danger" type="submit"><?php echo $isPending ? 'Cancel' : 'Leave'; ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$joinedClubs): ?>
                <div class="empty-state">You have not joined any clubs yet.</div>
            <?php endif; ?>
        </div>
    </article>
</section>
<?php app_footer(); ?>
