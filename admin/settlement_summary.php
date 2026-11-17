
<?php
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/voucher_expiry.php';

syncExpiredVouchers($pdo);

$pageTitle = 'Monthly Subsidy Settlement';
$base = '../';

$error = null;
$rows = [];

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

try {

    /*
     * Monthly summary:
     * One row per bill month.
     * Voucher amounts are derived from subsidy_amount.
     */

    $sql = "
        SELECT
            LEFT(CAST(b.bill_month AS TEXT), 7) AS month,

            COUNT(DISTINCT b.id) AS total_bills,

            COUNT(DISTINCT CASE
                WHEN v.voucher_code IS NOT NULL
                THEN b.id
            END) AS issued_vouchers,

            COUNT(DISTINCT CASE
                WHEN v.status = 'REDEEMED'
                THEN b.id
            END) AS redeemed_vouchers,

            COUNT(DISTINCT CASE
                WHEN v.status = 'ACTIVE'
                THEN b.id
            END) AS active_vouchers,

            COALESCE(
                SUM(
                    CASE
                        WHEN v.status = 'REDEEMED'
                        THEN b.subsidy_amount
                        ELSE 0
                    END
                ),
                0
            ) AS redeemed_subsidy,

            COALESCE(
                SUM(
                    CASE
                        WHEN v.status = 'ACTIVE'
                        THEN b.subsidy_amount
                        ELSE 0
                    END
                ),
                0
            ) AS active_subsidy,

            COALESCE(
                SUM(
                    CASE
                        WHEN v.status IN (
                            'EXPIRED',
                            'CLOSED_UNUSED'
                        )
                        THEN b.subsidy_amount
                        ELSE 0
                    END
                ),
                0
            ) AS closed_subsidy

        FROM bills b

        LEFT JOIN vouchers v
            ON v.bill_id = b.id

        GROUP BY
            LEFT(CAST(b.bill_month AS TEXT), 7)

        ORDER BY month DESC
    ";

    $stmt = $pdo->query($sql);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $ex) {

    $error = 'Unable to load settlement summary.';
    error_log($ex->getMessage());
}

// Overall totals
$totalIssued = 0;
$totalRedeemed = 0;
$totalActive = 0;

$totalRedeemedAmount = 0;
$totalActiveAmount = 0;
$totalClosedAmount = 0;

foreach ($rows as $row) {

    $totalIssued += (int)$row['issued_vouchers'];
    $totalRedeemed += (int)$row['redeemed_vouchers'];
    $totalActive += (int)$row['active_vouchers'];

    $totalRedeemedAmount +=
        (float)$row['redeemed_subsidy'];

    $totalActiveAmount +=
        (float)$row['active_subsidy'];

    $totalClosedAmount +=
        (float)$row['closed_subsidy'];
}

include '../includes/header.php';
?>

<a href="dashboard.php">← Back to Admin Dashboard</a>

<section class="hero">

    <div class="eyebrow">
        Module 02 · Subsidy Settlement
    </div>

    <h1>Monthly Subsidy Settlement</h1>

    <p>
        Month-wise voucher issuance, redemption
        and unused subsidy overview.
    </p>

</section>

<?php if ($error): ?>

    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>

<?php endif; ?>

<!-- OVERALL STATISTICS -->

<div class="row g-3 mb-4">

    <?php
    $stats = [
        ['Issued Vouchers', $totalIssued],
        ['Redeemed Vouchers', $totalRedeemed],
        ['Active Vouchers', $totalActive],
        ['Redeemed Subsidy', money($totalRedeemedAmount)],
        ['Active Subsidy', money($totalActiveAmount)],
        ['Closed / Expired Subsidy', money($totalClosedAmount)]
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

<!-- MONTHLY SETTLEMENT TABLE -->

<div class="panel mb-4">

    <h5 class="mb-3">
        Month-wise Subsidy Settlement Report
    </h5>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>
                    <th>Bill Month</th>
                    <th>Total Bills</th>
                    <th>Issued</th>
                    <th>Redeemed</th>
                    <th>Active</th>
                    <th>Redeemed Subsidy</th>
                    <th>Active Subsidy</th>
                    <th>Closed / Expired Subsidy</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($rows as $r): ?>

                    <tr>

                        <td>
                            <strong><?= e($r['month']) ?></strong>
                        </td>

                        <td>
                            <?= (int)$r['total_bills'] ?>
                        </td>

                        <td>
                            <?= (int)$r['issued_vouchers'] ?>
                        </td>

                        <td>
                            <?= (int)$r['redeemed_vouchers'] ?>
                        </td>

                        <td>
                            <?= (int)$r['active_vouchers'] ?>
                        </td>

                        <td>
                            <?= money($r['redeemed_subsidy']) ?>
                        </td>

                        <td>
                            <?= money($r['active_subsidy']) ?>
                        </td>

                        <td>
                            <?= money($r['closed_subsidy']) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($rows)): ?>

                    <tr>
                        <td colspan="8" class="text-center">
                            No settlement records found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php include '../includes/footer.php'; ?>
