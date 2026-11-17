<?php
session_start();
require_once __DIR__.'/../config/database.php';
$pageTitle='Consumer Payment'; $base='../'; $error=''; $bill=null;
$ivrs=trim((string)($_GET['ivrs']??''));
if ($ivrs!=='') {
 if (!preg_match('/^[0-9]{5,20}$/',$ivrs)) $error='Enter a valid numeric IVRS.';
 else {
  $q=$pdo->prepare('SELECT b.*,c.full_name,c.ivrs_no FROM bills b JOIN consumers c ON c.id=b.consumer_id WHERE c.ivrs_no=? ORDER BY b.bill_month DESC,b.id DESC LIMIT 1');
  $q->execute([$ivrs]); $bill=$q->fetch();
  if (!$bill) $error='No bill found for this IVRS.';
 }
}
if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
include '../includes/header.php';
?>
<a href="../index.php">← Back to Home</a><section class="hero"><div class="eyebrow">MODULE 03 · PHP PAYMENT + MINIMAL AJAX</div><h1>Consumer Bill Payment</h1><p>Check bill by IVRS. Voucher is fetched from PHP using AJAX.</p></section>
<div class="panel mx-auto" style="max-width:760px">
<form method="get"><label class="form-label fw-bold" for="ivrs">Consumer IVRS</label><div class="input-group"><input class="form-control" id="ivrs" name="ivrs" value="<?=htmlspecialchars($ivrs)?>" inputmode="numeric" placeholder="1234567890" required><button class="btn btn-brand">Check Bill</button></div></form>
<?php if($error):?><div class="alert alert-danger mt-3"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($bill):?>
<hr><div class="d-flex justify-content-between"><span>Consumer</span><strong><?=htmlspecialchars($bill['full_name'])?></strong></div>
<div class="d-flex justify-content-between mt-2"><span>Bill Month</span><strong><?=htmlspecialchars($bill['bill_month'])?></strong></div>
<div class="d-flex justify-content-between mt-2"><span>Original Bill</span><strong>₹<?=number_format((float)$bill['bill_amount'],2)?></strong></div>
<div class="d-flex justify-content-between mt-2"><span>Subsidy Eligible</span><strong>₹<?=number_format((float)$bill['subsidy_amount'],2)?></strong></div>
<div class="d-flex justify-content-between mt-2"><span>Due Date</span><strong><?=htmlspecialchars($bill['due_date'])?></strong></div>
<div class="d-flex justify-content-between mt-2"><span>Bill Status</span><strong><?=htmlspecialchars($bill['status'])?></strong></div>
<?php if($bill['status']==='UNPAID'):?>
<div class="alert alert-info mt-3" id="voucher-status">Checking available voucher...</div>
<form method="post" action="gateway.php" id="payment-form">
<input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'])?>">
<input type="hidden" name="bill_id" value="<?=(int)$bill['id']?>">
<input type="hidden" name="ivrs" value="<?=htmlspecialchars($ivrs)?>">
<div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="use-voucher" name="use_voucher" value="1" disabled><label class="form-check-label" for="use-voucher">Apply available voucher</label></div>
<hr><div class="d-flex justify-content-between"><span>Voucher Discount</span><strong id="discount">₹0.00</strong></div>
<div class="d-flex justify-content-between mt-2"><strong>Final Payable</strong><h3 id="payable">₹<?=number_format((float)$bill['bill_amount'],2)?></h3></div>
<button class="btn btn-brand w-100 mt-3" type="submit">Pay Now</button>
</form>
<script>
// Only AJAX voucher lookup and UI preview; server recalculates payment independently.
const billId=<?= (int)$bill['id'] ?>, original=<?=json_encode((float)$bill['bill_amount'])?>;
const checkbox=document.getElementById('use-voucher'); let voucherAmount=0;
const money=n=>'₹'+n.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
function update(){document.getElementById('discount').textContent=money(checkbox.checked?voucherAmount:0);document.getElementById('payable').textContent=money(original-(checkbox.checked?voucherAmount:0));}
checkbox.addEventListener('change',update);
fetch('../api/check_voucher.php?bill_id='+encodeURIComponent(billId),{credentials:'same-origin'})
.then(r=>r.json()).then(data=>{const s=document.getElementById('voucher-status');s.textContent=data.message||'Voucher unavailable';if(data.available){voucherAmount=Number(data.amount);checkbox.disabled=false;s.className='alert alert-success mt-3';}else{s.className='alert alert-warning mt-3';}update();})
.catch(()=>{document.getElementById('voucher-status').textContent='Voucher lookup failed. You may pay the full bill.';});
</script>
<?php else:?><div class="alert alert-success mt-3">This bill has already been paid. <a href="bill_status.php?ivrs=<?=urlencode($ivrs)?>">View payment history</a>.</div><?php endif;?>
<?php endif;?></div>
<?php include '../includes/footer.php';?>
