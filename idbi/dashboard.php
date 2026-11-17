<?php
session_start();
require '../config/database.php';
$pageTitle = 'IDBI Bank Dashboard';
$base = '../';
$error = '';
$success = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { throw new Exception('Invalid request token.'); }
        $billId = filter_var($_POST['bill_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$billId) { throw new Exception('Invalid bill ID.'); }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM bills WHERE id = ? FOR UPDATE');
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();
        if (!$bill || $bill['status'] !== 'UNPAID') { throw new Exception('Bill not eligible.'); }
        if (date('Y-m-d', time()) > $bill['due_date']) { throw new Exception('Bill due date passed.'); }
        if ((float)$bill['subsidy_amount'] <= 0) { throw new Exception('No subsidy for this bill.'); }
        $stmt = $pdo->prepare('SELECT id FROM vouchers WHERE bill_id = ?');
        $stmt->execute([$billId]);
        if ($stmt->fetch()) { throw new Exception('Voucher already exists for this bill.'); }
        $code = 'DEMO-' . strtoupper(bin2hex(random_bytes(6)));
        $expiry = $bill['due_date'] . ' 23:59:59 Asia/Kolkata';
        $stmt = $pdo->prepare("INSERT INTO vouchers (bill_id, voucher_code, amount, expires_at, status) VALUES (?, ?, ?, CAST(? AS timestamptz), 'ACTIVE')");
        $stmt->execute([$billId, $code, $bill['subsidy_amount'], $expiry]);
        $stmt = $pdo->prepare("INSERT INTO audit_logs(actor, action, entity_id) VALUES ('IDBI Demo', 'VOUCHER_ISSUED', ?)");
        $stmt->execute([$billId]);
        $pdo->commit();
        $_SESSION['notice'] = 'Voucher generated and available in consumer portal: ' . $code;
        header('Location: dashboard.php');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = $e->getMessage();
    }
}
$stmt = $pdo->query('SELECT b.*, c.ivrs_no, c.full_name, v.voucher_code, v.status AS voucher_status, v.expires_at FROM bills b JOIN consumers c ON c.id=b.consumer_id LEFT JOIN vouchers v ON v.bill_id=b.id ORDER BY b.id DESC');
$rows = $stmt->fetchAll();
include '../includes/header.php';
?>
<a href="../index.php">← Back to Home</a>
<section class="hero"><div class="eyebrow">MODULE 01 · CORE PHP + POSTGRESQL</div><h1>IDBI Bank Dashboard</h1><p>Generate one voucher per unpaid bill. Expiry is the bill due date.</p></section>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<div class="panel"><h5>Consumer List & Voucher Issuance</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>IVRS</th><th>Consumer</th><th>Bill</th><th>Subsidy</th><th>Due Date</th><th>Voucher Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($rows as $row):
$expired = $row['voucher_code'] && $row['voucher_status'] === 'ACTIVE' && strtotime($row['expires_at']) < time();
$status = $expired ? 'EXPIRED' : ($row['voucher_status'] ?: 'NOT ISSUED');
$canGenerate = !$row['voucher_code'] && $row['status'] === 'UNPAID' && date('Y-m-d') <= $row['due_date'] && (float)$row['subsidy_amount'] > 0;
?>
<tr><td><?= htmlspecialchars($row['ivrs_no']) ?></td><td><?= htmlspecialchars($row['full_name']) ?></td><td>₹<?= number_format((float)$row['bill_amount'],2) ?></td><td>₹<?= number_format((float)$row['subsidy_amount'],2) ?></td><td><?= htmlspecialchars($row['due_date']) ?></td><td><strong><?= htmlspecialchars($status) ?></strong><?php if ($row['voucher_code']): ?><br><small><?= htmlspecialchars($row['voucher_code']) ?></small><?php endif; ?></td><td>
<?php if ($canGenerate): ?><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input type="hidden" name="bill_id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-brand">Generate & Issue</button></form>
<?php elseif ($row['voucher_code']): ?><span class="text-success">Already Issued</span>
<?php else: ?><span class="text-muted">Not Eligible</span><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php include '../includes/footer.php'; ?>
