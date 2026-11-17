
<?php
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Consumer Transaction Details';
$base = '../';

$consumer = null;
$bills = [];
$payments = [];
$error = null;

// Secure HTML output
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Format rupee amount
function money($amount)
{
    return '₹' . number_format((float)$amount, 2);
}

// Display status badge
function badge($status)
{
    $status = strtoupper((string)($status ?? 'UNKNOWN'));

    $colors = [
        'ACTIVE' => 'success',
        'PAID' => 'success',
        'SUCCESS' => 'success',
        'REDEEMED' => 'primary',
        'EXPIRED' => 'danger',
        'FAILED' => 'danger',
        'UNPAID' => 'warning',
        'PENDING' => 'warning',
        'CANCELLED' => 'secondary',
        'CLOSED_UNUSED' => 'secondary',
        'NOT ISSUED' => 'secondary'
    ];

    $color = $colors[$status] ?? 'secondary';

    return '<span class="badge bg-' . $color . '">' .
           e($status) .
           '</span>';
}

// Read IVRS from URL
$ivrs = trim((string)($_GET['ivrs'] ?? ''));

if ($ivrs === '') {
    $error = 'Please provide a consumer IVRS number.';
} elseif (
    strlen($ivrs) > 30 ||
    !preg_match('/^[0-9]+$/', $ivrs)
) {
    $error = 'Invalid IVRS number.';
} else {

    try {

        // 1. Consumer information
        $sql = "
            SELECT id, ivrs_no, full_name
            FROM consumers
            WHERE ivrs_no = :ivrs
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['ivrs' => $ivrs]);

        $consumer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$consumer) {
            $error = 'Consumer not found.';
        } else {

            $consumerId = $consumer['id'];

            // 2. Monthly bills and vouchers
            $billSql = "
                SELECT
                    b.id,
                    b.bill_month,
                    b.bill_amount,
                    b.subsidy_amount,
                    b.due_date,
                    b.status AS bill_status,
                    v.voucher_code,
                    v.amount AS voucher_amount,
                    v.status AS voucher_status,
                    v.expires_at
                FROM bills b
                LEFT JOIN vouchers v
                    ON v.bill_id = b.id
                WHERE b.consumer_id = :consumer_id
                ORDER BY b.id DESC
            ";

            $stmt = $pdo->prepare($billSql);

            $stmt->execute([
                'consumer_id' => $consumerId
            ]);

            $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Complete payment history
            $paymentSql = "
                SELECT
                    p.transaction_ref,
                    p.paid_amount,
                    p.status,
                    p.paid_at,
                    b.bill_month
                FROM payments p
                JOIN bills b
                    ON b.id = p.bill_id
                WHERE b.consumer_id = :consumer_id
                ORDER BY p.id DESC
            ";

            $stmt = $pdo->prepare($paymentSql);

            $stmt->execute([
                'consumer_id' => $consumerId
            ]);

            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Throwable $ex) {

        $error = 'Unable to fetch consumer details.';
        error_log($ex->getMessage());
    }
}

// Calculate statistics
$totalBills = count($bills);
$totalSubsidy = 0;
$redeemedAmount = 0;
$totalPaid = 0;

foreach ($bills as $bill) {

    $totalSubsidy += (float)$bill['subsidy_amount'];

    if ($bill['voucher_status'] === 'REDEEMED') {
        $redeemedAmount += (float)$bill['voucher_amount'];
    }
}

foreach ($payments as $payment) {

    if ($payment['status'] === 'SUCCESS') {
        $totalPaid += (float)$payment['paid_amount'];
    }
}

include '../includes/header.php';
?>

<a href="dashboard.php">← Back to Admin Dashboard</a>

<section class="hero">

    <div class="eyebrow">
        Module 02 · Consumer Details
    </div>

    <h1>Consumer Transaction Details</h1>

    <p>
        Monthly bills, subsidy vouchers and payment history.
    </p>

</section>

<?php if ($error): ?>

    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>

