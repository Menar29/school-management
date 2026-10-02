<?php
$u = Auth::user();
$r = (string) ($_GET['r'] ?? '');
$active = $active ?? explode('/', $r)[0];
$nb = nav_compteurs($u);
$mk = fn($id, $label, $route, $n = null, $alert = false) => compact('id', 'label', 'route', 'n', 'alert');
$site = site($u['site_id'] ? (int) $u['site_id'] : null);
if ($u['role'] === 'proprietaire') {
    $groups = [
        'Pilotage' => [$mk('dashboard', 'Tableau de bord', 'dashboard')],
        'Argent' => [$mk('caisses', 'Caisses et versements', 'caisses', $nb['caisses'], true), $mk('creances', 'Créances clients', 'creances', $nb['creances'] ?: null)],
        'Approvisionnement' => [$mk('reappro', 'Réapprovisionnement', 'reappro'), $mk('commandes', 'Commandes', 'commandes', $nb['commandes']), $mk('receptions', 'Réceptions', 'receptions'), $mk('fournisseurs', 'Fournisseurs', 'fournisseurs')],
        'Stock' => [$mk('stock', 'Stock', 'stock', $nb['perimes'], true), $mk('produits', 'Produits', 'produits', $nb['produits']), $mk('transferts', 'Transferts', 'transferts', $nb['transferts'])],
        'Administration' => [$mk('utilisateurs', 'Utilisateurs', 'utilisateurs'), $mk('droits', 'Rôles et droits', 'droits'), $mk('securite', 'Sécurité', 'securite', $nb['bloques'], true), $mk('journal', 'Journal d\'activité', 'journal')],
    ];
    $space = 'Espace propriétaire';
} elseif ($u['role'] === 'gerant') {
    $groups = [
        'Pilotage' => [$mk('dashboard', 'Mon officine', 'dashboard')],
        'Argent' => [$mk('caisses', 'Caisses de l\'officine', 'caisses', $nb['caisses'], true), $mk('creances', 'Créances clients', 'creances')],
        'Stock' => [$mk('stock', 'Stock', 'stock', $nb['perimes'], true), $mk('produits', 'Produits', 'produits'), $mk('receptions', 'Réceptions', 'receptions'), $mk('transferts', 'Transferts', 'transferts', $nb['transferts'])],
        'Approvisionnement' => [$mk('reappro', 'Besoins à commander', 'reappro'), $mk('commandes', 'Commandes', 'commandes')],
        'Suivi' => [$mk('journal', 'Journal d\'activité', 'journal')],
    ];
    $space = 'Espace gérant · ' . ($site['nom_court'] ?? '');
} else {
    $groups = [
        'Stock' => [$mk('stock', 'Stock', 'stock', $nb['perimes'], true), $mk('produits', 'Produits', 'produits'), $mk('receptions', 'Réceptions', 'receptions'), $mk('transferts', 'Transferts', 'transferts', $nb['transferts'])],
    ];
    $space = 'Magasinier · ' . ($site['nom_court'] ?? '');
}
$tabs = $u['role'] === 'magasinier'
    ? [['stock', 'Stock', 'stock'], ['receptions', 'Réception', 'receptions'], ['transferts', 'Transferts', 'transferts'], ['produits', 'Produits', 'produits']]
    : [['dashboard', 'Accueil', 'dashboard'], ['caisses', 'Caisses', 'caisses'], ['stock', 'Stock', 'stock'], ['receptions', 'Réception', 'receptions']];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'Gestion') ?> · <?= e(config('pharmacie')) ?></title>
<meta name="theme-color" content="#0F1D18">
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body>
<div class="app">
  <nav class="side" id="side" aria-label="Navigation principale">
    <div class="side-h">
      <span class="logo"><?= icon('cross') ?></span>
      <span style="display:flex;flex-direction:column;min-width:0">
        <span style="color:#fff;font-weight:600;font-size:15px;line-height:20px" class="ell"><?= e(config('pharmacie')) ?></span>
        <span style="font-size:12px;color:var(--nav-muted)"><?= e($space) ?></span>
      </span>
    </div>
    <div class="side-nav">
      <?php foreach ($groups as $g => $items): ?>
        <div style="display:flex;flex-direction:column;gap:1px">
          <div class="side-g"><?= e($g) ?></div>
          <?php foreach ($items as $it): ?>
            <a class="nv <?= $active === $it['id'] ? 'on' : '' ?>" href="<?= e(url($it['route'])) ?>" <?= $active === $it['id'] ? 'aria-current="page"' : '' ?>>
              <?= icon($it['id']) ?><span><?= e($it['label']) ?></span>
              <?php if ($it['n']): ?><span class="cnt <?= $it['alert'] ? 'alert' : '' ?>"><?= (int) $it['n'] ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="side-f">
      <?php if (Perm::can($u, 'vendre')): ?>
        <a class="nv" href="<?= e(url('caisse')) ?>"><?= icon('caisse') ?><span>Passer en mode caisse</span></a>
      <?php endif; ?>
      <div style="display:flex;align-items:center;gap:10px;padding:6px 12px 0">
        <span class="av" style="background:#244A3E;color:#E6EFEA"><?= e(initiales($u['nom'])) ?></span>
        <span style="display:flex;flex-direction:column;min-width:0;flex:1">
          <span style="color:#fff;font-weight:600;font-size:14px" class="ell"><?= e(prenom($u['nom'])) ?></span>
          <span style="font-size:12px;color:var(--nav-muted)"><?= e(Perm::role($u['role'])) ?></span>
          <form method="post" action="<?= e(url('logout')) ?>" class="inline"><?= csrf_field() ?><button type="submit" style="background:none;border:none;padding:0;font-size:12.5px;color:#9FD9BF;cursor:pointer;text-align:left">Se déconnecter</button></form>
        </span>
      </div>
    </div>
  </nav>
  <div class="main">
    <?= $content ?>
  </div>
</div>
<nav class="mtabs no-print" aria-label="Onglets">
  <?php foreach ($tabs as [$id, $label, $route]): ?>
    <a href="<?= e(url($route)) ?>" class="<?= $active === $id ? 'on' : '' ?>"><?= icon($id) ?><?= e($label) ?></a>
  <?php endforeach; ?>
  <a href="#" data-open-menu><?= icon('menu') ?>Menu</a>
</nav>
<script src="assets/app.js?v=1"></script>
</body>
</html>
