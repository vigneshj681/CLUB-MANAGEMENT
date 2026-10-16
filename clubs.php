<?php
require_once __DIR__ . '/db.php';
require_student();

$memberId = (int) $_SESSION['member_id'];

$clubStmt = db()->prepare(
    'SELECT c.*,
            COUNT(DISTINCT CASE WHEN mc.membership_status = "Approved" THEN mc.member_id END) AS member_count,
            own.membership_status
     FROM `CLUB` c
     LEFT JOIN `MEMBER_CLUB` mc ON mc.club_id = c.club_id
     LEFT JOIN `MEMBER_CLUB` own ON own.club_id = c.club_id AND own.member_id = ?
     GROUP BY c.club_id, c.club_name, c.description, c.category, c.icon, c.membership_fee, c.created_at, c.updated_at, own.membership_status
     ORDER BY c.club_name'
);
$clubStmt->execute([$memberId]);
$clubs = $clubStmt->fetchAll();

app_header('Club Joining System', 'clubs');
?>
<section class="toolbar panel">
    <div>
        <p class="eyebrow">Explore</p>
        <h2>Available Clubs</h2>
    </div>
    <label class="search-box">
        <span>Search</span>
        <input type="search" placeholder="Search club name or category" data-filter-target=".club-card">
    </label>
</section>

<section class="club-grid">
    <?php foreach ($clubs as $club): ?>
        <?php
            $membershipStatus = $club['membership_status'] ?? '';
            $hasActiveRequest = in_array($membershipStatus, ['Pending', 'Approved'], true);
            $statusLabel = $membershipStatus === 'Approved' ? 'Joined' : ($membershipStatus === 'Pending' ? 'Pending' : 'Open');
            $statusClass = $membershipStatus === 'Approved' ? 'green' : ($membershipStatus === 'Pending' ? 'orange' : 'blue');
        ?>
        <article class="club-card" data-filter-text="<?php echo e(strtolower($club['club_name'] . ' ' . $club['category'])); ?>">
            <div class="club-card-top">
                <span class="club-icon large-icon"><?php echo e($club['icon']); ?></span>
                <?php echo badge($statusLabel, $statusClass); ?>
            </div>
            <h2><?php echo e($club['club_name']); ?></h2>
            <p><?php echo e($club['description']); ?></p>
            <div class="club-meta">
                <span><?php echo e($club['category']); ?></span>
                <span><?php echo (int) $club['member_count']; ?> members</span>
                <span><?php echo money((float) $club['membership_fee']); ?></span>
            </div>
            <form method="POST" action="join_club.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="club_id" value="<?php echo (int) $club['club_id']; ?>">
                <input type="hidden" name="action" value="<?php echo $hasActiveRequest ? 'remove' : 'join'; ?>">
                <input type="hidden" name="return_to" value="clubs.php">
                <button class="btn <?php echo $hasActiveRequest ? 'danger' : 'primary'; ?> full" type="submit">
                    <?php echo $membershipStatus === 'Pending' ? 'Cancel Request' : ($membershipStatus === 'Approved' ? 'Leave Club' : 'Request to Join'); ?>
                </button>
            </form>
        </article>
    <?php endforeach; ?>
</section>
<?php app_footer(); ?>
