<?php $voirPrix = $u['role'] !== 'magasinier'; $t = 0; ?>
<?= page_header('Réception ' . $r['numero'] . ' · ' . $f['nom'], pill('Validée', 'ok') . ' <span class="muted">· ' . e(site((int) $r['site_id'])['nom']) . ' · ' . e(quand($r['validee_at'])) . ($r['bl_numero'] ? ' · BL ' . e($r['bl_numero']) : '') . '</span>', '<button class="btn" onclick="window.print()">' . icon('print') . 'Imprimer</button>', '<a href="' . e(url('receptions')) . '">Réceptions</a>' . ($cmd ? ' / ' . e($cmd['numero']) : '')) ?>
<main class="page">
  <section class="card" style="overflow:hidden"><div class="scroll-x"><table class="table">
    <thead><tr><th>Produit</th><th class="ra">Attendu</th><th class="ra">Reçu</th><th>Lot</th><th>Péremption</th><?php if ($voirPrix): ?><th class="ra">Prix facturé</th><th class="ra">Montant</th><?php endif; ?></tr></thead>
    <tbody><?php foreach ($lignes as $l): $t += $l['quantite'] * $l['prix_facture']; ?>
      <tr><td><?= e($l['designation']) ?></td><td class="num ra"><?= $l['attendu'] ?: '—' ?></td><td class="num ra"><?= (int) $l['quantite'] ?></td><td><?= e($l['numero_lot'] ?? '—') ?></td><td><?= e(mois_annee($l['peremption'])) ?></td>
      <?php if ($voirPrix): ?><td class="num ra"><?= fmt($l['prix_facture']) ?></td><td class="num ra"><?= fmt($l['quantite'] * $l['prix_facture']) ?></td><?php endif; ?></tr>
    <?php endforeach; ?></tbody>
    <?php if ($voirPrix): ?><tfoot><tr><td colspan="6" style="font-weight:600">Total<?= $r['montant_facture'] !== null ? ' · facture ' . fmt($r['montant_facture']) : '' ?></td><td class="num ra tot"><?= fmt($t) ?></td></tr></tfoot><?php endif; ?>
  </table></div></section>
</main>
