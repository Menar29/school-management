<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Mes ventes et clôture</title>
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>
<div class="pos">
  <header class="pos-h">
    <a class="btn ghost" style="color:#fff" href="<?= e(url('caisse')) ?>"><?= icon('back') ?>Retour à la vente</a>
    <h1 class="h2" style="color:#fff">Mes ventes et clôture</h1>
    <span style="flex:1"></span>
    <span class="hide-m">Officine <?= e($site['nom_court']) ?> · <?= e($sess['poste']) ?></span>
    <span class="flex"><span class="av"><?= e(initiales($u['nom'])) ?></span><span style="color:#fff;font-weight:600" class="hide-m"><?= e(prenom($u['nom'])) ?></span></span>
  </header>
  <?php foreach (flashes() as [$t, $m]): ?><div class="banner <?= e($t) ?>" style="border-radius:0"><?= e($m) ?></div><?php endforeach; ?>
  <div class="pos-b">
    <section class="pos-l" style="padding:0">
      <div class="card-h" style="background:#fff">
        <span style="display:flex;flex-direction:column"><span class="h2">Mes ventes du jour</span>
          <span class="small muted">Session ouverte à <?= e(date('H:i', strtotime($sess['ouverte_at']))) ?> · <?= $r['nb'] ?> vente<?= $r['nb'] > 1 ? 's' : '' ?> · <?= $r['annulees'] ?> annulation<?= $r['annulees'] > 1 ? 's' : '' ?></span></span>
        <button class="btn" data-dialog="dlg-dep">Dépense de caisse</button>
      </div>
      <div class="scroll-x" style="background:#fff;flex:1">
        <table class="table">
          <thead><tr><th>Heure</th><th>Ticket</th><th>Articles</th><th class="ra">Montant</th><th>Paiement</th><th><span class="sr">Actions</span></th></tr></thead>
          <tbody>
          <?php foreach ($ventes as $v): $ann = $v['statut'] === 'annulee'; ?>
            <tr style="<?= $ann ? 'color:var(--muted)' : '' ?>">
              <td class="num"><?= e(date('H:i', strtotime($v['at']))) ?></td>
              <td class="num" style="white-space:nowrap"><?= e($v['numero']) ?></td>
              <td style="max-width:360px" class="ell"><?= e($v['articles']) ?></td>
              <td class="num ra" style="font-weight:600;<?= $ann ? 'text-decoration:line-through' : '' ?>"><?= fmt($v['total']) ?></td>
              <td><?= $ann ? pill('Annulée', 'mute') : ($v['mode'] === 'credit' ? pill('Crédit · ' . $v['client'], 'info') : pill('Espèces', 'ok')) ?></td>
              <td class="ra" style="white-space:nowrap">
                <a class="btn ghost" href="<?= e(url('caisse/ticket', ['id' => $v['id'], 'print' => 1])) ?>" target="_blank" aria-label="Imprimer le ticket <?= e($v['numero']) ?>"><?= icon('print') ?></a>
                <?php if (!$ann): ?><button class="btn ghost" data-dialog="dlg-ann" data-id="<?= (int) $v['id'] ?>" data-num="Annuler la vente <?= e($v['numero']) ?> · <?= fmt($v['total']) ?> FCFA">Annuler</button><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$ventes): ?><tr><td colspan="6" class="empty">Aucune vente pour l'instant.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <aside class="pos-r" style="width:460px">
      <div class="card-h"><span class="h2">Clôturer ma caisse</span><span class="small muted">Étape <?= $compte ? 2 : 1 ?> sur 2</span></div>
      <?php if (!$compte): ?>
        <form method="post" action="<?= e(url('caisse/cloturer')) ?>" style="display:flex;flex-direction:column;flex:1;min-height:0" id="count-form">
          <?= csrf_field() ?><input type="hidden" name="etape" value="compter">
          <div style="padding:16px 18px;overflow:auto;flex:1" class="stack-s">
            <div class="banner info">Comptez les espèces du tiroir, coupure par coupure. Le montant attendu s'affiche après le comptage.</div>
            <?php foreach (Caisse::COUPURES as $c): ?>
              <div class="between"><label for="b<?= $c ?>" style="width:90px;font-weight:600" class="num"><?= fmt($c) ?> F</label><span class="muted">×</span>
                <input id="b<?= $c ?>" name="b<?= $c ?>" type="number" min="0" inputmode="numeric" class="inp num" value="0" style="width:90px" data-mult="<?= $c ?>">
                <span class="num ra" style="width:100px" data-sub>0</span></div>
            <?php endforeach; ?>
            <div class="between"><label for="pieces" style="width:90px;font-weight:600">Pièces (total)</label><span></span>
              <input id="pieces" name="pieces" type="number" min="0" inputmode="numeric" class="inp num" value="0" style="width:120px" data-mult="1"><span style="width:100px"></span></div>
          </div>
          <div class="pos-f">
            <div class="between"><span class="h3">Total compté</span><span class="total-big" style="font-size:30px"><span id="counted">0</span> <small style="font-size:15px;color:var(--muted)">FCFA</small></span></div>
            <button class="btn pri lg block" type="submit">Valider le comptage</button>
          </div>
        </form>
        <script>
          (function(){const f=document.getElementById('count-form');const fm=n=>String(n).replace(/\B(?=(\d{3})+(?!\d))/g,' ');
          function upd(){let t=0;f.querySelectorAll('[data-mult]').forEach(i=>{const v=(parseInt(i.value,10)||0)*parseInt(i.dataset.mult,10);t+=v;const s=i.parentElement.querySelector('[data-sub]');if(s)s.textContent=fm(v);});document.getElementById('counted').textContent=fm(t);}
          f.addEventListener('input',upd);upd();})();
        </script>
      <?php else: $ecart = $compte['total'] - $r['attendu']; ?>
        <form method="post" action="<?= e(url('caisse/cloturer')) ?>" style="display:flex;flex-direction:column;flex:1">
          <?= csrf_field() ?><input type="hidden" name="etape" value="confirmer">
          <div style="padding:16px 18px;flex:1;overflow:auto" class="stack">
            <div class="dl">
              <?php if ($r['fond']): ?><div><span class="k">Fond de caisse</span><span class="num">+ <?= fmt($r['fond']) ?></span></div><?php endif; ?>
              <div><span class="k">Ventes en espèces</span><span class="num">+ <?= fmt($r['especes']) ?></span></div>
              <?php foreach ($r['liste_depenses'] as $d): ?><div><span class="k">Dépense de caisse · <?= e($d['libelle']) ?></span><span class="num">− <?= fmt($d['montant']) ?></span></div><?php endforeach; ?>
              <div><span style="font-weight:600">Espèces attendues</span><span class="num tot"><?= fmt($r['attendu']) ?></span></div>
              <div><span style="font-weight:600">Espèces comptées</span><span class="num tot"><?= fmt($compte['total']) ?></span></div>
              <?php if ($r['credit']): ?><div><span class="k">Ventes à crédit (hors caisse)</span><span class="num"><?= fmt($r['credit']) ?></span></div><?php endif; ?>
            </div>
            <div class="change <?= $ecart < 0 ? 'short' : '' ?>" style="<?= $ecart > 0 ? 'background:var(--warn-soft);color:var(--warn-ink)' : '' ?>">
              <span><?= $ecart === 0 ? 'Caisse juste' : ($ecart < 0 ? 'Il manque' : 'Il y a en trop') ?></span><b><?= fmt_signe($ecart) ?></b>
            </div>
            <?php if ($ecart !== 0): ?>
              <div><label class="lbl" for="why">Expliquez l'écart</label>
                <textarea id="why" name="explication" class="inp" placeholder="Ex. monnaie rendue en trop sur une vente" required></textarea>
                <p class="hint">Le propriétaire verra l'écart et votre explication.</p></div>
            <?php endif; ?>
          </div>
          <div class="pos-f">
            <div class="flex"><button class="btn" type="submit" form="recount">Recompter</button><button class="btn pri lg" style="flex:1" type="submit">Clôturer et remettre les espèces</button></div>
          </div>
        </form>
        <form id="recount" method="post" action="<?= e(url('caisse/cloturer')) ?>"><?= csrf_field() ?><input type="hidden" name="etape" value="recompter"></form>
      <?php endif; ?>
    </aside>
  </div>