<?php endif; ?>

<?php if ($consumer && !$error): ?>

<!-- CONSUMER INFORMATION -->

<div class="panel mb-4">

    <h5 class="mb-3">Consumer Information</h5>

    <div class="row g-3">

        <div class="col-md-6">
            <small class="text-muted">Consumer Name</small>
            <h5><?= e($consumer['full_name']) ?></h5>
        </div>

        <div class="col-md-6">
            <small class="text-muted">IVRS Number</small>
            <h5><?= e($consumer['ivrs_no']) ?></h5>
        </div>

    </div>

</div>

<!-- CONSUMER STATISTICS -->

<div class="row g-3 mb-4">

    <div class="col-md-3 col-sm-6">
        <div class="stat h-100">
            <small>Total Bills</small>
            <strong><?= $totalBills ?></strong>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="stat h-100">
            <small>Total Eligible Subsidy</small>
            <strong><?= money($totalSubsidy) ?></strong>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="stat h-100">
            <small>Redeemed Subsidy</small>
            <strong><?= money($redeemedAmount) ?></strong>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="stat h-100">
            <small>Total Successful Payments</small>
            <strong><?= money($totalPaid) ?></strong>
        </div>
    </div>

</div>

<!-- MONTHLY BILL AND VOUCHER HISTORY -->

<div class="panel mb-4">

    <h5 class="mb-3">Monthly Bill & Voucher History</h5>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>
                <tr>
                    <th>Bill Month</th>
                    <th>Original Bill</th>
                    <th>Subsidy</th>
                    <th>After Voucher</th>
                    <th>Due Date</th>
                    <th>Bill Status</th>
                    <th>Voucher Code</th>
                    <th>Voucher Status</th>
                    <th>Expiry</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($bills as $bill): ?>

                <?php
                $original = (float)$bill['bill_amount'];
                $subsidy = (float)$bill['subsidy_amount'];

                $afterVoucher = max(
                    0,
                    $original - $subsidy
                );

                $voucherStatus =
                    $bill['voucher_status'] ?? 'NOT ISSUED';

                if (
                    $voucherStatus === 'ACTIVE' &&
                    !empty($bill['expires_at']) &&
                    strtotime($bill['expires_at']) < time()
                ) {
                    $voucherStatus = 'EXPIRED';
                }
                ?>

                <tr>

                    <td><?= e($bill['bill_month']) ?></td>

                    <td><?= money($original) ?></td>

                    <td><?= money($subsidy) ?></td>

                    <td>
                        <strong><?= money($afterVoucher) ?></strong>
                    </td>

                    <td><?= e($bill['due_date']) ?></td>

                    <td>
                        <?= badge($bill['bill_status']) ?>
                    </td>

                    <td>
                        <?= e($bill['voucher_code'] ?? '—') ?>
                    </td>

                    <td>
                        <?= badge($voucherStatus) ?>
                    </td>

                    <td>
                        <?= e($bill['expires_at'] ?? '—') ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            <?php if (empty($bills)): ?>
                <tr>
                    <td colspan="9" class="text-center">
                        No monthly bills found.
                    </td>
                </tr>
            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<!-- COMPLETE PAYMENT HISTORY -->

<div class="panel mb-4">

    <h5 class="mb-3">Payment Transaction History</h5>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>
                <tr>
                    <th>Transaction Reference</th>
                    <th>Bill Month</th>
                    <th>Amount</th>
                    <th>Payment Status</th>
                    <th>Payment Date</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($payments as $payment): ?>

                <tr>

                    <td>
                        <?= e($payment['transaction_ref'] ?? '—') ?>
                    </td>

                    <td>
                        <?= e($payment['bill_month']) ?>
                    </td>

                    <td>
                        <?= money($payment['paid_amount']) ?>
                    </td>

                    <td>
                        <?= badge($payment['status']) ?>
                    </td>

                    <td>
                        <?= e($payment['paid_at'] ?? '—') ?>
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

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
