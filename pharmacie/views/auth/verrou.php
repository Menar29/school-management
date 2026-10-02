<div style="display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center">
  <span class="av" style="width:64px;height:64px;font-size:20px"><?= e(initiales($u['nom'])) ?></span>
  <h1 class="h1" style="font-size:28px">Caisse verrouillée</h1>
  <p class="muted">Session de <?= e(prenom($u['nom'])) ?><?= $sess ? ' ouverte depuis ' . e(date('H:i', strtotime($sess['ouverte_at']))) : '' ?>. Verrouillée après <?= (int) $delai ?> minute<?= $delai > 1 ? 's' : '' ?> sans activité. Le ticket en cours est conservé.</p>
</div>
<?php if ($err): ?><div class="banner bad" role="alert"><?= e($err) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('verrou')) ?>" class="stack" data-pinpad>
  <?= csrf_field() ?>
  <input type="hidden" name="pin" value="">
  <div class="dots" role="img" aria-label="Code saisi"><span></span><span></span><span></span><span></span></div>
  <div class="pad">
    <?php foreach (['1','2','3','4','5','6','7','8','9'] as $d): ?><button type="button" class="key" data-key="<?= $d ?>"><?= $d ?></button><?php endforeach; ?>
    <button type="button" class="key fn" data-key="clear">Effacer</button>
    <button type="button" class="key" data-key="0">0</button>
    <button type="button" class="key fn" data-key="back" aria-label="Effacer le dernier chiffre">⌫</button>
  </div>
</form>
<form method="post" action="<?= e(url('logout')) ?>" style="text-align:center"><?= csrf_field() ?>
  <button class="btn ghost" type="submit">Ce n'est pas <?= e(prenom($u['nom'])) ?> ? Changer d'utilisateur</button>
  <p class="small muted">Changer d'utilisateur ferme la session de <?= e(prenom($u['nom'])) ?> : il faudra se reconnecter avec le mot de passe.</p>
</form>
