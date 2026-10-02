<?= page_header($u['role'] === 'proprietaire' ? 'Tableau de bord' : 'Mon officine', e(date_longue()) . ' · mis à jour à ' . date('H:i'), site_seg('dashboard', $site, [], false, 'Les deux officines')) ?>
<main class="page">
  <?= flash_html() ?>
  <div class="grid g4">
    <div class="card kpi"><span class="l">Recette du jour</span><span class="v"><?= fmt($ca) ?><small>FCFA</small></span>
      <span class="small"><?php if ($delta !== null): ?><span class="<?= $delta >= 0 ? 'up' : 'down' ?>" style="font-weight:600"><?= $delta >= 0 ? '+' : '−' ?> <?= abs($delta) ?> %</span> <span class="muted">vs <?= JOURS[(int) date('w')] ?> dernier à la même heure</span><?php else: ?><span class="muted">Pas de comparaison disponible</span><?php endif; ?></span></div>
    <div class="card kpi"><span class="l">Espèces à recevoir des caisses</span><span class="v"><?= fmt($cash) ?><small>FCFA</small></span><span class="small muted"><?= count($sessions) ?> caisse<?= count($sessions) > 1 ? 's' : '' ?> non validée<?= count($sessions) > 1 ? 's' : '' ?> · <?= $ouvertes ?> encore ouverte<?= $ouvertes > 1 ? 's' : '' ?></span></div>
    <div class="card kpi"><span class="l">Écarts de caisse du jour</span><span class="v" style="color:<?= (int) $ecarts['total'] < 0 ? 'var(--bad-ink)' : 'var(--ink)' ?>"><?= (int) $ecarts['total'] === 0 ? '0' : e(fmt_signe((int) $ecarts['total'])) ?><small>FCFA</small></span><span class="small muted"><?= (int) $ecarts['a_voir'] ? (int) $ecarts['a_voir'] . ' caisse(s) à examiner' : 'Aucun écart à examiner' ?></span></div>
    <div class="card kpi"><span class="l"><?= e($kpi4[0]) ?></span><span class="v"><?= fmt($kpi4[1]) ?><small>FCFA</small></span><span class="small muted"><?= e($kpi4[2]) ?></span></div>
  </div>

  <div class="row">
    <section class="card grow" aria-label="Recette des 14 derniers jours">
      <div class="card-h"><span class="h2">Recette des 14 derniers jours</span>
        <span class="flex"><?php foreach (officines() as $o): if ($site && $site !== (int) $o['id']) continue; ?><span class="site <?= e($o['couleur']) ?>"><?= e($o['nom_court']) ?></span><?php endforeach; ?></span></div>
      <div class="card-b" style="display:flex;gap:14px">
        <div class="num small muted hide-m" style="display:flex;flex-direction:column;justify-content:space-between;height:220px;text-align:right;padding-bottom:2px">
          <?php for ($i = 4; $i >= 0; $i--): ?><span><?= fmt($max * $i / 4 / 1000) ?> k</span><?php endfor; ?>
        </div>
        <div class="chart" style="flex:1">
          <?php foreach ($jours as $d => $parSite): ?>
            <div class="col" title="<?= e(date('d/m', strtotime($d))) ?><?php foreach ($parSite as $sid => $t) echo e(' · ' . site($sid)['nom_court'] . ' ' . fmt($t)); ?>">
              <?php foreach (array_reverse($parSite, true) as $sid => $t): ?><div class="seg-<?= e(site($sid)['couleur']) ?>" style="height:<?= round($t / $max * 100, 1) ?>%"></div><?php endforeach; ?>
              <span class="lab num" style="<?= $d === today() ? 'color:var(--ink);font-weight:600' : '' ?>"><?= e(date('d', strtotime($d))) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <section class="card aside" aria-label="À traiter">
      <div class="card-h"><span class="h2">À traiter</span><span class="pill bad plain"><?= count($todo) ?></span></div>
      <ul>
        <?php foreach ($todo as $t): ?>
          <li><a href="<?= e(url($t[3], $t[4] ?? [])) ?>" style="display:flex;align-items:center;gap:12px;min-height:60px;padding:0 20px;border-bottom:1px solid var(--line);text-decoration:none;color:var(--ink)">
            <span class="pill <?= e($t[0]) ?> plain" style="min-width:36px;justify-content:center"><?= (int) $t[1] ?></span><span style="flex:1"><?= e($t[2]) ?></span><?= icon('chev') ?></a></li>
        <?php endforeach; ?>
        <?php if (!$todo): ?><li class="empty">Rien à traiter pour le moment.</li><?php endif; ?>
      </ul>
    </section>
  </div>

  <div class="row">
    <section class="card grow" aria-label="Caisses du jour">
      <div class="card-h"><span class="h2">Caisses du jour</span><a href="<?= e(url('caisses')) ?>" class="small" style="font-weight:600">Tout voir</a></div>
      <div class="scroll-x"><table class="table">
        <?php foreach ($tills as $t): $s = site((int) $t['site_id']); ?>
          <tr class="hov" data-href="<?= e(url('caisses', ['sel' => $t['id']])) ?>" style="cursor:pointer">
            <td><span class="flex"><span class="av"><?= e(initiales($t['nom'])) ?></span><span style="font-weight:600"><?= e(prenom($t['nom'])) ?></span></span></td>
            <td><?= site_tag($s) ?></td>
            <td class="num ra"><?= fmt($t['montant']) ?></td>
            <td><?= pill($t['st'][0], $t['st'][1]) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tills): ?><tr><td class="empty">Aucune caisse ouverte aujourd'hui.</td></tr><?php endif; ?>
      </table></div>
    </section>
    <section class="card aside" aria-label="Meilleures ventes">
      <div class="card-h"><span class="h2">Plus vendus · 30 jours</span><span class="small muted">en unités vendues</span></div>
      <div class="card-b stack" style="gap:12px">
        <?php $tmax = max(1, (int) ($top[0]['q'] ?? 1)); foreach ($top as $p): ?>
          <div class="stack-s" style="gap:6px"><div class="between"><span style="font-weight:500"><?= e($p['alias'] ?: $p['designation']) ?></span><span class="num muted"><?= (int) $p['q'] ?></span></div>
            <div class="bar"><span style="width:<?= round($p['q'] / $tmax * 100) ?>%"></span></div></div>
        <?php endforeach; ?>
        <?php if (!$top): ?><p class="muted">Pas encore de ventes.</p><?php endif; ?>
      </div>
    </section>
  </div>
</main>
