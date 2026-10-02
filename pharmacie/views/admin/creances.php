<?= page_header('Créances clients', 'Seuls les clients que vous autorisez peuvent acheter à crédit, dans la limite de leur plafond.',
    site_seg('creances', $site) . (Auth::can('creances.gerer') ? '<button class="btn pri" data-dialog="dlg-client" data-id="" data-nom="" data-plafond="20000">' . icon('plus') . 'Autoriser un client</button>' : '')) ?>
<main class="page">
  <?= flash_html() ?>
  <div class="grid g3">
    <div class="card kpi"><span class="l">Total dû par les clients</span><span class="v"><?= fmt($total) ?><small>FCFA</small></span><span class="small muted"><?= count(array_filter($clients, fn($c) => $c['du'] > 0)) ?> client(s)</span></div>
    <div class="card kpi"><span class="l">En retard de plus de 30 jours</span><span class="v" style="color:var(--bad-ink)"><?= fmt(array_sum(array_column($retard, 'du'))) ?><small>FCFA</small></span><span class="small muted"><?= count($retard) ?> client(s)</span></div>
    <div class="card kpi"><span class="l">Remboursé ce mois</span><span class="v"><?= fmt($mois) ?><small>FCFA</small></span><span class="small muted">en espèces</span></div>
  </div>
  <section class="card" style="overflow:hidden" aria-label="Clients à crédit">
    <div class="scroll-x"><table class="table">
      <thead><tr><th>Client</th><th>Officine</th><th class="ra">Dû</th><th>Plafond utilisé</th><th>Ancienneté</th><th><span class="sr">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($clients as $c): $pct = $c['plafond'] ? min(100, (int) round($c['du'] / $c['plafond'] * 100)) : 0; ?>
        <tr class="hov">
          <td><span style="display:flex;flex-direction:column"><span style="font-weight:600"><?= e($c['nom']) ?><?= $c['autorise'] ? '' : ' ' . pill('Crédit suspendu', 'mute') ?></span><span class="small muted"><?= e($c['type']) ?><?= $c['telephone'] ? ' · ' . e($c['telephone']) : '' ?></span></span></td>
          <td><?= site_tag(site((int) $c['site_id'])) ?></td>
          <td class="num ra" style="font-weight:600"><?= fmt($c['du']) ?></td>
          <td style="min-width:180px"><span class="flex" style="flex-wrap:nowrap"><span class="bar" style="flex:1"><span style="width:<?= $pct ?>%;background:<?= $pct >= 70 ? '#B26A00' : 'var(--brand)' ?>"></span></span><span class="num small muted"><?= $pct ?> % de <?= fmt($c['plafond']) ?></span></span></td>
          <td><?= $c['age'] !== null ? pill($c['age'] . ' jours', $c['age'] > 30 ? 'bad' : 'mute') : '<span class="muted">—</span>' ?></td>
          <td class="ra" style="white-space:nowrap">
            <a class="btn ghost" href="<?= e(url('creances/historique', ['id' => $c['id']])) ?>">Historique</a>
            <?php if (Auth::can('creances.gerer')): ?>
              <button class="btn ghost" data-dialog="dlg-client" data-id="<?= (int) $c['id'] ?>" data-nom="<?= e($c['nom']) ?>" data-type="<?= e($c['type']) ?>" data-tel="<?= e($c['telephone']) ?>" data-site="<?= (int) $c['site_id'] ?>" data-plafond="<?= (int) $c['plafond'] ?>" data-autorise="<?= (int) $c['autorise'] ?>">Modifier</button>
              <?php if ($c['du'] > 0): ?><button class="btn" data-dialog="dlg-pay" data-id="<?= (int) $c['id'] ?>" data-titre="Encaisser · <?= e($c['nom']) ?>" data-montant="<?= (int) $c['du'] ?>">Encaisser</button><?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clients): ?><tr><td colspan="6" class="empty">Aucun client autorisé à crédit.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
</main>

<dialog id="dlg-client">
  <form method="post" action="<?= e(url('creances/client')) ?>"><?= csrf_field() ?><input type="hidden" name="id" data-fill="id">
    <div class="dh"><h2 class="h2">Client autorisé à crédit</h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <div><label class="lbl" for="c-n">Nom</label><input id="c-n" name="nom" class="inp" data-fill="nom" required></div>
      <div class="grid g2">
        <div><label class="lbl" for="c-t">Type</label><select id="c-t" name="type" class="inp" data-fill="type"><option>Particulier</option><option>Professionnel</option></select></div>
        <div><label class="lbl" for="c-tel">Téléphone</label><input id="c-tel" name="telephone" class="inp" data-fill="tel"></div>
      </div>
      <div class="grid g2">
        <div><label class="lbl" for="c-s">Officine</label><select id="c-s" name="site_id" class="inp" data-fill="site"><?php foreach (officines() as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['nom']) ?></option><?php endforeach; ?></select></div>
        <div><label class="lbl" for="c-p">Plafond (FCFA)</label><input id="c-p" name="plafond" class="inp num" inputmode="numeric" data-fill="plafond" required></div>
      </div>
      <label class="flex"><input type="checkbox" name="autorise" value="1" class="chk" checked data-check="autorise"> Autorisé à acheter à crédit</label>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Enregistrer</button></div>
  </form>
</dialog>
<dialog id="dlg-pay">
  <form method="post" action="<?= e(url('creances/encaisser')) ?>"><?= csrf_field() ?><input type="hidden" name="id" data-fill="id">
    <div class="dh"><h2 class="h2" data-fill="titre">Encaisser</h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <div><label class="lbl" for="p-m">Montant reçu en espèces (FCFA)</label><input id="p-m" name="montant" class="inp num" inputmode="numeric" data-fill="montant" required></div>
      <div><label class="lbl" for="p-n">Note <span class="opt">(facultatif)</span></label><input id="p-n" name="note" class="inp" placeholder="Ex. reçu n°…"></div>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Encaisser</button></div>
  </form>
</dialog>
<script>document.querySelectorAll('[data-dialog=dlg-client]').forEach(b=>b.addEventListener('click',()=>{const c=document.querySelector('[data-check=autorise]');c.checked=b.dataset.autorise!=='0';}));</script>
