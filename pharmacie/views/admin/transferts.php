<?php $st = ['en_route' => ['En route', 'info'], 'recu' => ['Reçu', 'ok'], 'ecart' => ['Écart à traiter', 'bad']]; ?>
<?= page_header('Transferts de stock', 'Le stock reste « en route » tant que le site qui reçoit n\'a pas confirmé. Les lots qui périment en premier partent en premier.') ?>
<main class="page">
  <?= flash_html() ?>
  <?php foreach ($aRecevoir as $t): ?>
    <form method="post" action="<?= e(url('transfert/recevoir')) ?>" class="card" style="border-color:var(--info-line)"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
      <div class="card-h"><span class="h2"><?= e($t['numero']) ?> à confirmer · <?= e(site((int) $t['site_from'])['nom_court']) ?> → <?= e(site((int) $t['site_to'])['nom_court']) ?></span><span class="small muted">envoyé <?= e(quand($t['created_at'])) ?> par <?= e(prenom($t['auteur'])) ?></span></div>
      <div class="scroll-x"><table class="table">
        <thead><tr><th>Produit</th><th>Lot</th><th>Péremption</th><th class="ra">Envoyé</th><th>Reçu</th></tr></thead>
        <?php foreach ($t['lignes'] as $l): $f = max(1, (int) $l['fractions']); ?>
          <tr><td style="font-weight:600"><?= e($l['designation']) ?></td><td><?= e($l['numero_lot']) ?></td><td><?= e(mois_annee($l['peremption'])) ?></td><td class="num ra"><?= e(Stock::qte($l, (int) $l['envoye'])) ?></td>
            <td><span class="flex" style="flex-wrap:nowrap"><input name="recu[<?= (int) $l['id'] ?>][b]" type="number" min="0" class="inp num" style="width:80px" value="<?= intdiv((int) $l['envoye'], $f) ?>" aria-label="Reçu"><span class="small muted"><?= e(Stock::uniteStock($l)) ?>s</span>
              <?php if ($f > 1): ?><input name="recu[<?= (int) $l['id'] ?>][d]" type="number" min="0" class="inp num" style="width:64px" value="<?= (int) $l['envoye'] % $f ?>" aria-label="<?= e($l['detail_nom']) ?>s"><span class="small muted">+ <?= e(mb_strtolower($l['detail_nom'])) ?>s</span><?php endif; ?></span></td></tr>
        <?php endforeach; ?>
      </table></div>
      <div class="card-b between"><span class="small muted">Comptez ce qui est arrivé. Un écart est signalé automatiquement au propriétaire.</span><button class="btn pri">Confirmer la réception</button></div>
    </form>
  <?php endforeach; ?>
  <div class="row">
    <form method="post" action="<?= e(url('transfert/envoyer')) ?>" class="card aside" id="tf"><?= csrf_field() ?>
      <div class="card-h"><span class="h2">Nouveau transfert</span></div>
      <div class="card-b stack">
        <div class="grid g2">
          <div><label class="lbl" for="from">Depuis</label>
            <?php if ($mon): ?><input class="inp" value="<?= e(site($mon)['nom']) ?>" disabled id="from"><?php else: ?>
            <select id="from" name="from" class="inp"><?php foreach (sites() as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $s['type'] === 'magasin' ? 'selected' : '' ?>><?= e($s['nom']) ?></option><?php endforeach; ?></select><?php endif; ?></div>
          <div><label class="lbl" for="to">Vers</label><select id="to" name="to" class="inp"><?php foreach (sites() as $s): if ($mon === (int) $s['id']) continue; ?><option value="<?= (int) $s['id'] ?>" <?= $s['type'] === 'officine' && !$mon ? '' : '' ?>><?= e($s['nom']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div style="position:relative"><label class="lbl" for="addp">Ajouter un produit</label><input id="addp" class="inp" placeholder="Rechercher un produit" autocomplete="off"><div id="sugg" class="card" style="position:absolute;z-index:10;left:0;right:0;top:76px;max-height:260px;overflow:auto" hidden></div></div>
        <div id="tlines" class="stack-s"></div>
        <div><label class="lbl" for="tn">Note <span class="opt">(facultatif)</span></label><input id="tn" name="note" class="inp"></div>
        <button class="btn pri lg">Envoyer le transfert</button>
      </div>
    </form>
    <section class="card grow" style="overflow:hidden">
      <div class="card-h"><span class="h2">Transferts récents</span></div>
      <div class="scroll-x"><table class="table">
        <thead><tr><th>N°</th><th>Trajet</th><th class="ra">Produits</th><th>Envoyé</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $t): ?>
          <tr><td class="num"><?= e($t['numero']) ?></td><td><?= site_tag(site((int) $t['site_from'])) ?> → <?= site_tag(site((int) $t['site_to'])) ?></td><td class="num ra"><?= (int) $t['nb'] ?></td>
            <td><?= e(quand($t['created_at'])) ?> · <?= e(prenom($t['auteur'])) ?></td>
            <td><?= pill($st[$t['statut']][0], $st[$t['statut']][1]) ?><?php if ($t['statut'] === 'ecart' && $t['note']): ?><br><span class="small" style="color:var(--bad-ink)"><?= e($t['note']) ?></span><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">Aucun transfert.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </section>
  </div>
</main>
<script>
(function(){const inp=document.getElementById('addp'),sg=document.getElementById('sugg'),box=document.getElementById('tlines');let t;
inp.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(async()=>{const q=inp.value.trim();if(q.length<2){sg.hidden=true;return;}const r=await fetch('index.php?r=produit/recherche&q='+encodeURIComponent(q),{headers:{'X-Requested-With':'fetch'}});const d=await r.json();
sg.innerHTML='';d.forEach(p=>{const b=document.createElement('button');b.type='button';b.className='res';b.style.gridTemplateColumns='1fr';b.textContent=p.designation;b.onclick=()=>add(p);sg.appendChild(b);});if(!d.length)sg.innerHTML='<div class="empty small">Aucun produit.</div>';sg.hidden=false;},200);});
function add(p){const row=document.createElement('div');row.className='between card';row.style.padding='10px 12px';const n=document.createElement('span');n.style.fontWeight='600';n.textContent=p.designation;
const q=document.createElement('span');q.className='flex';q.innerHTML='<input type="hidden" name="produit[]" value="'+p.id+'"><input name="b[]" type="number" min="0" value="1" class="inp num" style="width:70px" aria-label="Boîtes">'+(p.fractions>1?'<input name="d[]" type="number" min="0" max="'+(p.fractions-1)+'" value="0" class="inp num" style="width:60px" aria-label="Détail"><span class="small muted">+ dét.</span>':'<input type="hidden" name="d[]" value="0">')+'<button type="button" class="ibtn" aria-label="Retirer">×</button>';
q.querySelector('button').onclick=()=>row.remove();row.append(n,q);box.appendChild(row);sg.hidden=true;inp.value='';}})();
</script>
