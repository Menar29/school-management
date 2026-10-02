<?php $poste = Auth::poste(); $ps = site($poste['site_id'] ?? null); ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'Connexion') ?> · <?= e(config('pharmacie')) ?></title>
<meta name="theme-color" content="#0F1D18">
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>
<div class="auth">
  <section class="auth-l">
    <div style="display:flex;align-items:center;gap:12px">
      <span class="logo" style="width:40px;height:40px"><?= icon('cross') ?></span>
      <span style="color:#fff;font-weight:600;font-size:17px"><?= e(config('pharmacie')) ?></span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <span class="cap" style="color:#86A096">Ce poste</span>
      <?php if ($poste): ?>
        <span style="color:#fff;font-size:34px;line-height:40px;font-weight:600;letter-spacing:-.02em"><?= e($ps ? ($ps['type'] === 'officine' ? 'Officine ' . $ps['nom_court'] : $ps['nom']) : 'Tous les sites') ?></span>
        <span style="font-size:16px"><?= e($poste['nom']) ?></span>
      <?php else: ?>
        <span style="color:#fff;font-size:30px;line-height:38px;font-weight:600">Poste non enregistré</span>
        <span style="font-size:15px">Les vendeurs se connectent sur un poste enregistré par le propriétaire.</span>
      <?php endif; ?>
      <span class="clock" data-clock><?= date('H:i') ?></span>
      <span style="font-size:16px"><?= e(date_longue()) ?></span>
    </div>
    <div style="display:flex;align-items:center;gap:8px;color:#9FD9BF;font-size:14px">
      <span style="width:8px;height:8px;border-radius:50%;background:#4FC08D"></span><?= $poste ? 'Poste enregistré' : 'Connexion' ?><?= config('https') ? ' · connexion chiffrée' : '' ?>
    </div>
  </section>
  <section class="auth-r">
    <div class="auth-box">
      <?php foreach (flashes() as [$t, $m]): ?><div class="banner <?= e($t) ?>" role="status"><?= e($m) ?></div><?php endforeach; ?>
      <?= $content ?>
    </div>
  </section>
</div>
<script src="assets/app.js?v=1"></script>
</body>
</html>
