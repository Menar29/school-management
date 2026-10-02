<?php $voirPrix = $u['role'] !== 'magasinier'; ?>
<?= page_header('Réceptions', 'Livraisons des fournisseurs, saisies avec leur lot et leur péremption',
  '<form method="get" class="flex"><input type="hidden" name="r" value="reception"><label class="sr" for="rf">Fournisseur</label><select id="rf" name="fournisseur" class="inp" style="width:auto" required><option value="">Fournisseur…</option>'
  . implode('', array_map(fn($f) => '<option value="' . (int) $f['id'] . '">' . e($f['code']) . '</option>', $fournisseurs)) . '</select><button class="btn">Réception sans commande</button></form>') ?>
<main class="page">
  <?= flash_html() ?>
  <section class="card" style="overflow:hidden">
    <div class="card-h"><span class="h2">Livraisons attendues</span><span class="small muted">Commandes envoyées aux fournisseurs</span></div>
    <div class="scroll-x"><table class="table">
      <?php foreach ($attendues as $c): ?>
        <tr class="hov"><td><a href="<?= e(url('commande', ['id' => $c['id']])) ?>" style="font-weight:600"><?= e($c['numero']) ?></a></td><td><?= e($c['f_nom']) ?></td><td><?= site_tag(site((int) $c['site_id'])) ?></td>
          <td><?= pill(STATUTS_CMD_R[$c['statut']] ?? $c['statut'], $c['statut'] === 'partielle' ? 'warn' : 'info') ?></td><td class="small muted"><?= (int) $c['restants'] ?> produit(s) à recevoir</td>
          <td class="ra"><a class="btn pri" href="<?= e(url('reception', ['commande' => $c['id']])) ?>">Réceptionner</a></td></tr>
      <?php endforeach; ?>
      <?php if (!$attendues): ?><tr><td class="empty">Aucune livraison attendue.</td></tr><?php endif; ?>
    </table></div>
  </section>
  <section class="card" style="overflow:hidden">
    <div class="card-h"><span class="h2">Réceptions récentes</span></div>
    <div class="scroll-x"><table class="table">
      <thead><tr><th>N°</th><th>Date</th><th>Fournisseur</th><th>Commande</th><th>Stock</th><th>Par</th><th class="ra">Produits</th><?php if ($voirPrix): ?><th class="ra">Valeur</th><?php endif; ?><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($receptions as $r): ?>
        <tr class="hov" data-href="<?= e(url('reception', ['id' => $r['id']])) ?>" style="cursor:pointer"><td><a href="<?= e(url('reception', ['id' => $r['id']])) ?>"><?= e($r['numero']) ?></a></td><td><?= e(quand($r['created_at'])) ?></td><td><?= e($r['f_nom']) ?></td><td><?= e($r['cmd'] ?? '—') ?></td>
          <td><?= site_tag(site((int) $r['site_id'])) ?></td><td><?= e(prenom($r['nom'])) ?></td><td class="num ra"><?= (int) $r['nb'] ?></td><?php if ($voirPrix): ?><td class="num ra"><?= fmt($r['valeur']) ?></td><?php endif; ?>
          <td><?= $r['statut'] === 'validee' ? pill('Validée', 'ok') : pill('Brouillon', 'warn') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$receptions): ?><tr><td colspan="9" class="empty">Aucune réception.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
</main>
