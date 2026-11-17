
<?php
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/voucher_expiry.php';

syncExpiredVouchers($pdo);

$pageTitle = 'Admin Dashboard';
$base = '../';

$error = null;
$rows = [];
$payments = [];

// Display currency
function money($amount)
{
    return '₹' . number_format((float)$amount, 2);
}

// Escape output
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Voucher status including expiry
function getVoucherStatus($row)
{
    $status = $row['voucher_status'] ?? null;

    if (empty($status)) {
        return 'NOT ISSUED';
    }

    if (
        $status === 'ACTIVE' &&
        !empty($row['expires_at']) &&
        strtotime($row['expires_at']) < time()
    ) {
        return 'EXPIRED';
    }

    return $status;
}

// Badge color according to status
function statusBadge($status)
{
    $status = strtoupper((string)$status);

    $colors = [
        'ACTIVE'        => 'success',
        'REDEEMED'      => 'primary',
        'PAID'          => 'success',
        'SUCCESS'       => 'success',
        'EXPIRED'       => 'danger',
        'FAILED'        => 'danger',
        'CANCELLED'     => 'danger',
        'UNPAID'        => 'warning',
        'PENDING'       => 'warning',
        'CLOSED_UNUSED' => 'secondary',
        'NOT ISSUED'    => 'secondary'
    ];

    $color = $colors[$status] ?? 'secondary';

    return '<span class="badge bg-' . $color . '">' .
           e($status) .
           '</span>';
}

try {

    // Consumer, bill and voucher records
    $sql = "
        SELECT
            c.ivrs_no,
            c.full_name,
            b.bill_month,
            b.bill_amount,
            b.subsidy_amount,
            b.due_date,
            b.status AS bill_status,
            v.voucher_code,
            v.status AS voucher_status,
            v.expires_at
        FROM bills b
        JOIN consumers c
            ON c.id = b.consumer_id
        LEFT JOIN vouchers v
            ON v.bill_id = b.id
        ORDER BY b.id DESC
    ";

    $rows = $pdo->query($sql)->fetchAll(
        PDO::FETCH_ASSOC
    );

    // Recent payment transactions
    $paymentSql = "
        SELECT
            p.transaction_ref,
            p.paid_amount,
            p.status,
            p.paid_at,
            c.ivrs_no
        FROM payments p
        JOIN bills b
            ON b.id = p.bill_id
        JOIN consumers c
            ON c.id = b.consumer_id
        ORDER BY p.id DESC
        LIMIT 20
    ";

    $payments = $pdo->query($paymentSql)->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $ex) {
    $error = 'Unable to load dashboard data.';
    error_log($ex->getMessage());
}

// Statistics
$totalBills = count($rows);
$totalVouchers = 0;
$activeVouchers = 0;
$redeemedVouchers = 0;
$expiredVouchers = 0;

$successfulPayments = 0;
$failedPayments = 0;

foreach ($rows as $row) {

    if (!empty($row['voucher_code'])) {
        $totalVouchers++;
    }

    $status = getVoucherStatus($row);

    if ($status === 'ACTIVE') {
        $activeVouchers++;
    }

    if ($status === 'REDEEMED') {
        $redeemedVouchers++;
    }

    if ($status === 'EXPIRED') {
        $expiredVouchers++;
    }
}

foreach ($payments as $payment) {

    if ($payment['status'] === 'SUCCESS') {
        $successfulPayments++;
    }

    if ($payment['status'] === 'FAILED') {
        $failedPayments++;
    }
}

include '../includes/header.php';
?>

<a href="../index.php">← Back to Home</a>

<section class="hero">
    <div class="eyebrow">
        Module 02 · Database Connected
    </div>

    <h1>Admin Dashboard</h1>

    <p>
        Consumer, subsidy, voucher and payment overview.
    </p>
</section>

<div class="mb-4">
    <a
        href="settlement_summary.php"
        class="btn btn-primary"
    >
        View Monthly Subsidy Settlement
    </a>
</div>

<div class="mb-4">
    <a
        href="reconciliation.php"
        class="btn btn-primary"
    >
        View Payment Reconciliation Report
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- DASHBOARD STATISTICS -->

<div class="row g-3 mb-4">

    <?php
    $stats = [
        ['Monthly Bills', $totalBills],
        ['Issued Vouchers', $totalVouchers],
        ['Active Vouchers', $activeVouchers],
        ['Redeemed Vouchers', $redeemedVouchers],
        ['Expired Vouchers', $expiredVouchers],
        ['Payment Attempts (Recent)', count($payments)],
        ['Successful (Recent)', $successfulPayments],
        ['Failed (Recent)', $failedPayments]
    ];
    ?>

    <?php foreach ($stats as $stat): ?>

        <div class="col-md-3 col-sm-6">
            <div class="stat h-100">
                <small><?= e($stat[0]) ?></small>
                <strong><?= (int)$stat[1] ?></strong>
            </div>
        </div>

    <?php endforeach; ?>

</div>

<!-- BILL SUMMARY -->

<div class="panel mb-4">

    <h5 class="mb-3">Bill & Subsidy Summary</h5>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>
                <tr>
                    <th>IVRS</th>
                    <th>Consumer</th>
                    <th>Bill Month</th>
                    <th>Bill</th>
                    <th>Subsidy</th>
                    <th>After Voucher</th>
                    <th>Due Date</th>
                    <th>Bill Status</th>
                    <th>Voucher Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($rows as $r): ?>

                    <?php
                    $billAmount = (float)$r['bill_amount'];
                    $subsidy = (float)$r['subsidy_amount'];

                    $afterVoucher = max(
                        0,
                        $billAmount - $subsidy
                    );

                    $voucherStatus = getVoucherStatus($r);
                    ?>

                    <tr>

                        <td><?= e($r['ivrs_no']) ?></td>

                        <td><?= e($r['full_name']) ?></td>

                        <td><?= e($r['bill_month']) ?></td>

                        <td><?= money($billAmount) ?></td>

                        <td><?= money($subsidy) ?></td>

                        <td>
                            <strong>
                                <?= money($afterVoucher) ?>
                            </strong>
                        </td>

                        <td><?= e($r['due_date']) ?></td>

                        <td>
                            <?= statusBadge($r['bill_status']) ?>
                        </td>

                        <td>
                            <?= statusBadge($voucherStatus) ?>
                        </td>
                        <td>
                            <a
                                href="consumer_details.php?ivrs=<?= urlencode($r['ivrs_no']) ?>"
                                class="btn btn-sm btn-outline-primary"
                            >
                                View Details
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="10" class="text-center">
                            No bill records found.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- PAYMENT TRANSACTIONS -->

<div class="panel mb-4">

    <h5 class="mb-3">Recent Payment Transactions</h5>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>
                <tr>
                    <th>Transaction Reference</th>
                    <th>Consumer IVRS</th>
                    <th>Paid Amount</th>
                    <th>Payment Status</th>
                    <th>Paid At</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($payments as $t): ?>

                    <tr>

                        <td>
                            <?= e($t['transaction_ref'] ?? '—') ?>
                        </td>

                        <td>
                            <?= e($t['ivrs_no']) ?>
                        </td>

                        <td>
                            <?= money($t['paid_amount']) ?>
                        </td>

                        <td>
                            <?= statusBadge($t['status']) ?>
                        </td>

                        <td>
                            <?= e($t['paid_at'] ?? '—') ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="5" class="text-center">
                            No payment transactions found.
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include '../includes/footer.php'; ?>
