<?php
require_once __DIR__.'/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$billId=filter_input(INPUT_GET,'bill_id',FILTER_VALIDATE_INT);
if(!$billId){http_response_code(400);echo json_encode(['available'=>false,'message'=>'Invalid bill ID']);exit;}
$q=$pdo->prepare("SELECT v.amount,v.voucher_code,v.status,v.expires_at,b.status AS bill_status,b.subsidy_amount FROM bills b LEFT JOIN vouchers v ON v.bill_id=b.id WHERE b.id=?");
$q->execute([$billId]);$r=$q->fetch();
if(!$r){http_response_code(404);echo json_encode(['available'=>false,'message'=>'Bill not found']);exit;}
$valid=$r['bill_status']==='UNPAID' && $r['status']==='ACTIVE' && $r['expires_at']!==null && strtotime($r['expires_at'])>=time() && (float)$r['amount']==(float)$r['subsidy_amount'];
echo json_encode(['available'=>$valid,'amount'=>$valid?(float)$r['amount']:0,'message'=>$valid?'Voucher '.$r['voucher_code'].' available: INR '.$r['amount']:'No valid voucher available for this bill']);
