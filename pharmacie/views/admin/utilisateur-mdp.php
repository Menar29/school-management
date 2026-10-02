<?= page_header($nouveau ? 'Utilisateur créé' : 'Mot de passe réinitialisé', '', '', '<a href="' . e(url('utilisateurs')) . '">Utilisateurs</a>') ?>
<main class="page">
  <div class="card card-b stack" style="max-width:560px">
    <h2 class="h2"><?= e($cible['nom']) ?></h2>
    <div class="dl">
      <div><span class="k">Identifiant</span><span class="num" style="font-weight:600"><?= e($cible['login']) ?></span></div>
      <div><span class="k">Rôle</span><span><?= e(Perm::role($cible['role'])) ?> · <?= e(site($cible['site_id'] ? (int) $cible['site_id'] : null)['nom'] ?? 'Tous les sites') ?></span></div>
    </div>
    <div><span class="lbl">Mot de passe provisoire</span><div class="code-big" style="font-size:28px;letter-spacing:.06em;background:var(--sunk);border-radius:12px;padding:16px"><?= e($pw) ?></div>
      <p class="hint">Affiché une seule fois. À remettre en main propre, pas par message. À sa première connexion, la personne choisira son propre mot de passe et son code de déverrouillage.</p></div>
    <div class="flex no-print"><button class="btn" onclick="window.print()"><?= icon('print') ?>Imprimer la fiche</button><a class="btn pri" href="<?= e(url('utilisateurs')) ?>">Terminé</a></div>
  </div>
</main>
