<?php
$seg = '<div class="seg" role="group" aria-label="Site">';
foreach (['all' => 'Tous'] + array_map(fn($s) => $s['nom_court'], sites()) as $k => $l) {
    $on = (string) $f === (string) $k;
    $seg .= '<a href="' . e(url('utilisateurs', ['site' => $k])) . '" style="display:inline-flex;align-items:center;padding:0 14px;min-height:40px;border-radius:8px;font-weight:600;font-size:14px;color:var(--ink);text-decoration:none;' . ($on ? 'background:#fff;box-shadow:0 1px 3px rgba(19,28,24,.12)' : '') . '">' . e($l) . '</a>';
}
$seg .= '</div>';
?>
<?= page_header('Utilisateurs', 'Chaque personne a son identifiant et son mot de passe, et n\'accède qu\'à son site.', '<button class="btn pri" data-dialog="dlg-new">' . icon('plus') . 'Ajouter un utilisateur</button>') ?>
<main class="page">
  <?= flash_html() ?>
  <div class="between"><?= $seg ?><a href="<?= e(url('droits')) ?>" class="small" style="font-weight:600">Voir ce que chaque rôle peut faire</a></div>
  <section class="card" style="overflow:hidden">
    <div class="scroll-x"><table class="table">
      <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Rattachement</th><th>Dernière connexion</th><th>Statut</th><th><span class="sr">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $locked = $r['locked_until'] && strtotime($r['locked_until']) > time(); $av = $r['role'] === 'magasinier' ? 'o' : (in_array($r['role'], ['gerant', 'proprietaire'], true) ? 'b' : ''); ?>
        <tr class="hov" style="<?= $r['actif'] ? '' : 'opacity:.7' ?>">
          <td><span class="flex" style="flex-wrap:nowrap"><span class="av <?= $av ?>"><?= e(initiales($r['nom'])) ?></span><span style="display:flex;flex-direction:column"><span style="font-weight:600"><?= e($r['nom']) ?></span><span class="small muted num"><?= e($r['login']) ?><?= $r['telephone'] ? ' · ' . e($r['telephone']) : '' ?></span></span></span></td>
          <td><?= e(Perm::role($r['role'])) ?><?= $r['totp_enabled'] ? ' <span class="small muted">· 2 étapes</span>' : '' ?></td>
          <td><?= site_tag(site($r['site_id'] ? (int) $r['site_id'] : null)) ?></td>
          <td class="small"><?= e(quand($r['last_login_at'])) ?><?= $r['last_login_device'] ? '<br><span class="muted">' . e($r['last_login_device']) . '</span>' : '' ?></td>
          <td><?= !$r['actif'] ? pill('Désactivé', 'mute') : ($locked ? pill('Bloqué', 'bad') : ($r['must_change_pw'] ? pill('Nouveau', 'info') : pill('Actif', 'ok'))) ?></td>
          <td class="ra" style="white-space:nowrap">
            <?php if ($r['role'] !== 'proprietaire'): ?>
              <button class="btn ghost" data-dialog="dlg-edit" data-id="<?= (int) $r['id'] ?>" data-nom="<?= e($r['nom']) ?>" data-tel="<?= e($r['telephone']) ?>" data-role="<?= e($r['role']) ?>" data-site="<?= (int) $r['site_id'] ?>">Modifier</button>
              <form method="post" action="<?= e(url('utilisateur/reinitialiser')) ?>" class="inline" data-confirm="Générer un nouveau mot de passe provisoire pour <?= e(prenom($r['nom'])) ?> ? Ses sessions seront fermées."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><?php if ($r['totp_enabled']): ?><input type="hidden" name="totp" value="1"><?php endif; ?><button class="btn ghost">Réinitialiser le mot de passe</button></form>
              <form method="post" action="<?= e(url('utilisateur/activer')) ?>" class="inline" <?= $r['actif'] ? 'data-confirm="Désactiver ' . e(prenom($r['nom'])) . ' ? Toutes ses sessions seront fermées immédiatement."' : '' ?>><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn <?= $r['actif'] ? 'danger' : '' ?>"><?= $r['actif'] ? 'Désactiver' : 'Réactiver' ?></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <p class="small muted">Désactiver un utilisateur ferme immédiatement toutes ses sessions, sur tous les postes et téléphones. Son historique est conservé. Réinitialiser le mot de passe génère un mot de passe provisoire à changer à la connexion suivante.</p>
</main>

