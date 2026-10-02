<?php
$owner = $u['role'] === 'proprietaire';
$fr = max(1, (int) $p['fractions']);
$uniteStock = ucfirst(Stock::uniteStock($p));
$coutBoite = (int) $p['prix_achat'];
$marge = fn($prix, $cout) => $prix && $cout ? round(($prix - $cout) / $prix * 100) . ' %' : '—';
$acts = '<a class="btn" href="' . e(url('stock/mouvements', ['produit' => $p['id']])) . '">Historique des mouvements</a>';
if ($p['a_verifier'] && Auth::can('produits.prix')) $acts .= '<form method="post" action="' . e(url('produit/verifie')) . '" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $p['id'] . '"><button class="btn">Marquer comme vérifié</button></form>';
$acts .= '<button class="btn pri" form="pf">Enregistrer</button>';
?>
<?= page_header($p['designation'], pill($st[1], $st[2]) . ($p['auteur'] ? ' <span class="muted">· créé par ' . e(prenom($p['auteur'])) . ' ' . e(quand($p['created_at'])) . '</span>' : ''), $acts, '<a href="' . e(url('produits')) . '">Produits</a> / Fiche') ?>
<main class="page">
  <?= flash_html() ?>
  <form method="post" action="<?= e(url('produit/enregistrer')) ?>" id="pf" class="row">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <div class="grow stack">
      <section class="card">
        <div class="card-h"><span class="h2">Vente au comptoir</span></div>
        <div class="card-b stack">
          <div>
            <span class="lbl">Noms utilisés par les vendeurs pour le retrouver</span>
            <div class="flex" id="aliases">
              <?php foreach ($alias as $a): ?><span class="tag"><?= e($a['alias']) ?><input type="hidden" name="alias[]" value="<?= e($a['alias']) ?>"><button type="button" data-rmtag aria-label="Retirer <?= e($a['alias']) ?>"><?= icon('x') ?></button></span><?php endforeach; ?>
              <input type="hidden" name="alias[]" value="">
              <input class="inp" id="newalias" placeholder="Ajouter un nom (Entrée)" style="width:220px">
            </div>
            <p class="hint">Les abréviations de votre journal (« PARA CP », « AMOXI GEL »…) pour que la recherche en caisse les comprenne.</p>
          </div>
          <div class="scroll-x"><table class="table">
            <thead><tr><th>Unité</th><th>Contient</th><th class="ra">Prix de vente</th><?php if ($owner): ?><th class="ra">Coût d'achat</th><th class="ra">Marge</th><?php endif; ?></tr></thead>
            <tbody>
              <tr><td><strong><?= e($uniteStock) ?></strong> <span class="small muted">unité de stock</span></td><td><?= (int) $p['contenance'] ?> <?= e($p['unite_base']) ?></td>
                <td class="ra"><?php if ($owner): ?><label class="sr" for="pb">Prix de vente</label><input id="pb" name="prix_boite" class="inp num" style="width:120px;text-align:right" value="<?= e($p['prix_boite']) ?>" inputmode="numeric"><?php else: ?><span class="num"><?= fmt($p['prix_boite']) ?></span><?php endif; ?></td>
                <?php if ($owner): ?><td class="num ra"><?= fmt($coutBoite ?: null) ?></td><td class="num ra"><?= $marge($p['prix_boite'], $coutBoite) ?></td><?php endif; ?></tr>
              <?php if ($p['detail_nom']): ?>
                <tr><td><strong><?= e($p['detail_nom']) ?></strong></td><td><?= (int) $p['detail_contenance'] ?> <?= e($p['unite_base']) ?> · 1/<?= $fr ?> <?= e(mb_strtolower($uniteStock)) ?></td>
                  <td class="ra"><?php if ($owner): ?><label class="sr" for="pd">Prix de la <?= e(mb_strtolower($p['detail_nom'])) ?></label><input id="pd" name="prix_detail" class="inp num" style="width:120px;text-align:right" value="<?= e($p['prix_detail']) ?>" inputmode="numeric"><?php else: ?><span class="num"><?= fmt($p['prix_detail']) ?></span><?php endif; ?></td>
                  <?php if ($owner): ?><td class="num ra"><?= $coutBoite ? fmt((int) round($coutBoite / $fr)) : '—' ?></td><td class="num ra"><?= $marge($p['prix_detail'], $coutBoite ? $coutBoite / $fr : 0) ?></td><?php endif; ?></tr>
              <?php endif; ?>
            </tbody>
          </table></div>
          <p class="small muted"><?= $p['detail_nom'] ? 'Quand un vendeur vend une ' . e(mb_strtolower($p['detail_nom'])) . ', le stock baisse de 1/' . $fr . ' de ' . e(mb_strtolower($uniteStock)) . '. ' : '' ?>Seul le propriétaire modifie les prix ; chaque changement est enregistré dans le journal.</p>
        </div>
      </section>
      <section class="card" style="overflow:hidden">
        <div class="card-h"><span class="h2">Lots en stock</span><span class="small muted">vendus dans cet ordre</span></div>
        <div class="scroll-x"><table class="table">
          <thead><tr><th>Lot</th><th>Emplacement</th><th class="ra">Quantité</th><th>Péremption</th><th>Statut</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($lots as $l): ?>
            <tr><td class="num"><?= e($l['numero']) ?></td><td><?= site_tag(site((int) $l['site_id'])) ?></td><td class="num ra"><?= e(Stock::qte($p, (int) $l['quantite'])) ?></td><td class="num"><?= e(mois_annee($l['peremption'])) ?></td>
              <td><?= $l['perime'] ? pill('Périmé · bloqué', 'bad') : ($l['bientot'] ? pill('Périme bientôt', 'warn') : pill('Valide', 'ok')) ?></td>
              <td class="ra"><?php if (Auth::can('stock.gerer') && ($owner || (int) $l['site_id'] === (int) $u['site_id'])): ?><button type="button" class="btn ghost" data-dialog="dlg-adj" data-lot="<?= (int) $l['id'] ?>" data-titre="Lot <?= e($l['numero']) ?> · <?= e(site((int) $l['site_id'])['nom_court']) ?>" data-b="<?= intdiv((int) $l['quantite'], $fr) ?>" data-d="<?= (int) $l['quantite'] % $fr ?>">Ajuster</button><?php endif; ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$lots): ?><tr><td colspan="6" class="empty">Aucun lot en stock<?= $monSite ? ' sur votre site' : '' ?>.</td></tr><?php endif; ?>
          </tbody>
        </table></div>
      </section>
      <section class="card">
        <div class="card-h"><span class="h2">Identification</span></div>
        <div class="card-b grid g2">
          <?php $ro = $owner ? '' : 'disabled'; ?>
          <div><label class="lbl" for="des">Désignation</label><input id="des" name="designation" class="inp" value="<?= e($p['designation']) ?>" <?= $ro ?>></div>
          <div><label class="lbl" for="dci">Molécule (DCI)</label><input id="dci" name="dci" class="inp" value="<?= e($p['dci']) ?>" <?= $ro ?>></div>
          <div><label class="lbl" for="dos">Dosage</label><input id="dos" name="dosage" class="inp" value="<?= e($p['dosage']) ?>" <?= $ro ?>></div>
          <div><label class="lbl" for="fo">Forme</label><select id="fo" name="forme" class="inp" <?= $ro ?>><?php foreach (FORMES as $f): ?><option <?= $p['forme'] === $f ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?></select></div>
          <div><label class="lbl" for="cat">Catégorie</label><select id="cat" name="categorie" class="inp" <?= $ro ?>><?php foreach (CATEGORIES as $c): ?><option <?= $p['categorie'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
          <div><label class="lbl" for="cla">Classe thérapeutique</label><input id="cla" name="classe" class="inp" value="<?= e($p['classe']) ?>" <?= $ro ?>></div>
          <div><label class="lbl" for="cb">Code-barres</label><input id="cb" name="code_barre" class="inp num" value="<?= e($p['code_barre']) ?>" <?= $ro ?>></div>
          <div class="flex" style="align-self:end;min-height:44px"><label class="flex"><input type="checkbox" name="ordonnance" value="1" class="chk" <?= $p['ordonnance'] ? 'checked' : '' ?> <?= $ro ?>> Délivré sur ordonnance</label>
            <?php if ($owner): ?><label class="flex"><input type="checkbox" name="actif" value="1" class="chk" <?= $p['actif'] ? 'checked' : '' ?>> En vente (décochez pour arrêter)</label><?php endif; ?></div>
          <?php if ($owner): ?>
            <input type="hidden" name="contenance" value="<?= (int) $p['contenance'] ?>"><input type="hidden" name="unite_base" value="<?= e($p['unite_base']) ?>">
            <?php if ($p['detail_nom']): ?><input type="hidden" name="detail" value="1"><input type="hidden" name="detail_nom" value="<?= e($p['detail_nom']) ?>"><input type="hidden" name="detail_contenance" value="<?= (int) $p['detail_contenance'] ?>"><?php endif; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>
    <aside class="aside">
      <section class="card">
        <div class="card-h"><span class="h2">Seuils d'alerte</span></div>
        <div class="card-b stack-s">
          <?php foreach (sites() as $s): $edit = $owner || (int) $s['id'] === (int) $u['site_id']; ?>
            <div class="between"><label for="s<?= (int) $s['id'] ?>"><?= site_tag($s) ?></label><input id="s<?= (int) $s['id'] ?>" <?= $edit ? 'name="seuil[' . (int) $s['id'] . ']"' : 'disabled' ?> type="number" min="0" class="inp num" style="width:90px" value="<?= (int) ($seuils[(int) $s['id']] ?? 0) ?>"></div>
          <?php endforeach; ?>
          <p class="hint">En <?= e(mb_strtolower($uniteStock)) ?>s. Sous ce seuil, le produit apparaît dans le réapprovisionnement.</p>
        </div>
      </section>
      <section class="card">
        <div class="card-h"><span class="h2">Achat</span></div>
        <div class="card-b stack">
          <div><label class="lbl" for="fou">Fournisseur habituel</label><select id="fou" name="fournisseur_id" class="inp" <?= $owner ? '' : 'disabled' ?>><option value="">—</option><?php foreach ($fournisseurs as $f): ?><option value="<?= (int) $f['id'] ?>" <?= (int) $p['fournisseur_id'] === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['code'] . ' · ' . $f['nom']) ?></option><?php endforeach; ?></select></div>
          <div class="dl">
            <?php if (Auth::can('prix_achat')): ?>
              <div><span class="k">Dernier prix d'achat</span><span class="num"><?php if ($owner): ?><input name="prix_achat" class="inp num" style="width:110px;text-align:right" value="<?= e($p['prix_achat']) ?>" aria-label="Dernier prix d'achat"><?php else: ?><?= fmt($p['prix_achat']) ?><?php endif; ?></span></div>
              <div><span class="k">Prix précédent</span><span class="num"><?= fmt($p['prix_achat_prec']) ?></span></div>
              <?php if ($dernierBC): ?><div><span class="k">Dernière commande</span><span class="num"><?= e($dernierBC['numero']) ?> · <?= fmt($dernierBC['prix_achat']) ?></span></div><?php endif; ?>
            <?php endif; ?>
            <div><span class="k">Ventes moyennes (30 j)</span><span class="num"><?= number_format($vj, 1, ',', '') ?> <?= e(mb_strtolower($uniteStock)) ?> / jour</span></div>
          </div>
        </div>
      </section>
    </aside>
  </form>
