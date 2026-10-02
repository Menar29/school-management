<?= page_header($c['nom'], e($c['type']) . ' · plafond ' . fmt($c['plafond']) . ' FCFA · doit <strong>' . fmt($du) . ' FCFA</strong>', '<button class="btn" onclick="window.print()">' . icon('print') . 'Imprimer le relevé</button>', '<a href="' . e(url('creances')) . '">Créances</a> / Historique') ?>
<main class="page">
  <section class="card" style="overflow:hidden">
    <table class="table">
      <thead><tr><th>Date</th><th>Opération</th><th class="ra">Achat à crédit</th><th class="ra">Remboursement</th><th class="ra">Solde</th></tr></thead>
      <tbody>
      <?php $solde = 0; foreach ($lignes as $l): $solde += (int) $l['debit'] - (int) $l['credit']; ?>
        <tr><td><?= e(date('d/m/Y H:i', strtotime($l['at']))) ?></td>
          <td><?= $l['vente_id'] ? '<a href="' . e(url('caisse/ticket', ['id' => $l['vente_id']])) . '" target="_blank">Vente ' . e($l['ref']) . '</a>' : 'Remboursement · ' . e($l['ref']) ?></td>
          <td class="num ra"><?= $l['debit'] ? fmt($l['debit']) : '' ?></td><td class="num ra"><?= $l['credit'] ? fmt($l['credit']) : '' ?></td><td class="num ra" style="font-weight:600"><?= fmt($solde) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$lignes): ?><tr><td colspan="5" class="empty">Aucune opération.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</main>
