<?php $voirPrix = Auth::can('prix_achat'); ?>
<?= page_header('Nouvelle commande', $u['role'] === 'proprietaire' ? '' : 'Votre commande sera proposée au propriétaire, qui la valide.', '', '<a href="' . e(url('commandes')) . '">Commandes</a> / Nouvelle') ?>
<main class="page">
  <?= flash_html() ?>
  <form method="post" action="<?= e(url('commande/nouvelle')) ?>" class="stack" id="bc">
    <?= csrf_field() ?>
    <section class="card card-b grid g3">
      <div><label class="lbl" for="f">Fournisseur</label><select id="f" name="fournisseur" class="inp" required><option value="">—</option><?php foreach ($fournisseurs as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['code'] . ' · ' . $f['nom']) ?></option><?php endforeach; ?></select></div>
      <?php if ($u['role'] === 'proprietaire'): ?>
        <div><label class="lbl" for="d">Livraison à</label><select id="d" name="dest" class="inp"><?php foreach (sites() as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $s['type'] === 'magasin' ? 'selected' : '' ?>><?= e($s['nom']) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div><label class="lbl" for="n">Note <span class="opt">(facultatif)</span></label><input id="n" name="notes" class="inp"></div>
    </section>
    <section class="card" style="overflow:hidden">
      <div class="card-h"><span class="h2">Produits</span><button type="button" class="btn" id="addl"><?= icon('plus') ?>Ajouter une ligne</button></div>
      <table class="table" id="lines">
        <thead><tr><th>Produit</th><th class="ra" style="width:120px">Quantité</th><?php if ($voirPrix): ?><th class="ra" style="width:150px">Prix d'achat</th><?php endif; ?><th style="width:60px"></th></tr></thead>
        <tbody></tbody>
      </table>
    </section>
    <div><button class="btn pri lg"><?= $u['role'] === 'proprietaire' ? 'Créer le bon de commande' : 'Proposer la commande' ?></button></div>
  </form>
</main>
<template id="tpl"><tr>
  <td><select name="produit[]" class="inp" required><option value="">Choisir un produit…</option><?php foreach ($produits as $p): ?><option value="<?= (int) $p['id'] ?>" data-prix="<?= (int) $p['prix_achat'] ?>" data-f="<?= (int) $p['fournisseur_id'] ?>"><?= e($p['designation']) ?></option><?php endforeach; ?></select></td>
  <td><input name="qte[]" type="number" min="1" class="inp num" style="text-align:right" required></td>
  <?php if ($voirPrix): ?><td><input name="prix[]" class="inp num" style="text-align:right" inputmode="numeric"></td><?php endif; ?>
  <td><button type="button" class="ibtn" data-rm aria-label="Retirer"><?= icon('x') ?></button></td>
</tr></template>
<script>
(function(){const tb=document.querySelector('#lines tbody'),tpl=document.getElementById('tpl');function add(){const n=tpl.content.cloneNode(true);tb.appendChild(n);}
document.getElementById('addl').addEventListener('click',add);tb.addEventListener('click',e=>{const b=e.target.closest('[data-rm]');if(b)b.closest('tr').remove();});
tb.addEventListener('change',e=>{if(e.target.name==='produit[]'){const o=e.target.selectedOptions[0];const p=e.target.closest('tr').querySelector('[name="prix[]"]');if(p&&o)p.value=o.dataset.prix||'';}});
document.getElementById('f').addEventListener('change',e=>{const f=e.target.value;document.querySelectorAll('#lines select, #tpl').forEach(()=>{});tpl.content.querySelectorAll('option[data-f]').forEach(o=>{o.hidden=f&&o.dataset.f!==f&&o.dataset.f!=='0';});tb.querySelectorAll('option[data-f]').forEach(o=>{o.hidden=f&&o.dataset.f!==f&&o.dataset.f!=='0';});});
add();add();add();})();
</script>
