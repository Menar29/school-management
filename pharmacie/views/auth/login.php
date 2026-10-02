<?php $poste = Auth::poste(); $ps = site($poste['site_id'] ?? null); ?>
<div>
  <h1 class="h1" style="font-size:28px;line-height:36px">Connexion</h1>
  <p class="muted" style="margin-top:4px"><?= $poste && $ps && $poste['type'] === 'caisse' ? 'Seuls les comptes de ' . e($ps['type'] === 'officine' ? 'l\'officine ' . $ps['nom_court'] : $ps['nom']) . ' peuvent ouvrir ce poste.' : 'Saisissez votre identifiant et votre mot de passe.' ?></p>
</div>
<?php if ($err): ?><div class="banner <?= e($err[0]) ?>" role="alert"><span><?= e($err[1]) ?></span></div><?php endif; ?>
<form method="post" action="<?= e(url('login')) ?>" class="stack" autocomplete="on">
  <?= csrf_field() ?>
  <div>
    <label class="lbl" for="login">Identifiant</label>
    <input id="login" name="login" class="inp" value="<?= e($login) ?>" autocomplete="username" autocapitalize="off" spellcheck="false" placeholder="Ex. aicha.remede" style="min-height:52px;font-size:17px" required autofocus>
  </div>
  <div>
    <label class="lbl" for="pw">Mot de passe</label>
    <div style="position:relative">
      <input id="pw" name="password" class="inp" type="password" autocomplete="current-password" style="min-height:52px;font-size:17px;padding-right:110px" required data-caps>
      <button type="button" class="btn ghost" data-toggle-pw="pw" aria-pressed="false" style="position:absolute;right:4px;top:4px;min-height:44px;padding:0 12px;font-size:14px">Afficher</button>
    </div>
    <p class="warn-msg" data-caps-msg hidden>Majuscules activées.</p>
  </div>
  <button class="btn pri xl block" type="submit">Se connecter</button>
  <p class="small muted" style="text-align:center">Mot de passe oublié ? Le propriétaire le réinitialise depuis son espace.</p>
  <?php if (!$poste): ?><p class="small" style="text-align:center"><a href="<?= e(url('poste')) ?>">Enregistrer ce poste comme caisse</a></p><?php endif; ?>
</form>
