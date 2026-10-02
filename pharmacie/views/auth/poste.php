<div>
  <h1 class="h1" style="font-size:28px;line-height:36px">Enregistrer ce poste</h1>
  <p class="muted" style="margin-top:4px">Le propriétaire génère un code à 6 chiffres dans <b>Sécurité › Enregistrer un poste de caisse</b>. Le code est valable 10 minutes et une seule fois.</p>
</div>
<?php if ($err): ?><div class="banner bad" role="alert"><?= e($err) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('poste')) ?>" class="stack">
  <?= csrf_field() ?>
  <div><label class="lbl" for="code">Code d'enregistrement</label><input id="code" name="code" class="inp num otp" inputmode="numeric" maxlength="7" placeholder="000 000" required></div>
  <div><label class="lbl" for="nom">Nom du poste</label><input id="nom" name="nom" class="inp" placeholder="Ex. Caisse 1 · comptoir principal" required></div>
  <button class="btn pri xl block" type="submit">Enregistrer ce poste</button>
  <a class="btn ghost" href="<?= e(url('login')) ?>">Retour à la connexion</a>
</form>
