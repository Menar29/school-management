<?= page_header('Produit ajouté au catalogue') ?>
<main class="page">
  <div class="card card-b stack" style="max-width:640px">
    <span class="av" style="width:56px;height:56px;background:var(--ok-soft)"><?= icon('check') ?></span>
    <h2 class="h1"><?= e($nom) ?></h2>
    <p class="muted"><?= $u['role'] === 'magasinier' ? 'Il est visible dans tous les sites, sans prix : le propriétaire doit fixer son prix pour qu\'il soit vendable.' : 'Il est visible dans tous les sites. Il apparaîtra en caisse dès que du stock sera reçu, avec son lot et sa péremption.' ?></p>
    <div class="flex"><a class="btn pri" href="<?= e(url('receptions')) ?>">Réceptionner une livraison</a><a class="btn" href="<?= e(url('produit/nouveau')) ?>">Créer un autre produit</a><a class="btn ghost" href="<?= e(url('produit', ['id' => $id])) ?>">Ouvrir la fiche</a></div>
  </div>
</main>
