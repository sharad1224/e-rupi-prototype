
<?php

session_start();

require_once __DIR__ . '/../config/database.php';

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

// Validate payment session
if (
    !isset($_SESSION['gateway']) ||
    !is_array($_SESSION['gateway']) ||
    !isset($_SESSION['csrf']) ||
    !isset($_SESSION['gateway']['nonce']) ||
    !is_string($_SESSION['csrf']) ||
    !is_string($_SESSION['gateway']['nonce']) ||
    !hash_equals(
        $_SESSION['csrf'],
        (string)($_POST['csrf'] ?? '')
    ) ||
    !hash_equals(
        $_SESSION['gateway']['nonce'],
        (string)($_POST['nonce'] ?? '')
    )
) {
    http_response_code(403);
    exit('Invalid or expired payment session.');
}

// Read gateway session
$gateway = $_SESSION['gateway'];

// Prevent reuse of gateway session
unset($_SESSION['gateway']);

// Validate dummy payment result
$result = $_POST['result'] ?? '';

if (!in_array($result, ['success', 'failed'], true)) {
    http_response_code(400);
    exit('Invalid payment action.');
}

// Validate required gateway data
if (
    empty($gateway['bill_id']) ||
    empty($gateway['ivrs']) ||
    !array_key_exists('use_voucher', $gateway)
) {
    http_response_code(400);
    exit('Incomplete payment session.');
}

try {

    // Begin database transaction
    $pdo->beginTransaction();

    /*
     * STEP 1:
     * Lock bill row to prevent simultaneous payments.
     */

    $sql = "
        SELECT b.*
        FROM bills b
        JOIN consumers c
            ON c.id = b.consumer_id
        WHERE b.id = ?
          AND c.ivrs_no = ?
        FOR UPDATE OF b
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $gateway['bill_id'],
        $gateway['ivrs']
    ]);

    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill || $bill['status'] !== 'UNPAID') {
        throw new RuntimeException(
            'Bill already paid or unavailable.'
        );
    }

    /*
     * STEP 2:
     * Validate and lock voucher when requested.
     */

    $voucher = null;

    if ($gateway['use_voucher']) {

        $sql = "
            SELECT
                *,
                (expires_at >= CURRENT_TIMESTAMP)
                    AS is_valid_time
            FROM vouchers
            WHERE bill_id = ?
            FOR UPDATE
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$bill['id']]);

        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voucher) {
            throw new RuntimeException(
                'Voucher not found.'
            );
        }

        if ($voucher['status'] !== 'ACTIVE') {
            throw new RuntimeException(
                'Voucher is not active.'
            );
        }

        // PostgreSQL returns boolean as t/f or true/false
        $validTime = in_array(
            $voucher['is_valid_time'],
            [true, 1, '1', 't', 'true'],
            true
        );

        if (!$validTime) {
            throw new RuntimeException(
                'Voucher has expired. Please retry from bill page.'
            );
        }

        // Compare currency amounts to two decimal places
        $voucherAmount = round(
            (float)$voucher['amount'],
            2
        );

        $subsidyAmount = round(
            (float)$bill['subsidy_amount'],
            2
        );

        if ($voucherAmount !== $subsidyAmount) {
            throw new RuntimeException(
                'Voucher amount does not match subsidy.'
            );
        }

        if (
            $voucherAmount < 0 ||
            $voucherAmount > (float)$bill['bill_amount']
        ) {
            throw new RuntimeException(
                'Invalid voucher amount.'
            );
        }
    }

    /*
     * STEP 3:
     * Calculate payable amount.
     */

    $originalAmount = round(
        (float)$bill['bill_amount'],
        2
    );

    $discountAmount = $voucher
        ? round((float)$voucher['amount'], 2)
        : 0;

    $payableAmount = round(
        $originalAmount - $discountAmount,
        2
    );

    if ($payableAmount < 0) {
        throw new RuntimeException(
            'Invalid payment amount.'
        );
    }

    /*
     * STEP 4:
     * Generate unique demo transaction reference.
     */

    $transactionRef = 'DEMO-TXN-' .
        strtoupper(bin2hex(random_bytes(8)));

    $paymentStatus = $result === 'success'
        ? 'SUCCESS'
        : 'FAILED';

    /*
     * STEP 5:
     * Insert payment transaction.
     */

    $sql = "
        INSERT INTO payments (
            bill_id,
            voucher_id,
            transaction_ref,
            original_amount,
            discount_amount,
            paid_amount,
            status,
            paid_at
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            CASE
                WHEN ? = 'SUCCESS' THEN NOW()
                ELSE NULL
            END
        )
        RETURNING id
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $bill['id'],
        $voucher['id'] ?? null,
        $transactionRef,
        $originalAmount,
        $discountAmount,
        $payableAmount,
        $paymentStatus,
        $paymentStatus
    ]);

    $paymentId = $stmt->fetchColumn();

    /*
     * STEP 6:
     * Update bill and voucher only on success.
     */

    if ($paymentStatus === 'SUCCESS') {

        // Mark bill as paid
        $stmt = $pdo->prepare("
            UPDATE bills
            SET status = 'PAID'
            WHERE id = ?
              AND status = 'UNPAID'
        ");

        $stmt->execute([$bill['id']]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to update bill status.'
            );
        }

        if ($voucher) {

            /*
             * Redeem voucher only if still active
             * and not expired.
             */

            $stmt = $pdo->prepare("
                UPDATE vouchers
                SET
                    status = 'REDEEMED',
                    redeemed_at = NOW()
                WHERE id = ?
                  AND status = 'ACTIVE'
                  AND expires_at >= CURRENT_TIMESTAMP
                RETURNING id
            ");

            $stmt->execute([$voucher['id']]);

            $redeemedId = $stmt->fetchColumn();

            if (!$redeemedId) {
                throw new RuntimeException(
                    'Voucher redemption failed.'
                );
            }

            /*
             * Record redemption in audit history.
             */

            $stmt = $pdo->prepare("
                INSERT INTO voucher_transactions (
                    voucher_id,
                    payment_id,
                    action
                )
                VALUES (?, ?, 'REDEEMED')
            ");

            $stmt->execute([
                $voucher['id'],
                $paymentId
            ]);

        } else {

            /*
             * Full bill payment without voucher:
             * close unused voucher.
             */

            $stmt = $pdo->prepare("
                UPDATE vouchers
                SET status = 'CLOSED_UNUSED'
                WHERE bill_id = ?
                  AND status IN ('ACTIVE', 'GENERATED')
            ");

            $stmt->execute([$bill['id']]);
        }
    }

    /*
     * STEP 7:
     * Commit all database changes.
     */

    $pdo->commit();

    // Redirect to payment result
    header(
        'Location: payment_result.php?ref=' .
        urlencode($transactionRef)
    );

    exit;

} catch (Throwable $ex) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($ex->getMessage());

    http_response_code(409);

    echo 'Payment could not be processed. ';
    echo 'Please retry from the bill page. ';
    echo '<a href="payment.php">Back to payment</a>';

    exit;
}
