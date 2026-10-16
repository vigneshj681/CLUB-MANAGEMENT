<?php
require_once __DIR__ . '/db.php';
require_student();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('clubs.php');
}

verify_csrf();

$memberId = (int) $_SESSION['member_id'];
$clubId = (int) ($_POST['club_id'] ?? 0);
$action = $_POST['action'] ?? 'join';
$allowedReturns = ['clubs.php', 'profile.php', 'dashboard.php'];
$returnTo = in_array($_POST['return_to'] ?? '', $allowedReturns, true) ? $_POST['return_to'] : 'clubs.php';

$clubStmt = db()->prepare('SELECT * FROM `CLUB` WHERE club_id = ?');
$clubStmt->execute([$clubId]);
$club = $clubStmt->fetch();

if (!$club) {
    flash('error', 'Selected club was not found.');
    redirect($returnTo);
}

try {
    if ($action === 'join') {
        $currentStmt = db()->prepare('SELECT membership_status FROM `MEMBER_CLUB` WHERE member_id = ? AND club_id = ?');
        $currentStmt->execute([$memberId, $clubId]);
        $currentMembership = $currentStmt->fetch();

        if ($currentMembership && in_array($currentMembership['membership_status'], ['Pending', 'Approved'], true)) {
            flash('error', 'This club is already linked to your account.');
            redirect($returnTo);
        }

        db()->beginTransaction();

        if ($currentMembership) {
            $join = db()->prepare(
                'UPDATE `MEMBER_CLUB`
                 SET membership_status = "Pending", role_in_club = "Member", joined_at = CURRENT_TIMESTAMP,
                     approved_by = NULL, approved_at = NULL, removed_at = NULL
                 WHERE member_id = ? AND club_id = ?'
            );
            $join->execute([$memberId, $clubId]);
        } else {
            $join = db()->prepare(
                'INSERT INTO `MEMBER_CLUB` (member_id, club_id, role_in_club, membership_status)
                 VALUES (?, ?, "Member", "Pending")'
            );
            $join->execute([$memberId, $clubId]);
        }

        if ((float) $club['membership_fee'] > 0) {
            $paymentExists = db()->prepare(
                'SELECT COUNT(*)
                 FROM `PAYMENT`
                 WHERE member_id = ? AND club_id = ? AND payment_for = "Membership Fee"
                   AND payment_status IN ("Pending", "Paid")'
            );
            $paymentExists->execute([$memberId, $clubId]);

            if ((int) $paymentExists->fetchColumn() === 0) {
                $receipt = 'RCPT' . date('YmdHis') . random_int(100, 999);
                $payment = db()->prepare(
                    'INSERT INTO `PAYMENT` (member_id, club_id, amount, payment_for, receipt_number, payment_status)
                     VALUES (?, ?, ?, "Membership Fee", ?, "Pending")'
                );
                $payment->execute([$memberId, $clubId, $club['membership_fee'], $receipt]);
            }
        }

        db()->commit();
        flash('success', 'Your request to join ' . $club['club_name'] . ' is pending admin approval.');
    } elseif ($action === 'remove') {
        $remove = db()->prepare(
            'UPDATE `MEMBER_CLUB`
             SET membership_status = "Removed", removed_at = NOW()
             WHERE member_id = ? AND club_id = ? AND membership_status <> "Removed"'
        );
        $remove->execute([$memberId, $clubId]);
        flash('success', $club['club_name'] . ' removed from your account.');
    }
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    flash('error', 'Could not update club membership. Please try again.');
}

redirect($returnTo);
?>
