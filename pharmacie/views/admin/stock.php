<?php
$M = ['ok' => ['Suffisant', 'ok'], 'order' => ['Sous le seuil', 'info'], 'soon' => ['Périme bientôt', 'warn'], 'exp' => ['Périmé · à retirer', 'bad'], 'out' => ['Rupture', 'mute']];
$voirPrix = Auth::can('prix_achat');
$colSites = $monSite ? [$monSite => site($monSite)] : sites();
$acts = '';
if (Auth::can('stock.gerer')) $acts .= '<a class="btn" href="' . e(url('stock/inventaire')) . '">' . icon('inventaire') . 'Lancer un inventaire</a>';
if (Auth::can('produits.creer')) $acts .= '<a class="btn pri" href="' . e(url('produit/nouveau')) . '">' . icon('plus') . 'Nouveau produit</a>';
?>
<?= page_header('Stock', e(implode(', ', array_map(fn($s) => $s['nom_court'], $colSites))) . ' · quantités par lot, sorties au premier périmé', $acts) ?>
<main class="page">
  <?= flash_html() ?>
  <div class="grid g4">
    <?php if ($voirPrix): ?><div class="card kpi"><span class="l">Valeur du stock · prix d'achat</span><span class="v"><?= fmt($kpi['valeur']) ?><small>FCFA</small></span><span class="small muted">lots non périmés</span></div><?php endif; ?>
    <div class="card kpi"><span class="l">Références suivies</span><span class="v"><?= $kpi['refs'] ?></span><span class="small muted">dont <?= $kpi['avec'] ?> avec du stock</span></div>
    <div class="card kpi"><span class="l">Périment sous 6 mois</span><span class="v" style="color:var(--warn-ink)"><?= $kpi['soon'] ?></span><span class="small muted">produits à vendre en priorité</span></div>
    <div class="card kpi"><span class="l">Périmés encore en stock</span><span class="v" style="color:var(--bad-ink)"><?= $kpi['exp'] ?></span><span class="small muted">vente bloquée · à retirer</span></div>
    <?php if (!$voirPrix): ?><div class="card kpi"><span class="l">Ruptures</span><span class="v"><?= $count['out'] ?></span><span class="small muted">produits sans stock</span></div><?php endif; ?>
  </div>
  <section class="card" style="overflow:hidden">
    <div class="card-b between" style="border-bottom:1px solid var(--line)">
      <form method="get" class="flex"><input type="hidden" name="r" value="stock"><input type="hidden" name="f" value="<?= e($f) ?>"><label class="sr" for="sq">Rechercher dans le stock</label><input id="sq" name="q" value="<?= e($q) ?>" class="inp" style="width:320px" placeholder="Produit, molécule ou n° de lot"></form>
      <div class="chips">
        <?php foreach (['all' => 'Tous', 'order' => 'Sous le seuil', 'soon' => 'Périment bientôt', 'exp' => 'Périmés', 'out' => 'Ruptures'] as $k => $l): ?>
          <a class="chip <?= $f === $k ? 'on' : '' ?>" href="<?= e(url('stock', ['f' => $k, 'q' => $q])) ?>" style="text-decoration:none"><?= e($l) ?> <span class="n"><?= $count[$k] ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($f === 'exp' && $count['exp'] && Auth::can('stock.gerer')): ?>
      <form method="post" action="<?= e(url('stock/retirer')) ?>" class="banner bad" style="border-radius:0;border-width:0 0 1px" data-confirm="Retirer du stock tous les lots périmés<?= $monSite ? ' de votre site' : '' ?> ? Ils seront enregistrés comme retirés pour destruction.">
        <?= csrf_field() ?><span style="flex:1">Les lots périmés ne peuvent pas être vendus. Une fois retirés du rayon, enregistrez leur retrait.</span><button class="btn danger">Retirer les lots périmés</button></form>
    <?php endif; ?>
    <div class="scroll-x"><table class="table">
      <thead><tr><th>Produit</th><?php foreach ($colSites as $s): ?><th class="ra"><?= e($s['nom_court']) ?></th><?php endforeach; ?><th class="ra">Seuil</th><th>1er périmé</th><?php if ($voirPrix): ?><th class="ra">Valeur achat</th><?php endif; ?><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $p = $r['p']; ?>
        <tr class="hov">
          <td><a href="<?= e(url('produit', ['id' => $p['id']])) ?>" style="font-weight:600;color:var(--ink)"><?= e($p['designation']) ?></a><?php if ($r['perime']): ?><br><span class="small" style="color:var(--bad-ink)"><?= e(Stock::qte($p, $r['perime'])) ?> périmé(s)</span><?php endif; ?></td>
          <?php foreach ($colSites as $sid => $s): $qq = $r['sites'][$sid] ?? 0; ?><td class="num ra" title="<?= e(Stock::qte($p, $qq)) ?>"><?= e(Stock::qte($p, $qq, true)) ?></td><?php endforeach; ?>
          <td class="num ra muted"><?= (int) $r['seuil'] ?></td>
          <td class="num" style="<?= $r['st'] === 'soon' ? 'color:var(--warn-ink);font-weight:600' : '' ?>"><?= e(mois_annee($r['premier'])) ?></td>
          <?php if ($voirPrix): ?><td class="num ra"><?= fmt($r['valeur']) ?></td><?php endif; ?>
          <td><?= pill($M[$r['st']][0], $M[$r['st']][1]) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="9" class="empty">Aucun produit dans cette catégorie.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
</main>
