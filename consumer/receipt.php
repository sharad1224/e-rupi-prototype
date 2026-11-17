<?php
// Lightweight PDF receipt: built-in PDF writer, no Composer dependency.
// PDF core Helvetica supports ASCII; monetary amounts use INR rather than rupee glyph.
require_once __DIR__.'/../config/database.php';
$ref=trim((string)($_GET['ref']??''));
if(!preg_match('/^DEMO-TXN-[A-F0-9]{16}$/',$ref)){http_response_code(400);exit('Invalid reference');}
$q=$pdo->prepare("SELECT p.*,c.ivrs_no,c.full_name,b.bill_month FROM payments p JOIN bills b ON b.id=p.bill_id JOIN consumers c ON c.id=b.consumer_id WHERE p.transaction_ref=? AND p.status='SUCCESS'");$q->execute([$ref]);$p=$q->fetch();
if(!$p){http_response_code(404);exit('Successful transaction not found');}
$lines=['E-RUPI PROTOTYPE - DEMO RECEIPT','SIMULATED PAYMENT - NOT A BANK RECEIPT','', 'Transaction Ref: '.$p['transaction_ref'],'Consumer: '.$p['full_name'],'IVRS: '.$p['ivrs_no'],'Bill Month: '.$p['bill_month'],'Paid At: '.$p['paid_at'],'Original Bill: INR '.number_format((float)$p['original_amount'],2),'Voucher Discount: INR '.number_format((float)$p['discount_amount'],2),'Amount Paid: INR '.number_format((float)$p['paid_amount'],2),'Status: SUCCESS','', 'For demonstration purposes only.'];
$stream="BT /F1 12 Tf 50 790 Td 17 TL ";
foreach($lines as $i=>$line){$line=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$line);$line=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$stream.=($i?'T* ':'').'('.$line.') Tj ';}
$stream.='ET';
$objects=[null,'<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>','<< /Length '.strlen($stream).' >>' ."
stream
".$stream."
endstream",'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
$pdf="%PDF-1.4
";$offsets=[0];for($i=1;$i<=5;$i++){$offsets[$i]=strlen($pdf);$pdf.="$i 0 obj
".$objects[$i]."
endobj
";}
$xref=strlen($pdf);$pdf.="xref
0 6
0000000000 65535 f 
";for($i=1;$i<=5;$i++)$pdf.=sprintf('%010d 00000 n ', $offsets[$i])."
";
$pdf.="trailer
<< /Size 6 /Root 1 0 R >>
startxref
$xref
%%EOF";
header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="receipt-'.$ref.'.pdf"');header('Content-Length: '.strlen($pdf));header('Cache-Control: private, no-store');echo $pdf;
