<div style="max-width:800px;margin:24px auto;padding:0 16px;font-size:14px">
  <div class="between" style="align-items:flex-start">
    <div><strong style="font-size:20px"><?= e(config('pharmacie')) ?></strong><br>Livraison : <?= e(site((int) $c['site_id'])['nom']) ?></div>
    <div style="text-align:right"><strong style="font-size:20px">BON DE COMMANDE</strong><br><?= e($c['numero']) ?><br><?= e(date_fr($c['created_at'])) ?></div>
  </div>
  <div class="card card-b" style="margin:20px 0"><strong><?= e($c['f_nom']) ?></strong> (<?= e($c['f_code']) ?>)<br><?= e($c['f_contact'] ?? '') ?> <?= e($c['f_tel'] ?? '') ?><br><?= e($c['f_adresse'] ?? '') ?></div>
  <table class="table" style="border:1px solid var(--line)">
    <thead><tr><th>Désignation</th><th class="ra">Quantité</th><?php if ($prix): ?><th class="ra">Prix unitaire</th><th class="ra">Montant</th><?php endif; ?></tr></thead>
    <tbody><?php $t = 0; foreach ($lignes as $l): $t += $l['quantite'] * $l['prix_achat']; ?>
      <tr><td><?= e($l['designation']) ?></td><td class="num ra"><?= (int) $l['quantite'] ?></td><?php if ($prix): ?><td class="num ra"><?= fmt($l['prix_achat']) ?></td><td class="num ra"><?= fmt($l['quantite'] * $l['prix_achat']) ?></td><?php endif; ?></tr>
    <?php endforeach; ?></tbody>
    <?php if ($prix): ?><tfoot><tr><td colspan="3"><strong>Total FCFA</strong></td><td class="num ra"><strong><?= fmt($t) ?></strong></td></tr></tfoot><?php endif; ?>
  </table>
  <p style="margin-top:40px" class="between"><span>Conditions : <?= e($c['conditions'] ?: '—') ?></span><span>Signature et cachet</span></p>
  <p class="no-print" style="margin-top:24px"><button class="btn" onclick="window.print()">Imprimer</button></p>
</div>
