
<?php

/**
 * Synchronize expired vouchers in PostgreSQL.
 *
 * Updates only ACTIVE vouchers whose expiry
 * timestamp has passed.
 *
 * Returns number of updated vouchers.
 */

function syncExpiredVouchers(PDO $pdo): int
{
    $sql = "
        UPDATE vouchers
        SET status = 'EXPIRED'
        WHERE status = 'ACTIVE'
          AND expires_at IS NOT NULL
          AND expires_at < CURRENT_TIMESTAMP
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->rowCount();
}
