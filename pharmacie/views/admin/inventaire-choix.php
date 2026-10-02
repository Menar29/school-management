<?= page_header('Inventaire', 'Choisissez le site à compter', '', '<a href="' . e(url('stock')) . '">Stock</a> / Inventaire') ?>
<main class="page"><div class="grid g3">
  <?php foreach (sites() as $s): ?><a class="card card-b" href="<?= e(url('stock/inventaire', ['site' => $s['id']])) ?>" style="text-decoration:none;color:var(--ink)"><span class="site <?= e($s['couleur']) ?> h2"><?= e($s['nom']) ?></span><p class="small muted mt">Compter les lots de ce site</p></a><?php endforeach; ?>
</div></main>
