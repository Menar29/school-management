<?php
$actions = site_seg('caisses', $site, ['jour' => $jour])
    . '<form method="get" class="flex"><input type="hidden" name="r" value="caisses">' . ($site ? '<input type="hidden" name="site" value="' . (int) $site . '">' : '')
    . '<label class="sr" for="day">Jour</label><select id="day" name="jour" class="inp" style="width:auto" data-autosubmit>';
foreach ($jours as $j) $actions .= '<option value="' . e($j['d']) . '"' . ($j['d'] === $jour ? ' selected' : '') . '>' . e($j['d'] === today() ? 'Aujourd\'hui · ' . date_fr($j['d']) : date_fr($j['d'])) . '</option>';
if (!$jours || $jours[0]['d'] !== today()) $actions .= '<option value="' . today() . '"' . ($jour === today() ? ' selected' : '') . '>Aujourd\'hui</option>';
$actions .= '</select></form>';
if ($u['role'] === 'proprietaire') $actions .= '<button class="btn" data-dialog="dlg-dep">Enregistrer une dépense</button>';
?>
<?= page_header($u['role'] === 'proprietaire' ? 'Caisses et versements' : 'Caisses de l\'officine', 'Chaque vendeur clôture sa caisse, ' . ($u['role'] === 'proprietaire' ? 'vous validez l\'argent reçu.' : 'le propriétaire valide l\'argent reçu.'), $actions) ?>
<main class="page">
  <?= flash_html() ?>
  <div class="grid g4">
    <div class="card kpi"><span class="l">Espèces attendues</span><span class="v"><?= fmt($attendu) ?><small>FCFA</small></span><span class="small muted"><?= count($rows) ?> session<?= count($rows) > 1 ? 's' : '' ?> de caisse</span></div>
    <div class="card kpi"><span class="l">Déjà remises et validées</span><span class="v"><?= fmt($valide) ?><small>FCFA</small></span><span class="small muted"><?= $nValide ?> versement<?= $nValide > 1 ? 's' : '' ?> validé<?= $nValide > 1 ? 's' : '' ?></span></div>
    <div class="card kpi"><span class="l">Écart</span><span class="v" style="color:<?= $ecart < 0 ? 'var(--bad-ink)' : 'var(--ink)' ?>"><?= $ecart ? e(fmt_signe($ecart)) : '0' ?><small>FCFA</small></span><span class="small muted"><?= $aVoir ? $aVoir . ' caisse(s) à examiner' : 'Rien à examiner' ?></span></div>
    <?php if ($especes !== null): ?>
      <div class="card kpi"><span class="l">Espèces en votre possession</span><span class="v"><?= fmt($especes) ?><small>FCFA</small></span><span class="small muted">après paiements et dépenses</span></div>
    <?php else: ?>
      <div class="card kpi"><span class="l">Ventes à crédit</span><span class="v"><?= fmt(array_sum(array_map(fn($r) => $r['res']['credit'], $rows))) ?><small>FCFA</small></span><span class="small muted">hors caisse</span></div>
    <?php endif; ?>
  </div>

  <div class="row">
    <section class="card grow" style="overflow:hidden">
      <div class="card-h"><span class="h2">Sessions de caisse</span><span class="small muted">Sélectionnez une ligne pour le détail</span></div>
      <div class="scroll-x"><table class="table">
        <thead><tr><th>Vendeur</th><th>Officine</th><th class="ra">Attendu</th><th class="ra">Compté</th><th class="ra">Écart</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $on = $sel && (int) $sel['id'] === (int) $r['id']; ?>
          <tr class="hov <?= $on ? 'sel' : '' ?>" data-href="<?= e(url('caisses', ['sel' => $r['id'], 'jour' => $jour, 'site' => $site ?? 'all'])) ?>" style="cursor:pointer">
            <td><span class="flex"><span class="av"><?= e(initiales($r['nom'])) ?></span><span style="display:flex;flex-direction:column"><a href="<?= e(url('caisses', ['sel' => $r['id'], 'jour' => $jour, 'site' => $site ?? 'all'])) ?>" style="font-weight:600;color:var(--ink);text-decoration:none"><?= e(prenom($r['nom'])) ?></a><span class="small muted"><?= e(date('d/m H:i', strtotime($r['ouverte_at']))) ?> → <?= $r['cloturee_at'] ? e(date('H:i', strtotime($r['cloturee_at']))) : 'en cours' ?></span></span></span></td>
            <td><?= site_tag(site((int) $r['site_id'])) ?></td>
            <td class="num ra"><?= fmt($r['res']['attendu']) ?></td>
            <td class="num ra"><?= $r['statut'] === 'ouverte' ? '—' : fmt($r['compte']) ?></td>
            <td class="num ra" style="font-weight:600;color:<?= (int) $r['ecart'] < 0 ? 'var(--bad-ink)' : ((int) $r['ecart'] > 0 ? 'var(--warn-ink)' : 'var(--ok-ink)') ?>"><?= $r['statut'] === 'ouverte' ? '—' : e(fmt_signe((int) $r['ecart'])) ?></td>
            <td><?= pill($r['st'][0], $r['st'][1]) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty">Aucune session ce jour-là.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </section>

    <aside class="aside">
      <?php if ($sel): $res = $sel['res']; ?>
        <section class="card">
          <div class="card-h"><span style="display:flex;flex-direction:column"><span class="h2"><?= e(prenom($sel['nom'])) ?></span><span class="small muted"><?= e(site((int) $sel['site_id'])['nom_court']) ?> · <?= e(date('d/m H:i', strtotime($sel['ouverte_at']))) ?> → <?= $sel['cloturee_at'] ? e(date('H:i', strtotime($sel['cloturee_at']))) : 'en cours' ?></span></span><?= pill($sel['st'][0], $sel['st'][1]) ?></div>
          <div class="card-b stack">
            <div class="dl">
              <?php if ($res['fond']): ?><div><span class="k">Fond de caisse</span><span class="num"><?= fmt($res['fond']) ?></span></div><?php endif; ?>
              <div><span class="k">Ventes en espèces (<?= $res['nb'] ?>)</span><span class="num">+ <?= fmt($res['especes']) ?></span></div>
              <div><span class="k">Dépenses de caisse</span><span class="num"><?= $res['depenses'] ? '− ' . fmt($res['depenses']) : '0' ?></span></div>
              <?php foreach ($res['liste_depenses'] as $d): ?><div class="small"><span class="k">· <?= e($d['libelle']) ?></span><span class="num"><?= fmt($d['montant']) ?></span></div><?php endforeach; ?>
              <div><span class="k">Ventes à crédit (hors caisse)</span><span class="num"><?= fmt($res['credit']) ?></span></div>
              <div><span class="k">Remises · annulations</span><span class="num"><?= fmt($res['remises']) ?> · <?= $res['annulees'] ?></span></div>
              <div><span style="font-weight:600">Attendu / compté</span><span class="num tot"><?= fmt($res['attendu']) ?> / <?= $sel['statut'] === 'ouverte' ? '—' : fmt($sel['compte']) ?></span></div>
            </div>
            <?php if ($sel['explication']): ?><div class="banner warn"><span><strong>Explication du vendeur :</strong> <?= nl2br(e($sel['explication'])) ?></span></div><?php endif; ?>
            <?php if ($sel['remarque_admin']): ?><div class="banner info"><span><strong>Question posée :</strong> <?= e($sel['remarque_admin']) ?></span></div><?php endif; ?>
            <?php if ($sel['billetage']): $b = json_decode($sel['billetage'], true) ?: []; ?>
              <details><summary class="small" style="cursor:pointer">Détail du comptage</summary>
                <div class="dl small"><?php foreach ($b as $k => $n): if (!$n) continue; ?><div><span class="k"><?= $k === 'pieces' ? 'Pièces' : fmt((int) $k) . ' F × ' . (int) $n ?></span><span class="num"><?= fmt($k === 'pieces' ? $n : $k * $n) ?></span></div><?php endforeach; ?></div>
              </details>
            <?php endif; ?>
            <?php if ($sel['statut'] === 'cloturee'): ?>
              <div class="flex">
                <button class="btn" data-dialog="dlg-q" data-id="<?= (int) $sel['id'] ?>">Demander des précisions</button>
                <?php if ($u['role'] === 'proprietaire'): ?>
                  <form method="post" action="<?= e(url('caisses/valider')) ?>" class="inline" data-confirm="Valider le versement de <?= e(fmt($sel['compte'])) ?> FCFA reçu de <?= e(prenom($sel['nom'])) ?> ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><button class="btn pri">Valider le versement</button></form>
                <?php endif; ?>
              </div>
            <?php elseif ($sel['statut'] === 'precisions' && $u['role'] === 'proprietaire'): ?>
              <form method="post" action="<?= e(url('caisses/valider')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $sel['id'] ?>"><button class="btn">Valider sans attendre la réponse</button></form>
            <?php endif; ?>
          </div>
        </section>
        <section class="card">
          <div class="card-h"><span class="h2">Ventes de la session</span><span class="small muted"><?= count($ventesSel) ?></span></div>
          <div style="max-height:320px;overflow:auto"><table class="table small">
            <?php foreach ($ventesSel as $v): ?>
              <tr style="<?= $v['statut'] === 'annulee' ? 'color:var(--muted);text-decoration:line-through' : '' ?>"><td class="num"><?= e(date('H:i', strtotime($v['at']))) ?></td><td class="ell" style="max-width:180px"><a href="<?= e(url('caisse/ticket', ['id' => $v['id']])) ?>" target="_blank"><?= e($v['numero']) ?></a> · <?= e($v['articles']) ?></td><td class="num ra"><?= fmt($v['total']) ?><?= $v['mode'] === 'credit' ? ' · crédit' : '' ?></td></tr>
            <?php endforeach; ?>
          </table></div>
        </section>
      <?php endif; ?>
      <?php if ($mouvements): ?>
        <section class="card">
          <div class="card-h"><span class="h2">Mouvements d'espèces</span><span class="small muted">vos espèces</span></div>
          <ul>
            <?php foreach ($mouvements as $m): ?>
              <li class="between" style="padding:10px 20px;border-bottom:1px solid var(--line)"><span style="display:flex;flex-direction:column"><span><?= e($m['libelle']) ?></span><span class="small muted"><?= e(quand($m['at'])) ?></span></span>
                <span class="num" style="font-weight:600;color:<?= (int) $m['montant'] < 0 ? 'var(--bad-ink)' : 'var(--ok-ink)' ?>"><?= e(fmt_signe((int) $m['montant'])) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
    </aside>
  </div>
