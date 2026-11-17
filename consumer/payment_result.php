<?php
require_once __DIR__.'/../config/database.php';
$ref=trim((string)($_GET['ref']??''));if(!preg_match('/^DEMO-TXN-[A-F0-9]{16}$/',$ref)){http_response_code(400);exit('Invalid reference');}
$q=$pdo->prepare('SELECT p.*,c.ivrs_no,c.full_name,b.bill_month FROM payments p JOIN bills b ON b.id=p.bill_id JOIN consumers c ON c.id=b.consumer_id WHERE p.transaction_ref=?');$q->execute([$ref]);$p=$q->fetch();if(!$p){http_response_code(404);exit('Transaction not found');}
$pageTitle='Payment Result';$base='../';include '../includes/header.php';?>
<a href="../index.php">← Home</a><section class="hero"><div class="eyebrow">DEMO TRANSACTION</div><h1>Payment <?=htmlspecialchars($p['status']==='SUCCESS'?'Successful':'Failed')?></h1></section>
<div class="panel mx-auto" style="max-width:650px"><p><strong>Transaction:</strong> <?=htmlspecialchars($p['transaction_ref'])?></p><p><strong>Consumer:</strong> <?=htmlspecialchars($p['full_name'])?></p><p><strong>Original:</strong> ₹<?=number_format((float)$p['original_amount'],2)?></p><p><strong>Voucher:</strong> ₹<?=number_format((float)$p['discount_amount'],2)?></p><h3>Paid/Attempted: ₹<?=number_format((float)$p['paid_amount'],2)?></h3>
<?php if($p['status']==='SUCCESS'):?><a class="btn btn-brand" href="receipt.php?ref=<?=urlencode($ref)?>" target="_blank">Download PDF Receipt</a><?php else:?><a class="btn btn-brand" href="payment.php?ivrs=<?=urlencode($p['ivrs_no'])?>">Try Again</a><?php endif;?> <a class="btn btn-outline-primary" href="bill_status.php?ivrs=<?=urlencode($p['ivrs_no'])?>">Track Payment</a></div>
<?php include '../includes/footer.php';?>
