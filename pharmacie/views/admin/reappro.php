<?php
$seg = '<div class="seg" role="group" aria-label="Fournisseur">';
$seg .= '<a href="' . e(url('reappro')) . '" class="' . (!$f ? 'on' : '') . '" style="' . (!$f ? 'background:#fff;box-shadow:0 1px 3px rgba(19,28,24,.12);' : '') . 'display:inline-flex;align-items:center;padding:0 14px;min-height:40px;border-radius:8px;font-weight:600;font-size:14px;color:var(--ink);text-decoration:none">Tous les fournisseurs</a>';
foreach ($fournisseurs as $fo) $seg .= '<a href="' . e(url('reappro', ['f' => $fo['id']])) . '" style="' . ($f === (int) $fo['id'] ? 'background:#fff;box-shadow:0 1px 3px rgba(19,28,24,.12);' : '') . 'display:inline-flex;align-items:center;padding:0 14px;min-height:40px;border-radius:8px;font-weight:600;font-size:14px;color:var(--ink);text-decoration:none">' . e($fo['code']) . '</a>';
$seg .= '</div>';
$voirPrix = Auth::can('prix_achat');
?>
<?= page_header($u['role'] === 'proprietaire' ? 'Réapprovisionnement' : 'Besoins à commander', 'Suggestions calculées sur les ventes des 30 derniers jours' . ($site ? ' de votre officine' : ''), $seg) ?>
<main class="page">
  <?= flash_html() ?>
  <div class="banner info"><span><strong>Quantité suggérée</strong> = ventes par jour × (délai du fournisseur + <?= Reappro::RESERVE_JOURS ?> jours de réserve) − stock <?= $site ? 'de l\'officine' : 'des officines et du magasin' ?> − quantités déjà en commande, et au moins de quoi remonter au seuil d'alerte. Corrigez chaque quantité avant de créer les bons.<?= $u['role'] !== 'proprietaire' ? ' Vos bons sont proposés au propriétaire, qui les valide.' : '' ?></span></div>
  <form method="post" action="<?= e(url('reappro/creer')) ?>" id="reappro" class="stack">
    <?= csrf_field() ?>
    <section class="card" style="overflow:hidden">
      <div class="scroll-x"><table class="table">
        <thead><tr><th><input type="checkbox" class="chk" checked data-all aria-label="Tout sélectionner"></th><th>Produit</th><th class="ra"><?= $site ? 'Stock' : 'Stock ' . e(implode(' · ', array_map(fn($s) => mb_substr($s['nom_court'], 0, 4) . '.', sites()))) ?></th><th class="ra">Ventes/j</th><th>Couverture</th><th class="ra">En cde</th><th class="ra">À commander</th><?php if ($voirPrix): ?><th class="ra">Montant</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $p = $r['p']; $cov = $r['couverture']; ?>
          <tr data-prix="<?= (int) $r['prix'] ?>" data-f="<?= e($p['f_code'] ?? '?') ?>">
            <td><input type="checkbox" name="sel[]" value="<?= (int) $p['id'] ?>" class="chk" checked aria-label="Inclure <?= e($p['designation']) ?>" <?= $p['fournisseur_id'] ? '' : 'disabled' ?>></td>
            <td><span style="display:flex;flex-direction:column"><a href="<?= e(url('produit', ['id' => $p['id']])) ?>" style="font-weight:600;color:var(--ink)"><?= e($p['designation']) ?></a>
              <span class="small muted"><?= $p['f_code'] ? 'Fournisseur ' . e($p['f_code']) . ' · délai ' . (int) $r['delai'] . ' j' : '<span style="color:var(--bad-ink)">Pas de fournisseur habituel</span>' ?><?= $voirPrix && $r['prix'] ? ' · achat ' . fmt($r['prix']) . ' F' : '' ?></span></span></td>
            <td class="num ra"><?= $site ? fmt($r['stock']) : e(implode(' · ', array_map(fn($sid) => fmt($r['stock_sites'][$sid] ?? 0), array_keys(sites())))) ?></td>
            <td class="num ra"><?= number_format($r['vj'], 1, ',', '') ?></td>
            <td><?= $r['stock'] <= 0 ? pill('Rupture', 'bad') : ($cov === null ? pill('Sous le seuil', 'info') : pill($cov . ' jours', $cov < $r['delai'] + 3 ? 'warn' : 'info')) ?></td>
            <td class="num ra"><?= $r['encmd'] ?: '—' ?></td>
            <td class="ra"><label class="sr" for="q<?= (int) $p['id'] ?>">Quantité à commander</label><input id="q<?= (int) $p['id'] ?>" name="qte[<?= (int) $p['id'] ?>]" type="number" min="0" value="<?= (int) $r['qte'] ?>" class="inp num" style="width:90px;text-align:right"></td>
            <?php if ($voirPrix): ?><td class="num ra" data-mt><?= fmt($r['qte'] * $r['prix']) ?></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="empty">Aucun produit à recommander : le stock couvre les besoins.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </section>
    <?php if ($rows): ?>
      <div class="card card-b between" style="position:sticky;bottom:12px;box-shadow:var(--sh-2)">
        <span style="display:flex;flex-direction:column"><span style="font-weight:600" id="selcount"></span><span class="small muted" id="supsum"></span></span>
        <span class="flex">
          <?php if ($u['role'] === 'proprietaire'): ?>
            <label class="small" for="dest">Livraison à</label>
            <select id="dest" name="dest" class="inp" style="width:auto"><?php foreach (sites() as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $s['type'] === 'magasin' ? 'selected' : '' ?>><?= e($s['nom']) ?></option><?php endforeach; ?></select>
          <?php endif; ?>
          <?php if ($voirPrix): ?><span style="display:flex;flex-direction:column;text-align:right"><span class="small muted">Total estimé</span><span class="num tot"><span id="grand">0</span> FCFA</span></span><?php endif; ?>
          <button class="btn pri lg" id="cta">Créer le bon de commande</button>
        </span>
      </div>
    <?php endif; ?>
  </form>
</main>
<script>
(function(){const f=document.getElementById('reappro');if(!f)return;const fm=n=>String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g,' ');
function upd(){const by={};let n=0,g=0;f.querySelectorAll('tbody tr[data-prix]').forEach(tr=>{const c=tr.querySelector('input[type=checkbox]'),q=parseInt(tr.querySelector('input[type=number]').value,10)||0,p=parseInt(tr.dataset.prix,10),mt=tr.querySelector('[data-mt]');tr.querySelector('input[type=number]').disabled=!c.checked;tr.style.background=c.checked?'':'var(--sunk)';if(mt)mt.textContent=c.checked?fm(q*p):'—';if(c.checked&&q>0&&!c.disabled){n++;g+=q*p;const k=tr.dataset.f;by[k]=by[k]||{n:0,t:0};by[k].n++;by[k].t+=q*p;}});
document.getElementById('selcount').textContent=n+(n>1?' produits sélectionnés':' produit sélectionné');const ks=Object.keys(by);document.getElementById('supsum').textContent=ks.map(k=>k+' : '+by[k].n+' produit(s)').join('  ·  ')||'Aucun produit';const gr=document.getElementById('grand');if(gr)gr.textContent=fm(g);const cta=document.getElementById('cta');cta.textContent=ks.length>1?'Créer '+ks.length+' bons de commande':'Créer le bon de commande';cta.disabled=n===0;}
f.addEventListener('input',upd);f.addEventListener('change',upd);const all=f.querySelector('[data-all]');all.addEventListener('change',()=>{f.querySelectorAll('tbody input[type=checkbox]:not(:disabled)').forEach(c=>c.checked=all.checked);upd();});upd();})();
</script>
