<?php $totalDu = array_sum(array_map(fn($f) => max(0, $f['du']), $list)); ?>
<?= page_header('Fournisseurs', count(array_filter($list, fn($f) => $f['actif'])) . ' fournisseur(s) actif(s) · ' . fmt($totalDu) . ' FCFA à payer',
  '<button class="btn pri" data-dialog="dlg-f" data-id="" data-code="" data-nom="" data-delai="7">' . icon('plus') . 'Nouveau fournisseur</button>') ?>
<main class="page">
  <?= flash_html() ?>
  <div class="row">
    <section class="card" style="width:360px;flex:none;overflow:hidden">
      <form method="get" class="card-b" style="border-bottom:1px solid var(--line)"><input type="hidden" name="r" value="fournisseurs"><label class="sr" for="fs">Rechercher</label><input id="fs" name="q" value="<?= e($q) ?>" class="inp" placeholder="Rechercher un fournisseur"></form>
      <?php foreach ($list as $f): $on = $sel && (int) $sel['id'] === (int) $f['id']; ?>
        <a href="<?= e(url('fournisseurs', ['id' => $f['id']])) ?>" class="between" style="padding:14px 20px;border-bottom:1px solid var(--line);text-decoration:none;color:var(--ink);<?= $on ? 'background:#F1F8F4' : '' ?>">
          <span class="flex" style="flex-wrap:nowrap"><span class="av b"><?= e(mb_substr($f['code'], 0, 3)) ?></span><span style="display:flex;flex-direction:column"><span style="font-weight:600"><?= e($f['nom']) ?></span><span class="small muted">Livre en <?= (int) $f['delai_jours'] ?> j · <?= $f['nb_cmd'] ?> commande(s)<?= $f['actif'] ? '' : ' · inactif' ?></span></span></span>
          <span style="display:flex;flex-direction:column;text-align:right"><span class="small muted">À payer</span><span class="num" style="font-weight:600;color:<?= $f['du'] > 0 ? 'var(--bad-ink)' : 'var(--ink)' ?>"><?= fmt(max(0, $f['du'])) ?></span></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$list): ?><p class="empty">Aucun fournisseur.</p><?php endif; ?>
    </section>
    <?php if ($sel): ?>
    <section class="grow stack">
      <div class="card card-b stack">
        <div class="between"><h2 class="h1"><?= e($sel['nom']) ?> <span class="muted small">(<?= e($sel['code']) ?>)</span></h2>
          <span class="flex"><button class="btn" data-dialog="dlg-f" data-id="<?= (int) $sel['id'] ?>" data-code="<?= e($sel['code']) ?>" data-nom="<?= e($sel['nom']) ?>" data-contact="<?= e($sel['contact']) ?>" data-tel="<?= e($sel['telephone']) ?>" data-adresse="<?= e($sel['adresse']) ?>" data-delai="<?= (int) $sel['delai_jours'] ?>" data-conditions="<?= e($sel['conditions']) ?>" data-actif="<?= (int) $sel['actif'] ?>">Modifier</button>
            <a class="btn pri" href="<?= e(url('reappro', ['f' => $sel['id']])) ?>">Nouvelle commande</a></span></div>
        <div class="grid g4 small">
          <div><div class="muted">Contact</div><div><?= e($sel['contact'] ?: '—') ?></div></div>
          <div><div class="muted">Téléphone</div><div><?= e($sel['telephone'] ?: '—') ?></div></div>
          <div><div class="muted">Délai de livraison</div><div><?= (int) $sel['delai_jours'] ?> jours</div></div>
          <div><div class="muted">Conditions de paiement</div><div><?= e($sel['conditions'] ?: '—') ?></div></div>
        </div>
      </div>
      <div class="grid g3">
        <div class="card kpi"><span class="l">À payer</span><span class="v" style="color:<?= $sel['du'] > 0 ? 'var(--bad-ink)' : 'var(--ink)' ?>"><?= fmt(max(0, $sel['du'])) ?><small>FCFA</small></span><span class="small muted">valeur reçue non payée</span></div>
        <div class="card kpi"><span class="l">Acheté en <?= date('Y') ?></span><span class="v"><?= fmt($achete) ?><small>FCFA</small></span><span class="small muted"><?= count($commandes) ?> commande(s)</span></div>
        <div class="card kpi"><span class="l">Livraisons complètes</span><span class="v"><?= $fill === null ? '—' : $fill . ' %' ?></span><span class="small muted">des commandes livrées sans manquant</span></div>
      </div>
      <section class="card" style="overflow:hidden">
        <div style="padding:0 20px;border-bottom:1px solid var(--line)" role="tablist">
          <?php foreach (['commandes' => 'Commandes', 'produits' => 'Produits et prix', 'paiements' => 'Paiements'] as $k => $l): ?>
            <a role="tab" class="tab <?= $tab === $k ? 'on' : '' ?>" href="<?= e(url('fournisseurs', ['id' => $sel['id'], 'tab' => $k])) ?>" aria-selected="<?= $tab === $k ? 'true' : 'false' ?>"><?= e($l) ?></a>
          <?php endforeach; ?>
        </div>
        <div class="scroll-x"><table class="table">
          <?php if ($tab === 'produits'): ?>
            <?php foreach ($produits as $p): $d = $p['prix_achat_prec'] && $p['prix_achat'] ? ($p['prix_achat'] - $p['prix_achat_prec']) / $p['prix_achat_prec'] * 100 : 0; ?>
              <tr><td class="num"><?= fmt($p['prix_achat']) ?> F</td><td><a href="<?= e(url('produit', ['id' => $p['id']])) ?>" style="color:var(--ink)"><?= e($p['designation']) ?></a></td><td class="num ra muted">avant <?= fmt($p['prix_achat_prec']) ?></td>
                <td><?= abs($d) < 0.05 ? pill('Prix stable', 'ok') : pill(($d > 0 ? '+ ' : '− ') . number_format(abs($d), 1, ',', '') . ' %', $d > 0 ? 'warn' : 'ok') ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$produits): ?><tr><td class="empty">Aucun produit n'a ce fournisseur comme fournisseur habituel.</td></tr><?php endif; ?>
          <?php elseif ($tab === 'paiements'): ?>
            <?php foreach ($paiements as $p): ?><tr><td><?= e(date_fr($p['at'])) ?></td><td><?= e($p['note'] ?: 'Espèces') ?><?= $p['numero'] ? ' · ' . e($p['numero']) : '' ?></td><td class="num ra"><?= fmt($p['montant']) ?></td><td><?= pill('Payé', 'ok') ?></td></tr><?php endforeach; ?>
            <?php if (!$paiements): ?><tr><td class="empty">Aucun paiement.</td></tr><?php endif; ?>
          <?php else: ?>
            <?php foreach ($commandes as $c): $st = STATUTS_CMD[$c['statut']]; $reste = $c['recu'] - $c['paye']; ?>
              <tr class="hov" data-href="<?= e(url('commande', ['id' => $c['id']])) ?>" style="cursor:pointer"><td><a href="<?= e(url('commande', ['id' => $c['id']])) ?>" style="font-weight:600"><?= e($c['numero']) ?></a></td><td><?= (int) $c['nb'] ?> produit(s) · <?= e(quand($c['created_at'])) ?></td><td class="num ra"><?= fmt($c['montant']) ?></td>
                <td><?= $c['recu'] > 0 && $reste <= 0 ? pill('Payée', 'ok') : ($c['recu'] > 0 ? pill('À payer ' . fmt($reste), 'warn') : pill($st[0], $st[1])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$commandes): ?><tr><td class="empty">Aucune commande.</td></tr><?php endif; ?>
          <?php endif; ?>
        </table></div>
      </section>
    </section>
    <?php endif; ?>
  </div>
