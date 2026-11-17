<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$base = $base ?? '';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($pageTitle) ?> | E-RUPI Prototype</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="<?= $base ?>assets/css/style.css"></head><body>
<header class="site-header"><div class="container d-flex justify-content-between align-items-center py-3"><a href="<?= $base ?>index.php" class="brand"><span class="brand-icon">₹</span><span>E-RUPI <small>PROTOTYPE</small></span></a><span class="demo-tag">● Demo Environment</span></div></header>
<main class="container py-5">
