
<?php
require_once __DIR__ . '/../config/database.php';

// Read month filter
$selectedMonth = trim((string)($_GET['month'] ?? ''));

// Validate input
if (
    $selectedMonth !== '' &&
    !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)
) {
    http_response_code(400);
    exit('Invalid month format.');
}

// Prevent spreadsheet formula injection
function csvSafe($value)
{
    $value = (string)($value ?? '');

    if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
        return "'" . $value;
    }

    return $value;
}

try {

    $sql = "
        SELECT
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

} catch (Throwable $ex) {

    error_log($ex->getMessage());

    http_response_code(500);
    exit('Unable to export reconciliation report.');
}

// File name
$filename = 'erupi_reconciliation_';

$filename .= $selectedMonth !== ''
    ? $selectedMonth
    : 'all';

$filename .= '.csv';

// Download headers
header('Content-Type: text/csv; charset=UTF-8');
header(
    'Content-Disposition: attachment; filename="' .
    $filename . '"'
);
header('Cache-Control: no-store, no-cache');

// Open output stream
$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fwrite($output, "\xEF\xBB\xBF");

// CSV headings
fputcsv($output, [
    'IVRS',
    'Consumer Name',
    'Bill Month',
    'Original Bill',
    'Subsidy',
    'Expected After Voucher',
    'Collected',
    'Bill Status',
    'Voucher Code',
    'Voucher Status',
    'Reconciliation'
]);

foreach ($rows as $r) {

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
    } elseif (
        abs($collected - $expectedPayment) < 0.01
    ) {
        $reconciliation = 'MATCHED';
    } else {
        $reconciliation = 'MISMATCH';
    }

    fputcsv($output, [
        csvSafe($r['ivrs_no']),
        csvSafe($r['full_name']),
        csvSafe($r['bill_month']),
        number_format($billAmount, 2, '.', ''),
        number_format($subsidy, 2, '.', ''),
        number_format($afterVoucher, 2, '.', ''),
        number_format($collected, 2, '.', ''),
        csvSafe($billStatus),
        csvSafe($r['voucher_code'] ?? ''),
        csvSafe($voucherStatus ?: 'NOT ISSUED'),
        $reconciliation
    ]);
}

fclose($output);
exit;
