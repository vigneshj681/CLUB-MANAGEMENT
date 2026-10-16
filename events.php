<?php
require_once __DIR__ . '/db.php';
require_student();

$memberId = (int) $_SESSION['member_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $eventId = (int) ($_POST['event_id'] ?? 0);

    $eventStmt = db()->prepare(
        'SELECT e.*, c.club_name
         FROM `EVENT` e
         JOIN `CLUB` c ON c.club_id = e.club_id
         JOIN `MEMBER_CLUB` mc ON mc.club_id = e.club_id AND mc.member_id = ? AND mc.membership_status = "Approved"
         WHERE e.event_id = ? AND e.event_date >= CURDATE()'
    );
    $eventStmt->execute([$memberId, $eventId]);
    $event = $eventStmt->fetch();

    if (!$event) {
        flash('error', 'Event is not available for registration.');
        redirect('events.php');
    }

    if ((int) $event['attendee_count'] >= (int) $event['capacity']) {
        flash('error', 'This event has reached its capacity.');
        redirect('events.php');
    }

    try {
        $stmt = db()->prepare('INSERT INTO `PARTICIPATION` (member_id, event_id, attendance_status) VALUES (?, ?, "Registered")');
        $stmt->execute([$memberId, $eventId]);
        flash('success', 'Registered for ' . $event['event_title'] . '.');
    } catch (Throwable $exception) {
        flash('error', 'You are already registered for this event.');
    }

    redirect('events.php');
}

$events = db()->prepare(
    'SELECT e.*, c.club_name,
            EXISTS(SELECT 1 FROM `PARTICIPATION` p WHERE p.member_id = ? AND p.event_id = e.event_id) AS registered
     FROM `EVENT` e
     JOIN `CLUB` c ON c.club_id = e.club_id
     JOIN `MEMBER_CLUB` mc ON mc.club_id = e.club_id AND mc.member_id = ? AND mc.membership_status = "Approved"
     WHERE e.event_date >= CURDATE()
     ORDER BY e.event_date, e.event_time'
);
$events->execute([$memberId, $memberId]);
$upcomingEvents = $events->fetchAll();

$history = db()->prepare(
    'SELECT p.*, e.event_title, e.event_date, c.club_name
     FROM `PARTICIPATION` p
     JOIN `EVENT` e ON e.event_id = p.event_id
     JOIN `CLUB` c ON c.club_id = e.club_id
     WHERE p.member_id = ?
     ORDER BY p.registered_at DESC'
);
$history->execute([$memberId]);
$participations = $history->fetchAll();

app_header('Event Management', 'events');
?>
<section class="toolbar panel">
    <div>
        <p class="eyebrow">Register</p>
        <h2>Upcoming Events</h2>
    </div>
    <label class="search-box">
        <span>Search</span>
        <input type="search" placeholder="Search events or clubs" data-filter-target=".event-card">
    </label>
</section>

<section class="event-card-grid">
    <?php foreach ($upcomingEvents as $event): ?>
        <article class="event-card" data-filter-text="<?php echo e(strtolower($event['event_title'] . ' ' . $event['club_name'])); ?>">
            <div class="event-date block">
                <strong><?php echo date('d', strtotime($event['event_date'])); ?></strong>
                <span><?php echo date('M Y', strtotime($event['event_date'])); ?></span>
            </div>
            <div>
                <p class="eyebrow"><?php echo e($event['club_name']); ?></p>
                <h2><?php echo e($event['event_title']); ?></h2>
                <p><?php echo e($event['description']); ?></p>
            </div>
            <div class="club-meta">
                <span><?php echo e(substr((string) $event['event_time'], 0, 5)); ?></span>
                <span><?php echo e($event['venue']); ?></span>
                <span><?php echo (int) $event['attendee_count']; ?>/<?php echo (int) $event['capacity']; ?> seats</span>
            </div>
            <?php if ($event['registered']): ?>
                <button class="btn soft full" type="button" disabled>Registered</button>
            <?php else: ?>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="event_id" value="<?php echo (int) $event['event_id']; ?>">
                    <button class="btn primary full" type="submit">Register for Event</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if (!$upcomingEvents): ?>
        <article class="panel empty-state">No upcoming events are available from your approved clubs right now.</article>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Participation Tracking</p>
            <h2>My Event Activities</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Club</th>
                    <th>Event Date</th>
                    <th>Registered On</th>
                    <th>Attendance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participations as $item): ?>
                    <tr>
                        <td><?php echo e($item['event_title']); ?></td>
                        <td><?php echo e($item['club_name']); ?></td>
                        <td><?php echo date('d M Y', strtotime($item['event_date'])); ?></td>
                        <td><?php echo date('d M Y', strtotime($item['registered_at'])); ?></td>
                        <td><?php echo badge($item['attendance_status'], $item['attendance_status'] === 'Present' ? 'green' : 'blue'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$participations): ?>
                    <tr><td colspan="5">No event participation records yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_footer(); ?>
