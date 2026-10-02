<?= page_header('Produits', 'Catalogue commun à tous les sites · ' . $count['all'] . ' références', Auth::can('produits.creer') ? '<a class="btn pri" href="' . e(url('produit/nouveau')) . '">' . icon('plus') . 'Nouveau produit</a>' : '') ?>
<main class="page">
  <?= flash_html() ?>
  <?php if ($count['noprice'] && $u['role'] === 'proprietaire'): ?>
    <div class="banner warn"><span style="flex:1"><strong><?= $count['noprice'] ?> produit(s) sans prix de vente.</strong> Créé(s) par le magasinier pendant une réception, il(s) ne peu(ven)t pas être vendu(s) tant que vous n'avez pas fixé le prix.</span><a class="btn" href="<?= e(url('produits', ['f' => 'noprice'])) ?>">Voir</a></div>
  <?php endif; ?>
  <section class="card" style="overflow:hidden">
    <div class="card-b between" style="border-bottom:1px solid var(--line)">
      <form method="get" class="flex"><input type="hidden" name="r" value="produits"><input type="hidden" name="f" value="<?= e($f) ?>"><label class="sr" for="pq">Rechercher un produit</label><input id="pq" name="q" value="<?= e($q) ?>" class="inp" style="width:320px" placeholder="Nom, molécule ou code-barres"></form>
      <div class="chips"><?php foreach (['all' => 'Tous', 'check' => 'À vérifier', 'noprice' => 'Sans prix', 'ok' => 'En vente', 'stopped' => 'Arrêtés'] as $k => $l): ?>
        <a class="chip <?= $f === $k ? 'on' : '' ?>" href="<?= e(url('produits', ['f' => $k, 'q' => $q])) ?>" style="text-decoration:none"><?= e($l) ?> <span class="n"><?= $count[$k] ?></span></a><?php endforeach; ?></div>
    </div>
    <div class="scroll-x"><table class="table">
      <thead><tr><th>Produit</th><th>Forme</th><th>Unités de vente</th><th class="ra">Prix boîte</th><th>Créé par</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $p): ?>
        <tr class="hov" style="<?= $p['st'][0] === 'noprice' ? 'background:#FFFCF6' : '' ?>">
          <td><a href="<?= e(url('produit', ['id' => $p['id']])) ?>" style="font-weight:600;color:var(--ink)"><?= e($p['designation']) ?></a><br><span class="small muted"><?= e($p['dci']) ?><?= $p['alias'] ? ' · « ' . e($p['alias']) . ' »' : '' ?></span></td>
          <td><?= e($p['forme']) ?></td>
          <td><?= e(ucfirst(Stock::uniteStock($p))) ?><?= $p['detail_nom'] ? ' · ' . e(mb_strtolower($p['detail_nom'])) . ' (' . (int) $p['fractions'] . '/boîte)' : '' ?></td>
          <td class="num ra" style="<?= $p['prix_boite'] === null ? 'color:var(--warn-ink);font-weight:600' : '' ?>"><?= fmt($p['prix_boite']) ?></td>
          <td><span class="small"><?= $p['auteur'] ? e(prenom($p['auteur']) . ' · ' . Perm::role($p['auteur_role'])) : 'Reprise du stock' ?><br><span class="muted"><?= e(quand($p['created_at'])) ?></span></span></td>
          <td><?= pill($p['st'][1], $p['st'][2]) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty"><strong>Aucun produit ne correspond<?= $q !== '' ? ' à « ' . e($q) . ' »' : '' ?></strong><br><span class="small">Vérifiez l'orthographe ou cherchez par molécule. S'il n'existe vraiment pas, créez-le.</span>
        <?php if (Auth::can('produits.creer')): ?><br><a class="btn mt" href="<?= e(url('produit/nouveau', ['designation' => $q])) ?>">Créer « <?= e($q) ?> »</a><?php endif; ?></td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
</main>
