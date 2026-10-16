<?php
require_once __DIR__ . '/db.php';
require_student();

$memberId = (int) $_SESSION['member_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $method = trim($_POST['payment_method'] ?? 'Online');

    $stmt = db()->prepare(
        'UPDATE `PAYMENT`
         SET payment_status = "Paid", payment_method = ?, paid_at = NOW()
         WHERE payment_id = ? AND member_id = ? AND payment_status = "Pending"'
    );
    $stmt->execute([$method, $paymentId, $memberId]);

    flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Payment marked as paid.' : 'Payment could not be updated.');
    redirect('payments.php');
}

$payments = db()->prepare(
    'SELECT p.*, c.club_name
     FROM `PAYMENT` p
     LEFT JOIN `CLUB` c ON c.club_id = p.club_id
     WHERE p.member_id = ?
     ORDER BY p.created_at DESC'
);
$payments->execute([$memberId]);
$records = $payments->fetchAll();

$totalPaid = 0.0;
$totalDue = 0.0;
foreach ($records as $record) {
    if ($record['payment_status'] === 'Paid') {
        $totalPaid += (float) $record['amount'];
    } else {
        $totalDue += (float) $record['amount'];
    }
}

app_header('Payment Management', 'payments');
?>
<section class="dashboard-grid compact">
    <div class="stat-card">
        <span class="stat-icon">PD</span>
        <p>Total Paid</p>
        <strong><?php echo money($totalPaid); ?></strong>
    </div>
    <div class="stat-card">
        <span class="stat-icon">DU</span>
        <p>Total Due</p>
        <strong><?php echo money($totalDue); ?></strong>
    </div>
    <div class="stat-card">
        <span class="stat-icon">RC</span>
        <p>Receipts</p>
        <strong><?php echo count($records); ?></strong>
    </div>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <p class="eyebrow">Membership Fee Records</p>
            <h2>Payment History</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Receipt No.</th>
                    <th>Purpose</th>
                    <th>Club</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Method</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php echo e($record['receipt_number']); ?></td>
                        <td><?php echo e($record['payment_for']); ?></td>
                        <td><?php echo e($record['club_name'] ?? 'General'); ?></td>
                        <td><?php echo money((float) $record['amount']); ?></td>
                        <td><?php echo badge($record['payment_status'], $record['payment_status'] === 'Paid' ? 'green' : 'orange'); ?></td>
                        <td><?php echo e($record['payment_method'] ?? '-'); ?></td>
                        <td>
                            <?php if ($record['payment_status'] === 'Pending'): ?>
                                <form method="POST" class="inline-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="payment_id" value="<?php echo (int) $record['payment_id']; ?>">
                                    <select name="payment_method" aria-label="Payment method">
                                        <option>Online</option>
                                        <option>UPI</option>
                                        <option>Cash</option>
                                    </select>
                                    <button class="tiny-button" type="submit">Mark Paid</button>
                                </form>
                            <?php else: ?>
                                <?php echo $record['paid_at'] ? date('d M Y', strtotime($record['paid_at'])) : '-'; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?>
                    <tr><td colspan="7">No payment records yet. Joining paid clubs will create membership fee records.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_footer(); ?>
