<?php $actifs = array_filter($postes, fn($p) => !$p['revoked_at']); $lock = (int) setting('verrou_minutes', '2'); ?>
<?= page_header('Sécurité et connexions', 'Qui s\'est connecté, depuis quel appareil, et les règles d\'accès', '<button class="btn pri" data-dialog="dlg-code">' . icon('pc') . 'Enregistrer un poste de caisse</button>') ?>
<main class="page">
  <?= flash_html() ?>
  <?php if ($code): ?>
    <div class="card card-b stack" style="border-color:var(--brand);max-width:640px">
      <span class="h2">Code d'enregistrement · <?= e($code['site']) ?></span>
      <p class="muted">Sur le nouvel ordinateur, ouvrez l'application, choisissez « Enregistrer ce poste comme caisse » sur l'écran de connexion et saisissez ce code :</p>
      <div class="code-big"><?= e(substr($code['code'], 0, 3) . ' ' . substr($code['code'], 3)) ?></div>
      <p class="small muted">Valable jusqu'à <?= e(date('H:i', strtotime($code['exp']))) ?>, utilisable une seule fois. Seuls les comptes de ce site pourront ouvrir une session sur ce poste.</p>
    </div>
  <?php endif; ?>
  <div class="grid g4">
    <div class="card kpi"><span class="l">Connexions refusées · 24 h</span><span class="v"><?= (int) $refus['n'] ?></span><span class="small muted">dont <?= (int) $refus['inex'] ?> sur un identifiant inexistant</span></div>
    <div class="card kpi"><span class="l">Comptes bloqués</span><span class="v" style="color:<?= $bloques ? 'var(--bad-ink)' : 'var(--ink)' ?>"><?= count($bloques) ?></span><span class="small muted"><?= $bloques ? e(implode(', ', array_column($bloques, 'login'))) : 'aucun compte bloqué' ?></span></div>
    <div class="card kpi"><span class="l">Appareils autorisés</span><span class="v"><?= count($actifs) ?></span><span class="small muted"><?= count(array_filter($actifs, fn($p) => $p['type'] === 'caisse')) ?> postes de caisse · <?= count(array_filter($actifs, fn($p) => $p['type'] !== 'caisse')) ?> téléphones et PC</span></div>
    <div class="card kpi"><span class="l">Double vérification</span><span class="v"><?= (int) $deux['actives'] ?> / <?= (int) $deux['total'] ?></span><span class="small muted">propriétaire et gérants · <?= setting('deux_etapes', '1') === '1' ? 'obligatoire' : '<span style="color:var(--bad-ink)">désactivée</span>' ?></span></div>
  </div>
  <?php foreach ($bloques as $b): ?>
    <form method="post" action="<?= e(url('securite/debloquer')) ?>" class="banner bad"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
      <span style="flex:1"><strong><?= e($b['login']) ?></strong> est bloqué jusqu'à <?= e(date('H:i', strtotime($b['locked_until']))) ?> après <?= Auth::MAX_ESSAIS ?> erreurs de mot de passe.</span><button class="btn">Débloquer</button></form>
  <?php endforeach; ?>
  <div class="row">
    <div class="grow stack">
      <section class="card" style="overflow:hidden">
        <div class="card-h"><span class="h2">Appareils autorisés</span><span class="small muted">Un appareil retiré est déconnecté immédiatement</span></div>
        <div class="scroll-x"><table class="table">
          <thead><tr><th>Appareil</th><th>Utilisé par</th><th>Dernière activité</th><th><span class="sr">Action</span></th></tr></thead>
          <?php foreach ($postes as $p): $gone = (bool) $p['revoked_at']; ?>
            <tr style="<?= $gone ? 'opacity:.55' : '' ?>"><td><span class="flex" style="flex-wrap:nowrap"><?= icon($p['type'] === 'caisse' ? 'pc' : (str_contains($p['nom'], 'Téléphone') || str_contains($p['nom'], 'iPhone') ? 'phone' : 'pc')) ?><span style="display:flex;flex-direction:column"><span style="font-weight:600"><?= e($p['nom']) ?></span><span class="small"><?= $p['site_id'] ? site_tag(site((int) $p['site_id'])) : '<span class="muted">Tous les sites</span>' ?><?= $p['type'] === 'caisse' ? ' · poste de caisse' : '' ?></span></span></span></td>
              <td><?= e($p['last_user'] ?? '—') ?></td><td><?= $gone ? 'Retiré ' . e(quand($p['revoked_at'])) : e(quand($p['last_seen_at'])) ?></td>
              <td class="ra"><?php if (!$gone): ?><form method="post" action="<?= e(url('securite/retirer')) ?>" class="inline" data-confirm="Retirer « <?= e($p['nom']) ?> » ? Il sera déconnecté immédiatement."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn danger">Retirer</button></form><?php else: ?><?= pill('Retiré', 'mute') ?><?php endif; ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$postes): ?><tr><td colspan="4" class="empty">Aucun appareil enregistré.</td></tr><?php endif; ?>
        </table></div>
      </section>
      <section class="card" style="overflow:hidden">
        <div class="card-h"><span class="h2">Journal des connexions</span><span class="small muted">Non modifiable</span></div>
        <div class="scroll-x" style="max-height:480px;overflow:auto"><table class="table">
          <thead><tr><th>Heure</th><th>Identifiant</th><th>Appareil · adresse</th><th>Résultat</th></tr></thead>
          <?php foreach ($log as $l): $r = RESULTATS[$l['resultat']] ?? [$l['resultat'], 'mute']; ?>
            <tr style="<?= $r[1] === 'bad' ? 'background:#FFFBFA' : '' ?>"><td class="num"><?= e(quand($l['at'])) ?></td><td class="num"><?= e($l['login']) ?></td><td class="small"><?= e($l['appareil']) ?> · <?= e($l['ip']) ?></td><td><?= pill($r[0], $r[1]) ?></td></tr>
          <?php endforeach; ?>
        </table></div>
      </section>
    </div>
    <aside class="aside">
      <form method="post" action="<?= e(url('securite/regles')) ?>" class="card"><?= csrf_field() ?>
        <div class="card-h"><span class="h2">Règles d'accès</span></div>
        <div class="card-b stack">
          <div><span class="lbl">Verrouillage de la caisse après</span>
            <div class="seg" role="radiogroup"><?php foreach ([1, 2, 5] as $m): ?><label style="display:inline-flex;align-items:center;gap:6px;padding:0 14px;min-height:40px;border-radius:8px;font-weight:600;font-size:14px;cursor:pointer;<?= $lock === $m ? 'background:#fff;box-shadow:0 1px 3px rgba(19,28,24,.12)' : '' ?>"><input type="radio" name="verrou" value="<?= $m ?>" <?= $lock === $m ? 'checked' : '' ?> style="accent-color:var(--brand)"> <?= $m ?> min</label><?php endforeach; ?></div></div>
          <label class="flex" style="align-items:flex-start;flex-wrap:nowrap"><input type="checkbox" name="deux_etapes" value="1" class="chk" <?= setting('deux_etapes', '1') === '1' ? 'checked' : '' ?>><span><strong>Double vérification obligatoire</strong><br><span class="small muted">Propriétaire et gérants : code à 6 chiffres sur le téléphone.</span></span></label>
          <label class="flex" style="align-items:flex-start;flex-wrap:nowrap"><input type="checkbox" name="postes" value="1" class="chk" <?= setting('postes_obligatoires', '1') === '1' ? 'checked' : '' ?>><span><strong>Postes de caisse enregistrés</strong><br><span class="small muted">Les vendeurs et magasiniers ne se connectent que sur un poste enregistré de leur site.</span></span></label>
          <div class="dl small">
            <div><span class="k">Mot de passe</span><span>8 caractères min. · 12 pour le propriétaire</span></div>
            <div><span class="k">Essais avant blocage</span><span><?= Auth::MAX_ESSAIS ?>, puis <?= Auth::BLOCAGE_MIN ?> minutes</span></div>
            <div><span class="k">Espace propriétaire</span><span>Déconnexion après 30 min d'inactivité</span></div>
          </div>
          <button class="btn pri">Enregistrer les règles</button>
        </div>
      </form>
      <section class="card">
        <div class="card-h"><span class="h2">Sauvegardes</span></div>
        <div class="card-b stack-s small">
          <p>La base de données est sauvegardée chaque nuit par le script <code>scripts/sauvegarde.sh</code> (voir README) : copie compressée, 30 jours conservés.</p>
          <p class="muted">Copiez régulièrement le dossier des sauvegardes hors du serveur (clé USB, cloud) et testez une restauration une fois par mois.</p>
        </div>
      </section>
      <p class="small muted">Toute action sur cet écran est elle-même enregistrée dans le journal d'activité, avec votre nom et l'heure.</p>
    </aside>
  </div>
</main>
<dialog id="dlg-code">
  <form method="post" action="<?= e(url('securite/code')) ?>"><?= csrf_field() ?>
    <div class="dh"><h2 class="h2">Enregistrer un poste de caisse</h2><button class="ibtn" type="button" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
    <div class="db">
      <p class="muted">Un code à 6 chiffres sera généré. Ce poste sera rattaché à :</p>
      <div class="stack-s"><?php foreach (sites() as $s): ?><label class="opt-card"><input type="radio" name="site" value="<?= (int) $s['id'] ?>" class="chk" required> <span class="site <?= e($s['couleur']) ?>"><?= e($s['nom']) ?></span></label><?php endforeach; ?></div>
      <p class="hint">Code valable 10 minutes, utilisable une seule fois.</p>
    </div>
    <div class="df"><button class="btn" type="button" data-close>Annuler</button><button class="btn pri">Générer le code</button></div>
  </form>
</dialog>
