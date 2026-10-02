<?php
$cell = function (string $v): string {
    if ($v === 'n') return '<span class="flex" style="color:#7A847E">' . icon('x') . 'Non</span>';
    if (str_starts_with($v, 'l:')) return '<span class="flex" style="color:var(--warn-ink);font-weight:500;flex-wrap:nowrap">' . icon('alert') . e(substr($v, 2)) . '</span>';
    return '<span class="flex" style="color:var(--ok-ink);font-weight:500;flex-wrap:nowrap">' . icon('check') . e($v === 'y' ? 'Oui' : substr($v, 2)) . '</span>';
};
?>
<?= page_header('Rôles et droits', 'Chaque utilisateur est rattaché à un seul site. Ses droits s\'appliquent uniquement à ce site. Le gérant est le responsable d\'une officine.') ?>
<main class="page">
  <section class="card" style="overflow:hidden"><div class="scroll-x"><table class="table">
    <thead><tr><th>Action</th><th>Vendeur</th><th>Gérant</th><th>Magasinier</th><th>Propriétaire</th></tr></thead>
    <?php foreach (Perm::tableau() as $g => $rows): ?>
      <tbody><tr><td colspan="5" style="background:var(--sunk);font-weight:600"><?= e($g) ?></td></tr>
      <?php foreach ($rows as $r): ?><tr><td><?= e($r[0]) ?></td><?php for ($i = 1; $i <= 4; $i++): ?><td class="small"><?= $cell($r[$i]) ?></td><?php endfor; ?></tr><?php endforeach; ?></tbody>
    <?php endforeach; ?>
  </table></div></section>
  <div class="banner info"><span>Toute action sensible (annulation, remise au-delà du seuil, ajustement de stock, modification de prix) est enregistrée avec son auteur, l'heure et le poste dans le journal d'activité. Personne, pas même le propriétaire, ne peut effacer cet historique depuis l'application.</span></div>
</main>