</main>

<dialog id="dlg-q">
  <form method="post" action="<?= e(url('caisses/precisions')) ?>"><?= csrf_field() ?><input type="hidden" name="id" data-fill="id">
    <div class="dh"><h2 class="h2">Demander des précisions</h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db"><label class="lbl" for="q-r">Votre question au vendeur</label><textarea id="q-r" name="remarque" class="inp" required placeholder="Ex. À quelle vente correspond la monnaie rendue en trop ?"></textarea></div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Envoyer</button></div>
  </form>
</dialog>
<dialog id="dlg-dep">
  <form method="post" action="<?= e(url('caisses/depense')) ?>"><?= csrf_field() ?>
    <div class="dh"><h2 class="h2">Enregistrer une dépense</h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <div><label class="lbl" for="d-l">Motif</label><input id="d-l" name="libelle" class="inp" required placeholder="Ex. électricité REMEDE"></div>
      <div><label class="lbl" for="d-m">Montant (FCFA)</label><input id="d-m" name="montant" class="inp num" inputmode="numeric" required></div>
      <div><label class="lbl" for="d-s">Site concerné <span class="opt">(facultatif)</span></label><select id="d-s" name="site" class="inp"><option value="">—</option><?php foreach (sites() as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['nom']) ?></option><?php endforeach; ?></select></div>
      <p class="hint">Payée avec vos espèces. Pour une dépense payée avec l'argent d'une caisse, le vendeur l'enregistre lui-même avant sa clôture.</p>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Enregistrer</button></div>
  </form>
</dialog>
