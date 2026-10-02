<?= page_header('Journal d\'activité', $total . ' événement(s) · non modifiable', site_seg('journal', $site, ['q' => $q])) ?>
<main class="page">
  <form method="get" class="flex"><input type="hidden" name="r" value="journal"><?php if ($site): ?><input type="hidden" name="site" value="<?= (int) $site ?>"><?php endif; ?>
    <label class="sr" for="jq">Rechercher</label><input id="jq" name="q" value="<?= e($q) ?>" class="inp" style="width:320px" placeholder="Action, détail ou personne">
    <label class="sr" for="jd">Depuis</label><input id="jd" name="du" type="date" value="<?= e(input('du')) ?>" class="inp" style="width:auto"><button class="btn">Filtrer</button></form>
  <section class="card" style="overflow:hidden"><div class="scroll-x"><table class="table">
    <thead><tr><th>Date</th><th>Personne</th><th>Site</th><th>Action</th><th>Détail</th><th>Poste</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): ?>
      <tr><td class="num" style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime($a['at']))) ?></td><td><?= e($a['nom'] ? prenom($a['nom']) : 'Système') ?></td><td><?= $a['site_id'] ? site_tag(site((int) $a['site_id'])) : '<span class="muted">—</span>' ?></td>
        <td><?= e(ucfirst(str_replace('_', ' ', $a['action']))) ?></td><td class="small"><?= e($a['details']) ?></td><td class="small muted"><?= e($a['poste'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="empty">Aucun événement.</td></tr><?php endif; ?>
    </tbody>
  </table></div></section>
  <?php if ($pages > 1): ?><div class="flex"><?php for ($i = 1; $i <= min($pages, 20); $i++): ?><a class="chip <?= $i === $page ? 'on' : '' ?>" href="<?= e(url('journal', ['p' => $i, 'q' => $q, 'site' => $site ?? 'all'])) ?>" style="text-decoration:none"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
</main>
