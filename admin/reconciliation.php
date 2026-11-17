
<?php
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Payment Reconciliation';
$base = '../';

$error = null;
$rows = [];

// Read selected month from URL
$selectedMonth = trim((string)($_GET['month'] ?? ''));

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function money($amount)
{
    return '₹' . number_format((float)$amount, 2);
}

// Validate month: YYYY-MM
if (
    $selectedMonth !== '' &&
    !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)
) {
    $error = 'Invalid month format.';
}

try {

    if (!$error) {

        $sql = "
            SELECT
                b.id AS bill_id,
                c.ivrs_no,
                c.full_name,
                b.bill_month,
                b.bill_amount,
                b.subsidy_amount,
                b.status AS bill_status,
                v.voucher_code,
                v.status AS voucher_status,

                COALESCE(
                    (
                        SELECT SUM(p.paid_amount)
                        FROM payments p
                        WHERE p.bill_id = b.id
                          AND UPPER(p.status) = 'SUCCESS'
                    ),
                    0
                ) AS total_paid

            FROM bills b

            JOIN consumers c
                ON c.id = b.consumer_id

            LEFT JOIN vouchers v
                ON v.bill_id = b.id

            WHERE (
                :month_filter = ''
                OR LEFT(CAST(b.bill_month AS TEXT), 7) = :month_value
            )

            ORDER BY b.id DESC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'month_filter' => $selectedMonth,
            'month_value' => $selectedMonth
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $ex) {

    $error = 'Unable to load reconciliation report.';
    error_log($ex->getMessage());
}

// Statistics
$totalBills = count($rows);
$paidBills = 0;
$unpaidBills = 0;

$totalCollected = 0;
$totalRedeemedSubsidy = 0;
$totalActiveSubsidy = 0;

foreach ($rows as $row) {

    $billStatus = strtoupper(
        (string)$row['bill_status']
    );

    $voucherStatus = strtoupper(
        (string)($row['voucher_status'] ?? '')
    );

    if ($billStatus === 'PAID') {
        $paidBills++;
    } else {
        $unpaidBills++;
    }

    $totalCollected += (float)$row['total_paid'];

    if ($voucherStatus === 'REDEEMED') {
        $totalRedeemedSubsidy +=
            (float)$row['subsidy_amount'];
    }

    if ($voucherStatus === 'ACTIVE') {
        $totalActiveSubsidy +=
            (float)$row['subsidy_amount'];
    }
}

include '../includes/header.php';
?>

<a href="dashboard.php">← Back to Admin Dashboard</a>

<section class="hero">

    <div class="eyebrow">
        Module 02 · Financial Reporting
    </div>

    <h1>Payment Reconciliation</h1>

    <p>
        Filter electricity bills and export subsidy reports.
    </p>

</section>

<?php if ($error): ?>

    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>

<?php endif; ?>

<!-- MONTH FILTER -->

<div class="panel mb-4">

    <h5 class="mb-3">Filter Report</h5>

    <form method="GET" action="reconciliation.php">

        <div class="row g-3 align-items-end">

            <div class="col-md-4">

                <label class="form-label">
                    Select Bill Month
                </label>

                <input
                    type="month"
                    name="month"
                    class="form-control"
                    value="<?= e($selectedMonth) ?>"
                >

            </div>

            <div class="col-md-8">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Apply Filter
                </button>

                <a
                    href="reconciliation.php"
                    class="btn btn-outline-secondary"
                >
                    Reset
                </a>

                <?php if (!$error): ?>

                    <a
                        href="export_reconciliation.php?month=<?= urlencode($selectedMonth) ?>"
                        class="btn btn-success"
                    >
                        Export CSV
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </form>

</div>

<!-- REPORT STATISTICS -->

<div class="row g-3 mb-4">

    <?php
    $stats = [
        ['Total Bills', $totalBills],
        ['Paid Bills', $paidBills],
        ['Unpaid / Other Bills', $unpaidBills],
        ['Total Collected', money($totalCollected)],
        ['Redeemed Subsidy', money($totalRedeemedSubsidy)],
        ['Active Voucher Value', money($totalActiveSubsidy)]
    ];
    ?>

    <?php foreach ($stats as $stat): ?>

        <div class="col-md-4 col-sm-6">

            <div class="stat h-100">

                <small><?= e($stat[0]) ?></small>

                <strong><?= e($stat[1]) ?></strong>

            </div>

        </div>

    <?php endforeach; ?>

</div>

<!-- RECONCILIATION TABLE -->

<div class="panel mb-4">

    <h5 class="mb-3">
        Consumer-wise Reconciliation
    </h5>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>
                    <th>IVRS</th>
                    <th>Consumer</th>
                    <th>Bill Month</th>
                    <th>Original Bill</th>
                    <th>Subsidy</th>
                    <th>Expected After Voucher</th>
                    <th>Collected</th>
                    <th>Bill Status</th>
                    <th>Voucher Status</th>
                    <th>Reconciliation</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($rows as $r): ?>

                    <?php

                    $billAmount = (float)$r['bill_amount'];
                    $subsidy = (float)$r['subsidy_amount'];
                    $collected = (float)$r['total_paid'];

                    $afterVoucher = max(
                        0,
                        $billAmount - $subsidy
                    );

                    $billStatus = strtoupper(
                        (string)$r['bill_status']
                    );

                    $voucherStatus = strtoupper(
                        (string)($r['voucher_status'] ?? '')
                    );

                    $expectedPayment = $billAmount;

                    if ($voucherStatus === 'REDEEMED') {
                        $expectedPayment = $afterVoucher;
                    }

                    if ($billStatus !== 'PAID') {

                        $reconciliation = 'PENDING';
                        $badgeColor = 'warning';

                    } elseif (
                        abs($collected - $expectedPayment) < 0.01
                    ) {

                        $reconciliation = 'MATCHED';
                        $badgeColor = 'success';

                    } else {

                        $reconciliation = 'MISMATCH';
                        $badgeColor = 'danger';

                    }

                    ?>

                    <tr>

                        <td><?= e($r['ivrs_no']) ?></td>

                        <td><?= e($r['full_name']) ?></td>

                        <td><?= e($r['bill_month']) ?></td>

                        <td><?= money($billAmount) ?></td>

                        <td><?= money($subsidy) ?></td>

                        <td><?= money($afterVoucher) ?></td>

                        <td><?= money($collected) ?></td>

                        <td><?= e($billStatus) ?></td>

                        <td>
                            <?= e($voucherStatus ?: 'NOT ISSUED') ?>
                        </td>

                        <td>
                            <span class="badge bg-<?= $badgeColor ?>">
                                <?= $reconciliation ?>
                            </span>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($rows)): ?>

                    <tr>
                        <td colspan="10" class="text-center">
                            No records found for selected month.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include '../includes/footer.php'; ?>
