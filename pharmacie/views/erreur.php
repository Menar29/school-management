<?php if (Auth::user()): ?>
<header class="top"><h1 class="h1"><?= e($titre) ?></h1></header>
<main class="page"><div class="card card-b stack"><p><?= e($message) ?></p><p><a class="btn" href="index.php">Retour à l'accueil</a></p></div></main>
<?php else: ?>
<h1 class="h1"><?= e($titre) ?></h1><p class="muted"><?= e($message) ?></p><a class="btn pri" href="<?= e(url('login')) ?>">Se connecter</a>
<?php endif; ?>
