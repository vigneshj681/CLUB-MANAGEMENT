<?php
require_once __DIR__ . '/db.php';
require_student();

$student = current_student();
$memberId = (int) $_SESSION['member_id'];

$stats = [
    'clubs' => 0,
    'participations' => 0,
    'upcoming' => 0,
    'payments_due' => 0.0,
];

$stmt = db()->prepare('SELECT COUNT(*) FROM `MEMBER_CLUB` WHERE member_id = ? AND membership_status = "Approved"');
$stmt->execute([$memberId]);
$stats['clubs'] = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM `PARTICIPATION` WHERE member_id = ?');
$stmt->execute([$memberId]);
$stats['participations'] = (int) $stmt->fetchColumn();

$stmt = db()->prepare(
    'SELECT COUNT(*)
     FROM `EVENT` e
     JOIN `MEMBER_CLUB` mc ON mc.club_id = e.club_id
     WHERE mc.member_id = ? AND mc.membership_status = "Approved" AND e.event_date >= CURDATE()'
);
$stmt->execute([$memberId]);
$stats['upcoming'] = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM `PAYMENT` WHERE member_id = ? AND payment_status = "Pending"');
$stmt->execute([$memberId]);
$stats['payments_due'] = (float) $stmt->fetchColumn();

$joined = db()->prepare(
    'SELECT c.*, mc.membership_status, mc.joined_at
     FROM `MEMBER_CLUB` mc
     JOIN `CLUB` c ON c.club_id = mc.club_id
     WHERE mc.member_id = ? AND mc.membership_status <> "Removed"
     ORDER BY c.club_name'
);
$joined->execute([$memberId]);
$joinedClubs = $joined->fetchAll();

$events = db()->prepare(
    'SELECT e.*, c.club_name,
            EXISTS(SELECT 1 FROM `PARTICIPATION` p WHERE p.member_id = ? AND p.event_id = e.event_id) AS registered
     FROM `EVENT` e
     JOIN `CLUB` c ON c.club_id = e.club_id
     JOIN `MEMBER_CLUB` mc ON mc.club_id = e.club_id AND mc.member_id = ? AND mc.membership_status = "Approved"
     WHERE e.event_date >= CURDATE()
     ORDER BY e.event_date, e.event_time
     LIMIT 5'
);
$events->execute([$memberId, $memberId]);
$upcomingEvents = $events->fetchAll();

$activities = db()->prepare(
    'SELECT p.registered_at, p.attendance_status, e.event_title, e.event_date, c.club_name
     FROM `PARTICIPATION` p
     JOIN `EVENT` e ON e.event_id = p.event_id
     JOIN `CLUB` c ON c.club_id = e.club_id
     WHERE p.member_id = ?
     ORDER BY p.registered_at DESC
     LIMIT 6'
);
$activities->execute([$memberId]);
$recentActivities = $activities->fetchAll();

$payments = db()->prepare(
    'SELECT p.*, c.club_name
     FROM `PAYMENT` p
     LEFT JOIN `CLUB` c ON c.club_id = p.club_id
     WHERE p.member_id = ?
     ORDER BY p.created_at DESC
     LIMIT 5'
);
$payments->execute([$memberId]);
$recentPayments = $payments->fetchAll();

app_header('Dashboard', 'dashboard');
?>
<section class="dashboard-grid">
    <article class="profile-summary panel">
        <div class="profile-hero">
            <div class="avatar large"><?php echo student_avatar($student); ?></div>
            <div>
                <p class="eyebrow">Student Profile</p>
                <h2><?php echo e($student['full_name']); ?></h2>
                <p><?php echo e($student['usn']); ?> - <?php echo e($student['department']); ?>, Year <?php echo e((string) $student['study_year']); ?></p>
            </div>
        </div>
        <div class="info-list">
            <span>Email <strong><?php echo e($student['email']); ?></strong></span>
            <span>Phone <strong><?php echo e($student['phone']); ?></strong></span>
            <span>Joined <strong><?php echo date('d M Y', strtotime($student['created_at'])); ?></strong></span>
        </div>
        <a class="btn soft" href="profile.php">Manage Profile</a>
    </article>

    <div class="stat-card">
        <span class="stat-icon">CL</span>
        <p>Joined Clubs</p>
        <strong><?php echo $stats['clubs']; ?></strong>
    </div>
    <div class="stat-card">
        <span class="stat-icon">EV</span>
        <p>Upcoming Events</p>
        <strong><?php echo $stats['upcoming']; ?></strong>
    </div>
    <div class="stat-card">
        <span class="stat-icon">PT</span>
        <p>Total Participations</p>
        <strong><?php echo $stats['participations']; ?></strong>
    </div>
    <div class="stat-card">
        <span class="stat-icon">PY</span>
        <p>Payments Due</p>
        <strong><?php echo money($stats['payments_due']); ?></strong>
    </div>
