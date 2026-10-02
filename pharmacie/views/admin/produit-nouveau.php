<?php
$o = fn($k, $d = '') => e($old[$k] ?? ($_GET[$k] ?? $d));
$role = $u['role'];
$errMsg = fn($k) => isset($err[$k]) ? '<p class="err-msg">' . e($err[$k]) . '</p>' : '';
$detail = isset($old['detail']) ? (bool) $old['detail'] : !is_post();
?>
<?= page_header('Nouveau produit', $role === 'magasinier' ? 'Le produit sera créé sans prix : le propriétaire fixera son prix avant qu\'il soit vendable.' : ($role === 'gerant' ? 'Le produit sera vendable tout de suite ; le propriétaire est prévenu et peut ajuster le prix.' : ''),
  '<a class="btn ghost" href="' . e(url('produits')) . '">Annuler</a><button class="btn pri" form="np">Enregistrer le produit</button>', '<a href="' . e(url('produits')) . '">Produits</a> / Nouveau') ?>
<main class="page">
  <?php if ($err): ?><div class="banner bad" role="alert"><span><strong>Le produit n'est pas enregistré.</strong> Corrigez les champs signalés en rouge : <?= e(implode(', ', array_keys($err))) ?>.</span></div><?php endif; ?>
  <form method="post" action="<?= e(url('produit/nouveau')) ?>" id="np" class="stack" style="max-width:980px">
    <?= csrf_field() ?>
    <section class="card">
      <div class="card-h"><span class="h2">1 · Retrouver le médicament</span><span class="small muted">Évite les doublons</span></div>
      <div class="card-b stack">
        <div><label class="lbl" for="q">Nom commercial ou molécule</label><input id="q" class="inp" placeholder="Ex. amoxi clav, paracétamol, loratadine" autocomplete="off"></div>
        <div id="dups" hidden>
          <span class="cap">Déjà dans votre catalogue · vérifiez avant de créer</span>
          <div id="duplist" class="stack-s mt"></div>
          <p class="hint">Même molécule mais dosage ou forme différents ? Alors c'est bien un nouveau produit.</p>
        </div>
      </div>
    </section>
    <section class="card">
      <div class="card-h"><span class="h2">2 · Identification</span><span class="small muted">Seuls les champs marqués * sont obligatoires</span></div>
      <div class="card-b grid g2">
        <div style="grid-column:1/-1"><label class="lbl" for="des">Désignation *</label><input id="des" name="designation" class="inp <?= isset($err['designation']) ? 'err' : '' ?>" value="<?= $o('designation') ?>" placeholder="Ex. LORATADINE 10MG CP B/10" style="text-transform:uppercase" required>
          <?= $errMsg('designation') ?: '<p class="hint">Comme sur la boîte : nom, dosage, forme, contenance. Mis en majuscules automatiquement.</p>' ?></div>
        <div><label class="lbl" for="dci">Molécule (DCI) <span class="opt">(conseillé)</span></label><input id="dci" name="dci" class="inp" value="<?= $o('dci') ?>" placeholder="Ex. Loratadine"></div>
        <div><label class="lbl" for="dos">Dosage <span class="opt">(facultatif)</span></label><input id="dos" name="dosage" class="inp" value="<?= $o('dosage') ?>" placeholder="Ex. 10 mg"></div>
        <fieldset style="grid-column:1/-1;border:none;padding:0;margin:0"><legend class="lbl">Forme *</legend>
          <div class="chips"><?php foreach (FORMES as $f): ?><label class="chip"><input type="radio" name="forme" value="<?= e($f) ?>" <?= ($old['forme'] ?? '') === $f ? 'checked' : '' ?> class="chk" style="width:16px;height:16px"> <?= e($f) ?></label><?php endforeach; ?></div><?= $errMsg('forme') ?></fieldset>
        <div><label class="lbl" for="cat">Catégorie</label><select id="cat" name="categorie" class="inp"><?php foreach (CATEGORIES as $c): ?><option <?= ($old['categorie'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
        <div><label class="lbl" for="cb">Code-barres <span class="opt">(facultatif)</span></label><input id="cb" name="code_barre" class="inp num" value="<?= $o('code_barre') ?>" placeholder="Scannez la boîte ou tapez le code"></div>
        <div><label class="flex"><input type="checkbox" name="ordonnance" value="1" class="chk" <?= !empty($old['ordonnance']) ? 'checked' : '' ?>> Délivré sur ordonnance</label><p class="hint">Le vendeur verra un rappel « Ordonnance » au moment de l'ajouter au ticket. La vente n'est pas bloquée.</p></div>
        <div><span class="lbl">Noms utilisés au comptoir</span><div class="flex" id="aliases"><input type="hidden" name="alias[]" value=""><input class="inp" id="newalias" placeholder="Ex. AMOXI CLAV (Entrée)" style="width:240px"></div><p class="hint">Les abréviations de votre journal papier. La recherche en caisse les comprend.</p></div>
      </div>
    </section>
    <section class="card">
      <div class="card-h"><span class="h2">3 · Conditionnement<?= $role !== 'magasinier' ? ' et prix' : '' ?></span><span class="small muted">En FCFA</span></div>
      <div class="card-b stack">
        <div class="flex"><span style="font-weight:600">Une boîte contient *</span><input name="contenance" type="number" min="1" class="inp num <?= isset($err['contenance']) ? 'err' : '' ?>" style="width:90px" value="<?= $o('contenance') ?>" id="bq" aria-label="Contenu d'une boîte">
          <label class="sr" for="ub">Unité</label><input id="ub" name="unite_base" class="inp" style="width:160px" value="<?= $o('unite_base', 'comprimés') ?>"></div>
        <?= $errMsg('contenance') ?>
        <label class="flex"><input type="checkbox" name="detail" value="1" class="chk" id="det" <?= $detail ? 'checked' : '' ?>> Vendu aussi au détail</label>
        <div class="flex" id="detw">
          <label class="sr" for="dn">Nom de l'unité de détail</label><input id="dn" name="detail_nom" class="inp" style="width:140px" value="<?= $o('detail_nom', 'Plaquette') ?>"><span>de</span>
          <label class="sr" for="dq">Nombre par unité de détail</label><input id="dq" name="detail_contenance" type="number" min="1" class="inp num <?= isset($err['detail']) ? 'err' : '' ?>" style="width:90px" value="<?= $o('detail_contenance') ?>"><span id="dqu"></span><span class="pill info" id="per" hidden></span>
        </div>
        <?= $errMsg('detail') ?>
        <?php if ($role !== 'magasinier'): ?>
          <div class="grid g3">
            <div><label class="lbl" for="pb">Prix de vente de la boîte <?= $role === 'gerant' ? '*' : '' ?></label><input id="pb" name="prix_boite" class="inp num <?= isset($err['prix']) ? 'err' : '' ?>" inputmode="numeric" value="<?= $o('prix_boite') ?>"></div>
            <div id="pdw"><label class="lbl" for="pd">Prix de vente du détail</label><input id="pd" name="prix_detail" class="inp num" inputmode="numeric" value="<?= $o('prix_detail') ?>"></div>
            <?php if ($role === 'proprietaire'): ?><div><label class="lbl" for="pa">Prix d'achat de la boîte</label><input id="pa" name="prix_achat" class="inp num" inputmode="numeric" value="<?= $o('prix_achat') ?>"><p class="hint" id="mg"></p></div><?php endif; ?>
          </div>
          <?= $errMsg('prix') ?>
        <?php endif; ?>
        <div style="max-width:320px"><label class="lbl" for="fo">Fournisseur habituel</label><select id="fo" name="fournisseur_id" class="inp"><option value="">—</option><?php foreach ($fournisseurs as $f): ?><option value="<?= (int) $f['id'] ?>" <?= (int) ($old['fournisseur_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['code'] . ' · ' . $f['nom']) ?></option><?php endforeach; ?></select></div>
        <p class="hint">Le prix d'achat sera aussi repris de la facture à la réception. Le produit apparaîtra en caisse dès que du stock sera reçu, avec son lot et sa péremption.</p>
      </div>
    </section>
  </form>
</main>
<script>
(function(){
  const des=document.getElementById('des');des.addEventListener('input',()=>{const p=des.selectionStart;des.value=des.value.toUpperCase();des.setSelectionRange(p,p);});
  const det=document.getElementById('det'),w=document.getElementById('detw'),pdw=document.getElementById('pdw'),bq=document.getElementById('bq'),dq=document.getElementById('dq'),per=document.getElementById('per'),ub=document.getElementById('ub');
  function upd(){w.hidden=!det.checked;if(pdw)pdw.hidden=!det.checked;document.getElementById('dqu').textContent=ub.value;const b=parseInt(bq.value,10),d=parseInt(dq.value,10);per.hidden=!(b>0&&d>0&&b%d===0);per.textContent=(b/d)+' par boîte';
    const pa=document.getElementById('pa'),pb=document.getElementById('pb'),mg=document.getElementById('mg');if(mg){const a=parseInt((pa.value||'').replace(/\D/g,''),10),s=parseInt((pb.value||'').replace(/\D/g,''),10);mg.textContent=a>0&&s>0?'Marge sur la boîte : '+Math.round((s-a)/s*100)+' %':'';}}
  document.getElementById('np').addEventListener('input',upd);det.addEventListener('change',upd);upd();
  const box=document.getElementById('aliases'),inp=document.getElementById('newalias');
  inp.addEventListener('keydown',e=>{if(e.key!=='Enter')return;e.preventDefault();const v=inp.value.trim().toUpperCase();if(!v)return;const s=document.createElement('span');s.className='tag';s.textContent=v;const h=document.createElement('input');h.type='hidden';h.name='alias[]';h.value=v;const b=document.createElement('button');b.type='button';b.textContent='×';b.onclick=()=>s.remove();s.append(h,b);box.insertBefore(s,inp);inp.value='';});
  const q=document.getElementById('q'),dups=document.getElementById('dups'),dl=document.getElementById('duplist');let t;
  async function look(v){if(v.trim().length<3){dups.hidden=true;return;}const r=await fetch('index.php?r=produit/recherche&q='+encodeURIComponent(v),{headers:{'X-Requested-With':'fetch'}});const d=await r.json();dups.hidden=!d.length;
    dl.innerHTML=d.slice(0,5).map(p=>'<div class="between card" style="padding:10px 14px"><span><strong></strong><br><span class="small muted"></span></span><a class="btn ghost" href="index.php?r=produit&id='+p.id+'">Ouvrir sa fiche</a></div>').join('');
    dl.querySelectorAll('.between').forEach((el,i)=>{el.querySelector('strong').textContent=d[i].designation;el.querySelector('.muted').textContent=(d[i].dci||'')+(d[i].alias?' · au comptoir « '+d[i].alias+' »':'');});}
  q.addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>look(q.value),250);});
  des.addEventListener('change',()=>look(des.value.split(' ')[0]));
})();
</script>
