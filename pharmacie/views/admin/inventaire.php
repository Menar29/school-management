<?= page_header('Inventaire · ' . $site['nom'], 'Comptez chaque lot en rayon. Laissez vide un lot que vous n\'avez pas compté : il ne sera pas modifié.', '', '<a href="' . e(url('stock')) . '">Stock</a> / Inventaire') ?>
<main class="page">
  <form method="post" action="<?= e(url('stock/inventaire', ['site' => $site['id']])) ?>" class="stack" data-confirm="Enregistrer l'inventaire ? Les quantités comptées remplacent celles du logiciel.">
    <?= csrf_field() ?>
    <section class="card" style="overflow:hidden"><div class="scroll-x"><table class="table">
      <thead><tr><th>Produit</th><th>Lot</th><th>Péremption</th><th class="ra">Dans le logiciel</th><th style="width:220px">Compté</th></tr></thead>
      <tbody>
      <?php foreach ($lots as $l): $f = max(1, (int) $l['fractions']); ?>
        <tr><td style="font-weight:600"><?= e($l['designation']) ?></td><td><?= e($l['numero']) ?></td><td><?= e(mois_annee($l['peremption'])) ?><?= $l['perime'] ? ' ' . pill('Périmé', 'bad') : '' ?></td>
          <td class="num ra"><?= e(Stock::qte($l, (int) $l['quantite'])) ?></td>
          <td><span class="flex" style="flex-wrap:nowrap"><input name="lot[<?= (int) $l['id'] ?>][b]" type="number" min="0" class="inp num" style="width:80px" aria-label="<?= e(ucfirst(Stock::uniteStock($l))) ?>s comptées"><span class="small muted"><?= e(Stock::uniteStock($l)) ?>s</span>
            <?php if ($f > 1): ?><input name="lot[<?= (int) $l['id'] ?>][d]" type="number" min="0" max="<?= $f - 1 ?>" class="inp num" style="width:64px" aria-label="<?= e($l['detail_nom']) ?>s en plus"><span class="small muted">+ <?= e(mb_strtolower($l['detail_nom'])) ?>s</span><?php endif; ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$lots): ?><tr><td colspan="5" class="empty">Aucun lot sur ce site.</td></tr><?php endif; ?>
      </tbody>
    </table></div></section>
    <div><button class="btn pri lg">Enregistrer l'inventaire</button></div>
  </form>
</main>
