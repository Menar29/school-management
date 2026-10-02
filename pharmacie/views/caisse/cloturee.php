<div>
  <h1 class="h1" style="font-size:28px;line-height:36px">Caisse clôturée</h1>
  <?php if ($last && $last['cloturee_at']): ?>
    <p class="muted" style="margin-top:4px">Votre caisse est clôturée depuis <?= e(quand($last['cloturee_at'])) ?>. Espèces comptées : <strong><?= fmt($last['compte']) ?> FCFA</strong><?= (int) $last['ecart'] !== 0 ? ' · écart ' . e(fmt_signe((int) $last['ecart'])) : '' ?>.</p>
  <?php endif; ?>
</div>
<?php if ($precision): ?>
  <div class="banner warn"><span><strong>Le propriétaire demande des précisions :</strong> <?= e($precision['remarque_admin']) ?></span></div>
  <form method="post" action="<?= e(url('caisse/precision')) ?>" class="stack"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $precision['id'] ?>">
    <textarea class="inp" name="explication" placeholder="Votre réponse" required></textarea><button class="btn">Envoyer la réponse</button></form>
<?php endif; ?>
<a class="btn pri xl block" href="<?= e(url('caisse')) ?>">Ouvrir une nouvelle session</a>
<form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn ghost block" type="submit">Se déconnecter</button></form>
