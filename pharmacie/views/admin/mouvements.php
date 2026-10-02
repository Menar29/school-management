<?= page_header('Historique des mouvements', e($p['designation']), '', '<a href="' . e(url('produit', ['id' => $p['id']])) . '">Fiche produit</a> / Mouvements') ?>
<main class="page">
  <section class="card" style="overflow:hidden"><div class="scroll-x"><table class="table">
    <thead><tr><th>Date</th><th>Type</th><th>Site</th><th>Lot</th><th class="ra">Quantité</th><th>Référence</th><th>Par</th><th>Motif</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): ?>
      <tr><td><?= e(date('d/m/Y H:i', strtotime($m['at']))) ?></td><td><?= e(TYPES_MVT[$m['type']] ?? $m['type']) ?></td><td><?= site_tag(site((int) $m['site_id'])) ?></td><td><?= e($m['lot'] ?? '—') ?></td>
        <td class="num ra" style="font-weight:600;color:<?= $m['quantite'] < 0 ? 'var(--bad-ink)' : 'var(--ok-ink)' ?>"><?= $m['quantite'] > 0 ? '+ ' : '' ?><?= e(Stock::qte($p, (int) $m['quantite'])) ?></td>
        <td><?= e($m['ref'] ?? '') ?></td><td><?= e($m['nom'] ? prenom($m['nom']) : '—') ?></td><td class="small muted"><?= e($m['motif'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="empty">Aucun mouvement.</td></tr><?php endif; ?>
    </tbody>
  </table></div></section>
</main>