<dialog id="dlg-new" style="max-width:620px">
  <form method="post" action="<?= e(url('utilisateur/creer')) ?>" id="nu"><?= csrf_field() ?>
    <div class="dh"><h2 class="h2">Nouvel utilisateur</h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <div class="grid g2">
        <div><label class="lbl" for="nn">Prénom et nom</label><input id="nn" name="nom" class="inp" placeholder="Ex. Fatouma Issa" required></div>
        <div><label class="lbl" for="nt">Téléphone <span class="opt">(facultatif)</span></label><input id="nt" name="telephone" class="inp" placeholder="Ex. 90 00 00 00"></div>
      </div>
      <fieldset style="border:none;padding:0;margin:0" class="stack-s"><legend class="lbl">Rôle</legend>
        <?php foreach (['vendeur' => 'Vend, encaisse en espèces et clôture sa propre caisse.', 'gerant' => 'Responsable d\'une officine : ses caisses, son stock, ses réceptions, les nouveaux produits. Utilise aussi l\'application mobile.', 'magasinier' => 'Réceptionne les livraisons, fait les transferts et les inventaires. N\'encaisse pas.'] as $k => $d): ?>
          <label class="opt-card"><input type="radio" name="role" value="<?= $k ?>" class="chk" <?= $k === 'vendeur' ? 'checked' : '' ?>><span><strong><?= e(Perm::role($k)) ?></strong><br><span class="small muted"><?= e($d) ?></span></span></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset style="border:none;padding:0;margin:0"><legend class="lbl">Rattaché à</legend>
        <div class="chips"><?php foreach (sites() as $s): ?><label class="chip" data-type="<?= e($s['type']) ?>"><input type="radio" name="site" value="<?= (int) $s['id'] ?>" data-suffix="<?= e(mb_strtolower(preg_replace('/[^A-Za-z0-9]/', '', $s['code']))) ?>" class="chk" style="width:16px;height:16px"> <span class="site <?= e($s['couleur']) ?>"><?= e($s['nom']) ?></span></label><?php endforeach; ?></div>
      </fieldset>
      <div><label class="lbl" for="nl">Identifiant de connexion</label><input id="nl" name="login" class="inp num" autocapitalize="off" spellcheck="false" placeholder="Rempli automatiquement" required pattern="[a-z0-9.]{3,60}">
        <p class="hint" id="lgh">Proposé à partir du prénom et du site. Minuscules, chiffres et point uniquement.</p></div>
      <div class="banner info" id="mgr" hidden><span><strong>Vérification en deux étapes obligatoire.</strong> Un gérant accède à l'application depuis son téléphone : il la configurera à sa première connexion.</span></div>
      <p class="small muted">Un mot de passe provisoire sera affiché une seule fois après la création. À sa première connexion, la personne choisit son propre mot de passe et son code de déverrouillage de caisse.</p>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Créer l'utilisateur</button></div>
  </form>
</dialog>
<dialog id="dlg-edit">
  <form method="post" action="<?= e(url('utilisateur/modifier')) ?>"><?= csrf_field() ?><input type="hidden" name="id" data-fill="id">
    <div class="dh"><h2 class="h2">Modifier l'utilisateur</h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <div class="grid g2"><div><label class="lbl" for="en">Prénom et nom</label><input id="en" name="nom" class="inp" data-fill="nom"></div><div><label class="lbl" for="et">Téléphone</label><input id="et" name="telephone" class="inp" data-fill="tel"></div></div>
      <div class="grid g2">
        <div><label class="lbl" for="er">Rôle</label><select id="er" name="role" class="inp" data-fill="role"><option value="vendeur">Vendeur</option><option value="gerant">Gérant</option><option value="magasinier">Magasinier</option></select></div>
        <div><label class="lbl" for="es">Site</label><select id="es" name="site" class="inp" data-fill="site"><?php foreach (sites() as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['nom']) ?></option><?php endforeach; ?></select></div>
      </div>
      <p class="hint">Changer le rôle ou le site ferme les sessions ouvertes de la personne.</p>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Enregistrer</button></div>
  </form>
</dialog>
<script>
(function(){const f=document.getElementById('nu'),taken=<?= json_encode($logins) ?>,nl=document.getElementById('nl');let touched=false;
nl.addEventListener('input',()=>{touched=true;});
const norm=s=>s.normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/[^a-z0-9]/g,'');
function upd(){const role=f.querySelector('[name=role]:checked').value;
  f.querySelectorAll('[data-type]').forEach(l=>{const dis=role!=='magasinier'&&l.dataset.type==='magasin';const i=l.querySelector('input');i.disabled=dis;l.style.opacity=dis?.45:1;if(dis&&i.checked)i.checked=false;});
  document.getElementById('mgr').hidden=role!=='gerant';
  const s=f.querySelector('[name=site]:checked'),n=document.getElementById('nn').value.trim();
  if(!touched&&n&&s){let base=norm(n.split(/\s+/)[0])+'.'+s.dataset.suffix,l=base,i=2;while(taken.includes(l))l=base+(i++);nl.value=l;}
  const h=document.getElementById('lgh');if(taken.includes(nl.value)){h.className='err-msg';h.textContent='Cet identifiant est déjà pris.';}else{h.className='hint';h.textContent='Proposé à partir du prénom et du site. Minuscules, chiffres et point uniquement.';}}
f.addEventListener('input',upd);f.addEventListener('change',upd);upd();})();
</script>