</main>
<dialog id="dlg-f">
  <form method="post" action="<?= e(url('fournisseur/enregistrer')) ?>"><?= csrf_field() ?><input type="hidden" name="id" data-fill="id">
    <div class="dh"><h2 class="h2">Fournisseur</h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <div class="grid g2"><div><label class="lbl" for="f-c">Code</label><input id="f-c" name="code" class="inp" data-fill="code" required maxlength="20"></div><div><label class="lbl" for="f-n">Nom</label><input id="f-n" name="nom" class="inp" data-fill="nom" required></div></div>
      <div class="grid g2"><div><label class="lbl" for="f-ct">Contact</label><input id="f-ct" name="contact" class="inp" data-fill="contact"></div><div><label class="lbl" for="f-t">Téléphone</label><input id="f-t" name="telephone" class="inp" data-fill="tel"></div></div>
      <div><label class="lbl" for="f-a">Adresse</label><input id="f-a" name="adresse" class="inp" data-fill="adresse"></div>
      <div class="grid g2"><div><label class="lbl" for="f-d">Délai de livraison (jours)</label><input id="f-d" name="delai_jours" type="number" min="0" class="inp num" data-fill="delai"></div><div><label class="lbl" for="f-co">Conditions de paiement</label><input id="f-co" name="conditions" class="inp" data-fill="conditions" placeholder="Ex. à 30 jours"></div></div>
      <div><label class="lbl" for="f-ac">Statut</label><select id="f-ac" name="actif" class="inp" data-fill="actif"><option value="1">Actif</option><option value="0">Inactif</option></select></div>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Enregistrer</button></div>
  </form>
</dialog>
