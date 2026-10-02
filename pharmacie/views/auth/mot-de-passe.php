<div>
  <h1 class="h1" style="font-size:28px;line-height:36px">Bienvenue <?= e(prenom($u['nom'])) ?></h1>
  <p class="muted" style="margin-top:4px">Première connexion : remplacez le mot de passe provisoire par le vôtre. Personne d'autre ne le connaîtra, pas même le propriétaire.</p>
</div>
<?php if ($errors): ?><div class="banner bad" role="alert"><span><?= e(implode(' · ', array_unique($errors))) ?></span></div><?php endif; ?>
<form method="post" action="<?= e(url('mot-de-passe')) ?>" class="stack" data-pw-rules="<?= $u['role'] === 'proprietaire' ? 12 : 8 ?>">
  <?= csrf_field() ?>
  <div>
    <label class="lbl" for="n1">Nouveau mot de passe</label>
    <input id="n1" name="n1" class="inp" type="password" autocomplete="new-password" style="min-height:52px;font-size:17px" required>
    <ul class="rules stack-s" style="margin-top:8px;gap:4px">
      <?php foreach (Auth::checkPassword('', $u['role']) as $rule): ?>
        <li><?= icon('check', 'ic') ?><?= e($rule['t']) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div>
    <label class="lbl" for="n2">Confirmez le mot de passe</label>
    <input id="n2" name="n2" class="inp" type="password" autocomplete="new-password" style="min-height:52px;font-size:17px" required>
  </div>
  <div>
    <label class="lbl" for="pin">Code de déverrouillage <span class="opt">(4 chiffres)</span></label>
    <input id="pin" name="pin" class="inp num" type="password" inputmode="numeric" maxlength="4" pattern="\d{4}" style="min-height:52px;font-size:22px;letter-spacing:.4em;width:180px" required>
    <p class="hint">Sert à rouvrir votre session quand la caisse se verrouille<?= in_array($u['role'], ['gerant', 'proprietaire'], true) ? ', et à autoriser une remise ou une annulation à la caisse' : '' ?>.</p>
  </div>
  <button class="btn pri xl block" type="submit">Enregistrer et continuer</button>
</form>