</div>

<dialog id="dlg-ann">
  <form method="post" action="<?= e(url('caisse/annuler')) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" data-fill="id">
    <div class="dh"><span style="display:flex;flex-direction:column"><span class="cap">Action réservée à un responsable</span><h2 class="h2" data-fill="num"></h2></span><button class="ibtn" data-close type="button" aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <?php if ($u['role'] === 'vendeur'): ?>
        <p class="muted">Le gérant ou le propriétaire doit saisir son code. L'annulation sera tracée avec votre nom et l'heure.</p>
        <div><label class="lbl" for="ann-pin">Code du responsable</label><input id="ann-pin" name="pin" type="password" inputmode="numeric" maxlength="4" class="inp num" style="width:160px;font-size:22px;letter-spacing:.4em" required></div>
      <?php endif; ?>
      <div><label class="lbl" for="ann-motif">Motif</label><input id="ann-motif" name="motif" class="inp" placeholder="Ex. erreur de produit" required></div>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Retour</button><button class="btn danger" type="submit">Confirmer l'annulation</button></div>
  </form>
</dialog>

<dialog id="dlg-dep">
  <form method="post" action="<?= e(url('caisse/depense')) ?>">
    <?= csrf_field() ?>
    <div class="dh"><h2 class="h2">Dépense payée avec la caisse</h2><button class="ibtn" data-close type="button" aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <div><label class="lbl" for="dep-l">Motif</label><input id="dep-l" name="libelle" class="inp" placeholder="Ex. transport livraison" required></div>
      <div><label class="lbl" for="dep-m">Montant (FCFA)</label><input id="dep-m" name="montant" class="inp num" inputmode="numeric" required></div>
      <p class="hint">Gardez le justificatif avec les espèces. Le propriétaire voit chaque dépense.</p>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri" type="submit">Enregistrer</button></div>
  </form>
</dialog>
<script src="assets/app.js?v=1"></script>
</body>
</html>
