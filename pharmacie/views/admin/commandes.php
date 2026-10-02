<?php
$chips = '';
foreach (['encours' => 'En cours', 'livree' => 'Livrées', 'annulee' => 'Annulées', 'tout' => 'Toutes'] as $k => $l) $chips .= '<a class="chip ' . ($st === $k ? 'on' : '') . '" href="' . e(url('commandes', ['st' => $k])) . '" style="text-decoration:none">' . e($l) . '</a>';
$voirPrix = Auth::can('prix_achat');
?>
<?= page_header('Commandes', 'Bons de commande aux fournisseurs', '<a class="btn" href="' . e(url('reappro')) . '">' . icon('reappro') . 'Depuis les besoins</a><a class="btn pri" href="' . e(url('commande/nouvelle')) . '">' . icon('plus') . 'Nouvelle commande</a>') ?>
<main class="page">
  <?= flash_html() ?>
  <div class="chips"><?= $chips ?></div>
  <section class="card" style="overflow:hidden">
    <div class="scroll-x"><table class="table">
      <thead><tr><th>N°</th><th>Fournisseur</th><th>Livraison à</th><th>Créée</th><th class="ra">Produits</th><?php if ($voirPrix): ?><th class="ra">Montant</th><th>Paiement</th><?php endif; ?><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $s = STATUTS_CMD[$r['statut']]; $reste = $r['recu_val'] - $r['paye']; ?>
        <tr class="hov" data-href="<?= e(url('commande', ['id' => $r['id']])) ?>" style="cursor:pointer">
          <td><a href="<?= e(url('commande', ['id' => $r['id']])) ?>" style="font-weight:600"><?= e($r['numero']) ?></a></td>
          <td><?= e($r['f_nom']) ?></td>
          <td><?= site_tag(site((int) $r['site_id'])) ?></td>
          <td><?= e(quand($r['created_at'])) ?></td>
          <td class="num ra"><?= (int) $r['nb'] ?><?= $r['statut'] === 'partielle' ? ' · ' . (int) $r['manquants'] . ' manquant(s)' : '' ?></td>
          <?php if ($voirPrix): ?><td class="num ra"><?= fmt($r['montant']) ?></td>
            <td><?= $r['recu_val'] == 0 ? '<span class="muted">—</span>' : ($reste <= 0 ? pill('Payée', 'ok') : pill('Reste ' . fmt($reste), 'warn')) ?></td><?php endif; ?>
          <td><?= pill($s[0], $s[1]) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" class="empty">Aucune commande.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
</main>
