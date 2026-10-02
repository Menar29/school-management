<?php
$s = STATUTS_CMD[$c['statut']];
$voirPrix = Auth::can('prix_achat');
$total = array_sum(array_map(fn($l) => $l['quantite'] * $l['prix_achat'], $lignes));
$acts = '<a class="btn" href="' . e(url('commande/imprimer', ['id' => $c['id'], 'print' => 1])) . '" target="_blank">' . icon('print') . 'Imprimer le bon</a>';
$form = fn($a, $label, $cls = '', $confirm = '') => '<form method="post" action="' . e(url('commande/statut')) . '" class="inline"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $c['id'] . '"><input type="hidden" name="action" value="' . $a . '"><button class="btn ' . $cls . '">' . e($label) . '</button></form>';
if ($c['statut'] === 'proposee' && Auth::can('commandes.valider')) $acts .= $form('valider', 'Valider la commande', 'pri');
if ($c['statut'] === 'creee' && Auth::can('commandes.valider')) $acts .= $form('envoyer', 'Marquer comme envoyée', 'pri');
if (in_array($c['statut'], ['creee', 'envoyee', 'partielle'], true) && Auth::can('receptions')) $acts .= '<a class="btn pri" href="' . e(url('reception', ['commande' => $c['id']])) . '">' . ($c['statut'] === 'partielle' ? 'Réceptionner le reste' : 'Réceptionner') . '</a>';
if ($c['statut'] === 'partielle' && Auth::can('commandes.valider')) $acts .= $form('cloturer', 'Solder (ne plus attendre le reste)', '', 'Les produits manquants ne seront plus attendus. Continuer ?');
if (in_array($c['statut'], ['proposee', 'creee', 'envoyee'], true) && ($u['role'] === 'proprietaire' || $c['statut'] === 'proposee')) $acts .= $form('annuler', 'Annuler', 'danger', 'Annuler cette commande ?');
$steps = [
    ['Créée', quand($c['created_at']) . ' · ' . prenom($c['auteur']), true],
    ['Envoyée', $c['envoyee_at'] ? quand($c['envoyee_at']) : '—', (bool) $c['envoyee_at'] || in_array($c['statut'], ['partielle', 'livree'], true)],
    ['Livrée en partie', $receptions ? quand($receptions[0]['created_at']) : '—', in_array($c['statut'], ['partielle', 'livree'], true)],
    ['Livrée', $c['statut'] === 'livree' ? ($receptions ? quand(end($receptions)['created_at']) : '') : '—', $c['statut'] === 'livree'],
    ['Payée', $recu > 0 && $paye >= $recu ? quand(end($paiements)['at'] ?? null) : '—', $recu > 0 && $paye >= $recu],
];
?>
<?= page_header($c['numero'] . ' · ' . $c['f_nom'], pill($s[0], $s[1]) . ' <span class="muted">· livraison à ' . e(site((int) $c['site_id'])['nom']) . '</span>', $acts, '<a href="' . e(url('commandes')) . '">Commandes</a> / ' . e($c['numero'])) ?>
<main class="page">
  <?= flash_html() ?>
  <?php if ($c['statut'] !== 'annulee'): ?>
    <section class="card"><ol class="timeline" style="list-style:none;margin:0">
      <?php foreach ($steps as $i => [$l, $w, $done]): ?><li class="<?= $done ? 'done' : '' ?>"><span style="font-weight:600"><?= e($l) ?></span><span class="small muted"><?= e($w) ?></span></li><?php endforeach; ?>
    </ol></section>
  <?php endif; ?>
  <div class="row">
    <section class="card grow" style="overflow:hidden">
      <div class="card-h"><span class="h2">Produits commandés</span><span class="small muted"><?= e($c['notes'] ?? '') ?></span></div>
      <div class="scroll-x"><table class="table">
        <thead><tr><th>Produit</th><th class="ra">Commandé</th><th class="ra">Reçu</th><th>Reste</th><?php if ($voirPrix): ?><th class="ra">Prix achat</th><th class="ra">Montant</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): $rest = $l['quantite'] - $l['recu']; ?>
          <tr><td><a href="<?= e(url('produit', ['id' => $l['produit_id']])) ?>" style="color:var(--ink)"><?= e($l['designation']) ?></a></td><td class="num ra"><?= (int) $l['quantite'] ?></td><td class="num ra"><?= (int) $l['recu'] ?></td>
            <td><?= $rest <= 0 ? pill('Complet', 'ok') : ($c['statut'] === 'creee' || $c['statut'] === 'envoyee' || $c['statut'] === 'proposee' ? pill($rest . ' attendu' . ($rest > 1 ? 's' : ''), 'mute') : pill($rest . ' manquant' . ($rest > 1 ? 's' : ''), 'warn')) ?></td>
            <?php if ($voirPrix): ?><td class="num ra"><?= fmt($l['prix_achat']) ?></td><td class="num ra"><?= fmt($l['quantite'] * $l['prix_achat']) ?></td><?php endif; ?></tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($voirPrix): ?><tfoot><tr><td colspan="5" style="font-weight:600">Total commandé</td><td class="num ra tot"><?= fmt($total) ?> FCFA</td></tr></tfoot><?php endif; ?>
      </table></div>
    </section>
    <aside class="aside">
      <?php if ($voirPrix): $reste = $recu - $paye; ?>
        <section class="card">
          <div class="card-h"><span class="h2">Paiement</span><?= $recu == 0 ? pill('Rien de reçu', 'mute') : ($reste <= 0 ? pill('Payée', 'ok') : pill('Non payée', 'warn')) ?></div>
          <div class="card-b stack">
            <div class="dl">
              <div><span class="k">Valeur reçue à ce jour</span><span class="num"><?= fmt($recu) ?></span></div>
              <div><span class="k">Déjà payé</span><span class="num"><?= fmt($paye) ?></span></div>
              <div><span style="font-weight:600">Reste à payer</span><span class="num tot"><?= fmt(max(0, $reste)) ?> FCFA</span></div>
            </div>
            <p class="small muted">Vous ne payez que ce qui a été reçu. Condition fournisseur : <?= e($c['conditions'] ?: '—') ?>.</p>
            <?php if ($reste > 0 && Auth::can('fournisseurs')): ?><button class="btn pri" data-dialog="dlg-pay">Enregistrer un paiement en espèces</button><?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
      <section class="card">
        <div class="card-h"><span class="h2">Historique</span></div>
        <ul class="card-b stack-s">
          <?php foreach (array_reverse($paiements) as $p): ?><li><span style="font-weight:600">Paiement de <?= fmt($p['montant']) ?> FCFA</span><br><span class="small muted"><?= e(quand($p['at'])) ?> · <?= e(prenom($p['nom'])) ?></span></li><?php endforeach; ?>
          <?php foreach (array_reverse($receptions) as $i => $r): ?><li><span style="font-weight:600"><?= $r['statut'] === 'brouillon' ? 'Réception en brouillon' : 'Livraison reçue' ?> par <?= e(prenom($r['nom'])) ?></span><br><span class="small muted"><?= e(quand($r['created_at'])) ?> · <?= e(site((int) $r['site_id'])['nom']) ?><?= $r['bl_numero'] ? ' · BL ' . e($r['bl_numero']) : '' ?></span>
            <?php if ($r['statut'] === 'brouillon'): ?> · <a href="<?= e(url('reception', ['id' => $r['id']])) ?>">Reprendre</a><?php endif; ?></li><?php endforeach; ?>
          <?php if ($c['envoyee_at']): ?><li><span style="font-weight:600">Bon remis au fournisseur</span><br><span class="small muted"><?= e(quand($c['envoyee_at'])) ?></span></li><?php endif; ?>
          <li><span style="font-weight:600"><?= $c['statut'] === 'proposee' ? 'Proposée' : 'Créée' ?> par <?= e(prenom($c['auteur'])) ?></span><br><span class="small muted"><?= e(quand($c['created_at'])) ?></span></li>
        </ul>
      </section>
    </aside>
  </div>
</main>
<dialog id="dlg-pay">
  <form method="post" action="<?= e(url('commande/paiement')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
    <div class="dh"><h2 class="h2">Paiement · <?= e($c['f_nom']) ?></h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <div><label class="lbl" for="pm">Montant payé en espèces (FCFA)</label><input id="pm" name="montant" class="inp num" inputmode="numeric" value="<?= (int) max(0, $recu - $paye) ?>" required></div>
      <div><label class="lbl" for="pn">Note <span class="opt">(facultatif)</span></label><input id="pn" name="note" class="inp" placeholder="Ex. reçu n°…"></div>
      <p class="hint">Le montant est retiré de vos espèces.</p>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Enregistrer</button></div>
  </form>
</dialog>