</section>

<section class="content-grid two">
    <article class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Memberships</p>
                <h2>Joined Clubs</h2>
            </div>
            <a class="text-link" href="clubs.php">Add Clubs</a>
        </div>
        <?php if (!$joinedClubs): ?>
            <div class="empty-state">No clubs joined yet. Explore clubs and start building your activity profile.</div>
        <?php else: ?>
            <div class="compact-club-list">
                <?php foreach ($joinedClubs as $club): ?>
                    <div>
                        <span class="club-icon"><?php echo e($club['icon']); ?></span>
                        <div>
                            <strong><?php echo e($club['club_name']); ?></strong>
                            <small><?php echo e($club['category']); ?> - <?php echo e($club['membership_status']); ?></small>
                        </div>
                        <?php echo badge($club['membership_status'], $club['membership_status'] === 'Approved' ? 'green' : 'orange'); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Notifications</p>
                <h2>Quick Updates</h2>
            </div>
        </div>
        <div class="notification-list">
            <div><?php echo badge('Profile', 'green'); ?> Keep your phone and department details updated.</div>
            <div><?php echo badge('Events', 'blue'); ?> Register early because event seats are capacity based.</div>
            <div><?php echo badge('Payments', 'orange'); ?> Membership receipts are visible in the payments page.</div>
        </div>
    </article>
</section>

<section class="content-grid two">
    <article class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Events</p>
                <h2>Upcoming Events</h2>
            </div>
            <a class="text-link" href="events.php">View All</a>
        </div>
        <div class="event-list">
            <?php foreach ($upcomingEvents as $event): ?>
                <div class="event-row">
                    <div class="event-date">
                        <strong><?php echo date('d', strtotime($event['event_date'])); ?></strong>
                        <span><?php echo date('M', strtotime($event['event_date'])); ?></span>
                    </div>
                    <div>
                        <strong><?php echo e($event['event_title']); ?></strong>
                        <small><?php echo e($event['club_name']); ?> - <?php echo e(substr((string) $event['event_time'], 0, 5)); ?></small>
                    </div>
                    <?php echo $event['registered'] ? badge('Registered', 'green') : badge('Open', 'blue'); ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$upcomingEvents): ?>
                <div class="empty-state">No upcoming events are available from your approved clubs yet.</div>
            <?php endif; ?>
        </div>
    </article>

    <article class="panel">
        <div class="section-head">
            <div>
                <p class="eyebrow">Activities</p>
                <h2>Event Activities</h2>
            </div>
        </div>
        <div class="timeline">
            <?php foreach ($recentActivities as $activity): ?>
                <div>
                    <span></span>
                    <p><strong><?php echo e($activity['event_title']); ?></strong> with <?php echo e($activity['club_name']); ?></p>
                    <small><?php echo date('d M Y', strtotime($activity['registered_at'])); ?> - <?php echo e($activity['attendance_status']); ?></small>
                </div>
            <?php endforeach; ?>
            <?php if (!$recentActivities): ?>
                <div class="empty-state">Your event activity will appear here after registration.</div>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Payments</p>
            <h2>Payment History</h2>
        </div>
        <a class="text-link" href="payments.php">Open Payments</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Purpose</th>
                    <th>Club</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentPayments as $payment): ?>
                    <tr>
                        <td><?php echo e($payment['receipt_number']); ?></td>
                        <td><?php echo e($payment['payment_for']); ?></td>
                        <td><?php echo e($payment['club_name'] ?? 'General'); ?></td>
                        <td><?php echo money((float) $payment['amount']); ?></td>
                        <td><?php echo badge($payment['payment_status'], $payment['payment_status'] === 'Paid' ? 'green' : 'orange'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentPayments): ?>
                    <tr><td colspan="5">No payment records yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_footer(); ?>
