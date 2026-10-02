<div>
  <h1 class="h1" style="font-size:28px;line-height:36px">Vérification en deux étapes</h1>
  <p class="muted" style="margin-top:4px">Ouvrez votre application d'authentification et saisissez le code à 6 chiffres affiché pour « <?= e(config('pharmacie')) ?> ».</p>
</div>
<?php if ($err): ?><div class="banner bad" role="alert"><?= e($err) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('deux-etapes')) ?>" class="stack">
  <?= csrf_field() ?>
  <div>
    <label class="lbl" for="code">Code de vérification</label>
    <input id="code" name="code" class="inp num otp" inputmode="numeric" autocomplete="one-time-code" maxlength="9" placeholder="000000" required autofocus>
    <p class="hint">Téléphone perdu ? Saisissez un de vos codes de secours (format ABCD-1234).</p>
  </div>
  <button class="btn pri xl block" type="submit">Vérifier</button>
  <a class="btn ghost" href="<?= e(url('login')) ?>">Retour</a>
</form>