</main>
<dialog id="dlg-adj">
  <form method="post" action="<?= e(url('stock/ajuster')) ?>"><?= csrf_field() ?><input type="hidden" name="lot" data-fill="lot">
    <div class="dh"><h2 class="h2">Ajuster · <span data-fill="titre"></span></h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <p class="muted">Saisissez la quantité réellement présente. L'ajustement est enregistré avec votre nom.</p>
      <div class="flex"><label class="sr" for="ab">Boîtes</label><input id="ab" name="boites" type="number" min="0" class="inp num" style="width:100px" data-fill="b"><span><?= e(mb_strtolower($uniteStock)) ?>s</span>
        <?php if ($fr > 1): ?><label class="sr" for="ad"><?= e($p['detail_nom']) ?>s</label><input id="ad" name="detail" type="number" min="0" max="<?= $fr - 1 ?>" class="inp num" style="width:80px" data-fill="d"><span>+ <?= e(mb_strtolower($p['detail_nom'])) ?>s</span><?php endif; ?></div>
      <div><label class="lbl" for="am">Motif</label><select id="am" name="motif" class="inp" required><option value="">Choisir…</option><option>Casse</option><option>Erreur de saisie</option><option>Produit manquant (vol ou perte)</option><option>Produit retrouvé</option><option>Retour client</option><option>Don ou échantillon</option></select></div>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Enregistrer l'ajustement</button></div>
  </form>
</dialog>
<script>
(function(){const box=document.getElementById('aliases'),inp=document.getElementById('newalias');
inp.addEventListener('keydown',e=>{if(e.key!=='Enter')return;e.preventDefault();const v=inp.value.trim().toUpperCase();if(!v)return;const s=document.createElement('span');s.className='tag';s.textContent=v;const h=document.createElement('input');h.type='hidden';h.name='alias[]';h.value=v;const b=document.createElement('button');b.type='button';b.dataset.rmtag='';b.setAttribute('aria-label','Retirer '+v);b.textContent='×';s.append(h,b);box.insertBefore(s,inp);inp.value='';});
box.addEventListener('click',e=>{const b=e.target.closest('[data-rmtag]');if(b)b.closest('.tag').remove();});})();
</script>
