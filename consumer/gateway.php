<?php
session_start();require_once __DIR__.'/../config/database.php';
$pageTitle='Dummy Payment Gateway';$base='../';$error='';$bill=null;
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: payment.php');exit;}
if(!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Invalid request token');}
$billId=filter_var($_POST['bill_id']??null,FILTER_VALIDATE_INT);
$ivrs=trim((string)($_POST['ivrs']??''));$useVoucher=isset($_POST['use_voucher']);
if(!$billId || !preg_match('/^[0-9]{5,20}$/',$ivrs)){http_response_code(400);exit('Invalid bill request');}
$q=$pdo->prepare('SELECT b.*,c.full_name,c.ivrs_no FROM bills b JOIN consumers c ON c.id=b.consumer_id WHERE b.id=? AND c.ivrs_no=?');$q->execute([$billId,$ivrs]);$bill=$q->fetch();
if(!$bill || $bill['status']!=='UNPAID'){http_response_code(409);exit('Bill unavailable or already paid.');}
$voucher=null;
if($useVoucher){$q=$pdo->prepare("SELECT * FROM vouchers WHERE bill_id=? AND status='ACTIVE' AND expires_at>NOW()");$q->execute([$billId]);$voucher=$q->fetch();if(!$voucher || (float)$voucher['amount']!==(float)$bill['subsidy_amount']){http_response_code(409);exit('Voucher is unavailable or expired. Return to bill page.');}}
$discount=$voucher?(float)$voucher['amount']:0;$payable=(float)$bill['bill_amount']-$discount;
// Store only bill selection, not client-supplied amounts. Recalculate again on completion.
$_SESSION['gateway']=['bill_id'=>$billId,'ivrs'=>$ivrs,'use_voucher'=>$useVoucher,'nonce'=>bin2hex(random_bytes(16))];
include '../includes/header.php';?>
<a href="payment.php?ivrs=<?=urlencode($ivrs)?>">← Back to Bill</a><section class="hero"><div class="eyebrow">SIMULATION ONLY</div><h1>Dummy Payment Gateway</h1><p>No real banking or payment transaction will occur.</p></section>
<div class="panel mx-auto" style="max-width:650px">
<div class="d-flex justify-content-between"><span>Consumer</span><strong><?=htmlspecialchars($bill['full_name'])?></strong></div>
<div class="d-flex justify-content-between mt-3"><span>Original Bill</span><strong>₹<?=number_format((float)$bill['bill_amount'],2)?></strong></div>
<div class="d-flex justify-content-between mt-3"><span>Voucher Adjustment</span><strong>− ₹<?=number_format($discount,2)?></strong></div>
<hr><div class="d-flex justify-content-between"><strong>Payable</strong><h3>₹<?=number_format($payable,2)?></h3></div>
<form method="post" action="process_payment.php"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'])?>"><input type="hidden" name="nonce" value="<?=htmlspecialchars($_SESSION['gateway']['nonce'])?>"><button class="btn btn-success w-100 mt-3" name="result" value="success">Simulate Successful Payment</button><button class="btn btn-outline-danger w-100 mt-2" name="result" value="failed">Simulate Failed Payment</button></form>
</div><?php include '../includes/footer.php';?>
