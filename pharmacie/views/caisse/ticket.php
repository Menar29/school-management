<div class="ticket">
  <div style="text-align:center">
    <strong style="font-size:15px"><?= e(config('pharmacie')) ?></strong><br>
    <?= e($site['nom']) ?><br>
    <?= e(date('d/m/Y H:i', strtotime($v['at']))) ?> · <?= e($v['numero']) ?><br>
    Vendeur : <?= e(prenom($v['vendeur'])) ?>
  </div>
  <hr>
  <table>
    <?php foreach ($lignes as $l): ?>
      <tr><td colspan="2"><?= e($l['designation']) ?></td></tr>
      <tr><td><?= (int) $l['quantite'] ?> × <?= fmt($l['prix_unitaire']) ?> <?= $l['unite'] === 'detail' ? '(' . e(mb_strtolower($l['detail_nom'])) . ')' : '' ?></td><td style="text-align:right"><?= fmt($l['total']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <hr>
  <table>
    <?php if ($v['remise']): ?>
      <tr><td>Sous-total</td><td style="text-align:right"><?= fmt($v['total_brut']) ?></td></tr>
      <tr><td>Remise</td><td style="text-align:right">− <?= fmt($v['remise']) ?></td></tr>
    <?php endif; ?>
    <tr><td><strong>TOTAL FCFA</strong></td><td style="text-align:right"><strong><?= fmt($v['total']) ?></strong></td></tr>
    <?php if ($v['mode'] === 'credit'): ?>
      <tr><td colspan="2">À crédit · <?= e($v['client']) ?></td></tr>
    <?php else: ?>
      <tr><td>Espèces reçues</td><td style="text-align:right"><?= fmt($v['recu']) ?></td></tr>
      <tr><td>Rendu</td><td style="text-align:right"><?= fmt($v['rendu']) ?></td></tr>
    <?php endif; ?>
  </table>
  <?php if ($v['statut'] === 'annulee'): ?><p style="text-align:center"><strong>*** VENTE ANNULÉE ***</strong></p><?php endif; ?>
  <hr>
  <p style="text-align:center">Merci de votre visite.<br>Les médicaments ne sont ni repris ni échangés.</p>
  <p class="no-print" style="text-align:center"><button onclick="window.print()" class="btn">Imprimer</button></p>
</div>
