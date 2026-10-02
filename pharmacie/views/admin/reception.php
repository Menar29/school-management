<?php $voirPrix = $u['role'] !== 'magasinier'; ?>
<?= page_header('Réceptionner une livraison' . ($fournisseur ? ' · ' . $fournisseur['nom'] : ''), $rec ? pill('Brouillon', 'warn') : '', '',
  ($cmd ? '<a href="' . e(url('commande', ['id' => $cmd['id']])) . '">' . e($cmd['numero']) . '</a> / ' : '<a href="' . e(url('receptions')) . '">Réceptions</a> / ') . 'Livraison') ?>
<main class="page">
  <?= flash_html() ?>
  <form method="post" action="<?= e(url('reception/enregistrer')) ?>" class="stack" id="rec" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($rec['id'] ?? 0) ?>">
    <input type="hidden" name="commande" value="<?= (int) ($cmd['id'] ?? 0) ?>">
    <section class="card card-b grid g4">
      <?php if (!$cmd): ?>
        <div><label class="lbl" for="fo">Fournisseur</label><select id="fo" name="fournisseur" class="inp" required><?php foreach ($fournisseurs as $f): ?><option value="<?= (int) $f['id'] ?>" <?= $fournisseur && (int) $fournisseur['id'] === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['code'] . ' · ' . $f['nom']) ?></option><?php endforeach; ?></select></div>
      <?php else: ?><input type="hidden" name="fournisseur" value="<?= (int) $cmd['fournisseur_id'] ?>"><?php endif; ?>
      <div><label class="lbl" for="dest">Stock de destination</label>
        <?php if ($u['role'] === 'proprietaire'): ?><select id="dest" name="dest" class="inp"><?php foreach (sites() as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $dest ? 'selected' : '' ?>><?= e($s['nom']) ?></option><?php endforeach; ?></select>
        <?php else: ?><input id="dest" class="inp" value="<?= e(site($dest)['nom']) ?>" disabled><?php endif; ?></div>
      <div><label class="lbl" for="bl">N° du bon de livraison</label><input id="bl" name="bl" class="inp" value="<?= e($rec['bl_numero'] ?? '') ?>" placeholder="Tel qu'écrit sur le document"></div>
      <div><label class="lbl" for="dt">Date de livraison</label><input id="dt" name="date" type="date" class="inp" value="<?= e($rec['date_livraison'] ?? today()) ?>"></div>
      <?php if ($voirPrix): ?><div><label class="lbl" for="mf">Montant de la facture <span class="opt">(si fournie)</span></label><input id="mf" name="facture" class="inp num" inputmode="numeric" value="<?= e($rec['montant_facture'] ?? '') ?>"></div><?php endif; ?>
    </section>
    <section class="card" style="overflow:hidden">
      <div class="card-h"><span class="h2"><?= $cmd ? 'Produits restant à livrer' : 'Produits livrés' ?></span><span class="small muted">Saisissez ce qui est réellement dans les cartons</span></div>
      <div class="scroll-x"><table class="table" id="lines">
        <thead><tr><th>Produit</th><th class="ra">Attendu</th><th style="width:100px">Reçu</th><th style="width:160px">N° de lot</th><th style="width:130px">Péremption</th><?php if ($voirPrix): ?><th style="width:130px">Prix facturé</th><?php endif; ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
          <tr data-prix-cmd="<?= (int) ($l['prix_cmd'] ?? 0) ?>">
            <td><input type="hidden" name="produit[]" value="<?= (int) $l['produit_id'] ?>"><input type="hidden" name="cl[]" value="<?= (int) $l['cl'] ?>"><input type="hidden" name="attendu[]" value="<?= (int) $l['attendu'] ?>">
              <span style="display:flex;flex-direction:column"><span style="font-weight:600"><?= e($l['designation']) ?></span><?php if ($voirPrix && $l['prix_cmd']): ?><span class="small muted">Prix commandé <?= fmt($l['prix_cmd']) ?></span><?php endif; ?></span></td>
            <td class="num ra"><?= $l['cl'] ? (int) $l['attendu'] : '—' ?></td>
            <td><input name="q[]" type="number" min="0" class="inp num" value="<?= (int) $l['q'] ?>" aria-label="Quantité reçue"></td>
            <td><input name="lot[]" class="inp" value="<?= e($l['lot']) ?>" placeholder="Ex. LT-0987" aria-label="Numéro de lot"><span class="err-msg" hidden>Lot obligatoire</span></td>
            <td><input name="per[]" class="inp num" value="<?= e($l['per']) ?>" placeholder="MM/AAAA" aria-label="Péremption"><span class="warn-msg" hidden></span></td>
            <?php if ($voirPrix): ?><td><input name="prix[]" class="inp num" inputmode="numeric" value="<?= e($l['prix']) ?>" aria-label="Prix facturé"><span class="warn-msg" hidden></span></td><?php endif; ?>
            <td><?php if (!$l['cl']): ?><button type="button" class="ibtn" data-rm aria-label="Retirer"><?= icon('x') ?></button><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="card-b flex" style="border-top:1px solid var(--line)">
        <span class="small muted">Un produit est dans les cartons mais pas sur le bon de commande ?</span>
        <span style="position:relative"><label class="sr" for="addp">Ajouter un produit du catalogue</label><input id="addp" class="inp" placeholder="+ Produit du catalogue" autocomplete="off" style="width:300px"><div id="sugg" class="card" style="position:absolute;z-index:10;left:0;right:0;top:48px;max-height:260px;overflow:auto" hidden></div></span>
        <a class="btn ghost" href="<?= e(url('produit/nouveau')) ?>" target="_blank">+ Nouveau produit</a>
      </div>
    </section>
    <div class="banner warn" id="shortexp" hidden><span><strong>Péremption courte.</strong> Vous pouvez accepter ce lot ou le refuser au livreur. S'il est accepté, il sera vendu en priorité (premier périmé, premier sorti).</span></div>
    <section class="card card-b between" style="position:sticky;bottom:12px;box-shadow:var(--sh-2)">
      <span class="flex" style="gap:24px">
        <?php if ($voirPrix): ?>
          <span style="display:flex;flex-direction:column"><span class="small muted">Total calculé</span><span class="num tot"><span id="calc">0</span> FCFA</span></span>
          <span style="display:flex;flex-direction:column"><span class="small muted">Montant facture</span><span class="num tot"><span id="fact">—</span></span></span>
          <span id="concord"></span>
        <?php endif; ?>
        <span class="small" id="todo"></span>
      </span>
      <span class="flex"><button class="btn" name="action" value="brouillon">Enregistrer le brouillon</button><button class="btn pri lg" name="action" value="valider" id="valid">Valider la réception</button></span>
    </section>
  </form>
</main>
<script>
(function(){
  const f=document.getElementById('rec'),tb=f.querySelector('#lines tbody'),fm=n=>String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g,' ');
  const voirPrix=<?= $voirPrix ? 'true' : 'false' ?>;
  function parsePer(s){let m=s.trim().match(/^(\d{1,2})[\/.-](\d{4})$/);if(m)return new Date(+m[2],+m[1],0);m=s.trim().match(/^(\d{4})-(\d{2})/);if(m)return new Date(+m[1],+m[2],0);return null;}
  function check(){let todo=0,total=0,short=false;
    tb.querySelectorAll('tr').forEach(tr=>{const q=parseInt(tr.querySelector('[name="q[]"]').value,10)||0;const lot=tr.querySelector('[name="lot[]"]'),per=tr.querySelector('[name="per[]"]'),px=tr.querySelector('[name="prix[]"]');
      const le=lot.nextElementSibling,pw=per.nextElementSibling;const needLot=q>0&&!lot.value.trim();lot.classList.toggle('err',needLot);le.hidden=!needLot;if(needLot)todo++;
      const d=parsePer(per.value);per.classList.remove('err','warn');pw.hidden=true;
      if(q>0&&!d){per.classList.add('err');pw.hidden=false;pw.textContent='Format MM/AAAA';pw.className='err-msg';todo++;}
      else if(d){const months=(d-new Date())/2629800000;if(months<0){per.classList.add('err');pw.hidden=false;pw.className='err-msg';pw.textContent='Lot périmé';todo++;}else if(months<6){per.classList.add('warn');pw.hidden=false;pw.className='warn-msg';pw.textContent='Périme dans '+Math.max(1,Math.round(months))+' mois';short=true;}}
      if(px){const p=parseInt(px.value.replace(/\D/g,''),10)||0,pc=parseInt(tr.dataset.prixCmd,10)||0,w=px.nextElementSibling;total+=p*q;px.classList.toggle('warn',pc>0&&p!==pc);w.hidden=!(pc>0&&p!==pc);if(pc&&p!==pc)w.textContent=(p>pc?'+ ':'− ')+fm(Math.abs(p-pc))+' vs commande';}});
    if(voirPrix){document.getElementById('calc').textContent=fm(total);const mf=parseInt((document.getElementById('mf').value||'').replace(/\D/g,''),10);document.getElementById('fact').textContent=isNaN(mf)?'—':fm(mf)+' FCFA';
      document.getElementById('concord').innerHTML=isNaN(mf)?'':(mf===total?'<span class="pill ok">Concordant</span>':'<span class="pill warn">Écart '+fm(Math.abs(mf-total))+'</span>');}
    document.getElementById('shortexp').hidden=!short;document.getElementById('todo').innerHTML=todo?'<span class="pill bad">'+todo+' champ'+(todo>1?'s':'')+' à compléter</span>':'<span class="pill ok">Prêt à valider</span>';}
  f.addEventListener('input',check);check();
  tb.addEventListener('click',e=>{const b=e.target.closest('[data-rm]');if(b){b.closest('tr').remove();check();}});
  const inp=document.getElementById('addp'),sg=document.getElementById('sugg');let t;
  inp.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(async()=>{const q=inp.value.trim();if(q.length<2){sg.hidden=true;return;}
    const r=await fetch('index.php?r=produit/recherche&q='+encodeURIComponent(q),{headers:{'X-Requested-With':'fetch'}});const d=await r.json();
    sg.innerHTML=d.map(p=>'<button type="button" class="res" style="grid-template-columns:1fr" data-p=\''+JSON.stringify(p).replace(/'/g,'&#39;')+'\'>'+p.designation.replace(/</g,'&lt;')+'</button>').join('')||'<div class="empty small">Aucun produit. Créez-le avec « + Nouveau produit ».</div>';sg.hidden=false;},200);});
  sg.addEventListener('click',e=>{const b=e.target.closest('[data-p]');if(!b)return;const p=JSON.parse(b.dataset.p);const tr=document.createElement('tr');tr.dataset.prixCmd='0';
    tr.innerHTML='<td><input type="hidden" name="produit[]" value="'+p.id+'"><input type="hidden" name="cl[]" value="0"><input type="hidden" name="attendu[]" value="0"><span style="font-weight:600"></span></td><td class="num ra">—</td><td><input name="q[]" type="number" min="0" class="inp num" value="1"></td><td><input name="lot[]" class="inp" placeholder="Ex. LT-0987"><span class="err-msg" hidden>Lot obligatoire</span></td><td><input name="per[]" class="inp num" placeholder="MM/AAAA"><span class="warn-msg" hidden></span></td>'+(voirPrix?'<td><input name="prix[]" class="inp num" inputmode="numeric" value="'+(p.prix_achat||'')+'"><span class="warn-msg" hidden></span></td>':'')+'<td><button type="button" class="ibtn" data-rm aria-label="Retirer">×</button></td>';
    tr.querySelector('span').textContent=p.designation;tb.appendChild(tr);sg.hidden=true;inp.value='';check();tr.querySelector('[name="lot[]"]').focus();});
})();
</script>
