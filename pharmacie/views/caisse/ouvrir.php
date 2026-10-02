<div>
  <h1 class="h1" style="font-size:28px;line-height:36px">Bonjour <?= e(prenom($u['nom'])) ?></h1>
  <p class="muted" style="margin-top:4px">
    <?php if ($derniere && $derniere['statut'] !== 'ouverte' && $derniere['cloturee_at'] && substr($derniere['cloturee_at'], 0, 10) === today()): ?>
      Votre caisse est clôturée depuis <?= e(date('H:i', strtotime($derniere['cloturee_at']))) ?>. Pour vendre à nouveau, ouvrez une nouvelle session avec un fond de caisse.
    <?php else: ?>
      Comptez le fond de caisse (la monnaie déjà dans le tiroir) puis ouvrez votre session.
    <?php endif; ?>
  </p>
</div>
<form method="post" action="<?= e(url('caisse/ouvrir')) ?>" class="stack">
  <?= csrf_field() ?>
  <?php if ($u['role'] === 'proprietaire'): ?>
    <fieldset style="border:none;padding:0;margin:0" class="stack-s">
      <legend class="lbl">Officine</legend>
      <?php foreach (officines() as $s): ?>
        <label class="opt-card"><input type="radio" name="site" value="<?= (int) $s['id'] ?>" required class="chk"> <span class="site <?= e($s['couleur']) ?>"><?= e($s['nom']) ?></span></label>
      <?php endforeach; ?>
    </fieldset>
  <?php endif; ?>
  <div>
    <label class="lbl" for="fond">Fond de caisse <span class="opt">(FCFA)</span></label>
    <input id="fond" name="fond" class="inp num" inputmode="numeric" value="0" style="min-height:56px;font-size:22px">
  </div>
  <button class="btn pri xl block" type="submit">Ouvrir la caisse</button>
  <?php if ($u['role'] !== 'vendeur'): ?><a class="btn ghost" href="index.php">Retour à mon espace</a><?php endif; ?>
</form>
<form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn ghost block" type="submit">Se déconnecter</button></form>
