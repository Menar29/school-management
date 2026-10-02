<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Caisse · <?= e($site['nom']) ?></title>
<meta name="theme-color" content="#0F1D18">
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="assets/icon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body>
<div class="pos" id="pos">
  <header class="pos-h">
    <span class="logo"><?= icon('cross') ?></span>
    <span style="display:flex;flex-direction:column;line-height:1.25">
      <span style="color:#fff;font-weight:600">Officine <?= e($site['nom_court']) ?></span>
      <span class="small"><?= e($sess['poste'] ?: 'Caisse') ?> · session ouverte à <?= e(date('H:i', strtotime($sess['ouverte_at']))) ?></span>
    </span>
    <span style="flex:1"></span>
    <span class="net" id="net">Synchronisé</span>
    <a class="btn ghost" style="color:#fff" href="<?= e(url('caisse/ventes')) ?>">Mes ventes et clôture</a>
    <?php if ($u['role'] !== 'vendeur'): ?><a class="btn ghost hide-m" style="color:#fff" href="index.php">Mon espace</a><?php endif; ?>
    <span class="flex hide-m">
      <span class="av"><?= e(initiales($u['nom'])) ?></span>
      <span style="display:flex;flex-direction:column;line-height:1.2"><span style="color:#fff;font-weight:600"><?= e(prenom($u['nom'])) ?></span>
        <form method="post" action="<?= e(url('logout')) ?>" class="inline"><?= csrf_field() ?><button style="background:none;border:none;padding:0;color:#9FD9BF;font-size:13px;cursor:pointer">Changer d'utilisateur</button></form>
      </span>
    </span>
  </header>
  <?php if ($precision): ?>
    <div class="banner warn" style="border-radius:0">
      <span style="flex:1"><strong>Le propriétaire demande des précisions sur votre clôture du <?= e(date_fr($precision['cloturee_at'])) ?> :</strong> <?= e($precision['remarque_admin']) ?></span>
      <form method="post" action="<?= e(url('caisse/precision')) ?>" class="flex"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $precision['id'] ?>"><input class="inp" name="explication" placeholder="Votre réponse" required style="width:280px"><button class="btn">Répondre</button></form>
    </div>
  <?php endif; ?>
  <div class="banner warn" id="offline" hidden style="border-radius:0"><span><strong>Pas de connexion internet.</strong> Continuez à vendre : les ventes sont gardées sur ce poste et envoyées automatiquement au retour du réseau. Le stock affiché peut avoir quelques minutes de retard.</span></div>
  <div class="banner bad" id="syncerr" hidden style="border-radius:0"><span id="syncerr-t"></span><button class="btn" data-act="retry">Réessayer maintenant</button></div>
  <div class="pos-b">
    <section class="pos-l">
      <div class="pos-search">
        <label class="sr" for="q">Rechercher un produit</label>
        <?= icon('search') ?>
        <input id="q" class="inp" placeholder="Rechercher un produit ou scanner son code" autocomplete="off" spellcheck="false">
        <span style="position:absolute;right:14px;top:18px" class="flex"><button class="ibtn" data-act="clearq" hidden aria-label="Effacer la recherche"><?= icon('x') ?></button><span class="kbd">F2</span></span>
      </div>
      <div id="quick">
        <div class="between" style="margin-bottom:12px"><h2 class="h2">Accès rapide</h2><span class="small muted">Les produits les plus vendus de l'officine</span></div>
        <div class="tiles" id="tiles"><div class="sk" style="height:92px"></div><div class="sk" style="height:92px"></div><div class="sk" style="height:92px"></div><div class="sk" style="height:92px"></div></div>
      </div>
      <div id="results" class="card" hidden style="overflow:hidden">
        <div class="res th" style="cursor:default;min-height:42px"><span id="rescount"></span><span>Disponibilité</span><span>Unité</span><span class="ra">Prix</span></div>
        <div id="reslist"></div>
      </div>
      <div id="held" hidden></div>
    </section>
    <aside class="pos-r">
      <div class="card-h" style="border-radius:0">
        <span style="display:flex;flex-direction:column"><span class="h2">Ticket en cours</span><span class="small muted" id="count">0 article</span></span>
        <span class="flex"><button class="btn ghost" data-act="hold" disabled>En attente</button><button class="btn ghost" data-act="clear" disabled>Vider</button></span>
      </div>
      <div class="lines" id="lines"></div>
      <div class="pos-f">
        <div class="between">
          <button class="btn" data-act="discount" disabled>Remise</button>
          <span class="small muted" id="discnote">Au-delà de <?= (int) $remiseMax ?> % : code d'un responsable</span>
        </div>
        <div class="between"><span class="h3">Total</span><span class="total-big"><span id="total">0</span> <small style="font-size:16px;color:var(--muted)">FCFA</small></span></div>
        <button class="btn pri xl block" data-act="pay" disabled>Encaisser <span class="kbd">F9</span></button>
      </div>
    </aside>
  </div>
</div>

<dialog id="dlg-pay" aria-labelledby="pay-t" style="max-width:min(780px,calc(100vw - 32px))">
  <div class="dh"><h2 class="h2" id="pay-t">Encaissement en espèces</h2><button class="ibtn" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
  <div class="db" id="pay-step">
    <div class="pay">
      <div class="stack-s">
        <div class="between"><span class="muted">À payer</span><span class="h2 num"><span data-v="total"></span> FCFA</span></div>
        <span class="lbl" style="margin:8px 0 0">Montant reçu du client</span>
        <div class="recv" id="recv">0</div>
        <div class="flex" id="quickamt"></div>
      </div>
      <div class="pad" id="paypad"></div>
    </div>
    <div class="change" id="change"><span id="chlabel">Monnaie à rendre</span><b><span id="chtext">0</span> FCFA</b></div>
    <div class="between">
      <button class="btn" data-act="credit" <?= $clients ? '' : 'disabled title="Aucun client autorisé à crédit"' ?>>Vendre à crédit à un client autorisé</button>
      <button class="btn pri lg" data-act="validate">Valider la vente <span class="kbd">Entrée</span></button>
    </div>
  </div>
  <div class="db" id="credit-step" hidden>
    <p class="muted">Seuls les clients autorisés par le propriétaire peuvent acheter à crédit, dans la limite de leur plafond.</p>
    <div class="stack-s" id="clientlist">
      <?php foreach ($clients as $c): $reste = (int) $c['plafond'] - (int) $c['du']; ?>
        <label class="opt-card"><input type="radio" name="client" value="<?= (int) $c['id'] ?>" data-reste="<?= $reste ?>" class="chk">
          <span style="flex:1"><span style="font-weight:600"><?= e($c['nom']) ?></span><br><span class="small muted"><?= e($c['type']) ?> · doit <?= fmt($c['du']) ?> sur <?= fmt($c['plafond']) ?> FCFA</span></span>
          <span class="small num">reste <?= fmt(max(0, $reste)) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="err-msg" id="credit-err" hidden></p>
    <div class="between"><button class="btn" data-act="cash">Retour aux espèces</button><button class="btn pri lg" data-act="validate-credit">Valider la vente à crédit</button></div>
  </div>
  <div class="db" id="done-step" hidden style="align-items:center;text-align:center">
    <span class="av" style="width:64px;height:64px;background:var(--ok-soft)"><?= icon('check') ?></span>
    <h2 class="h1">Vente enregistrée</h2>
    <span class="muted" id="done-sub"></span>
    <div class="change w-full" id="done-change"><span>Rendez au client</span><b><span id="done-rendu">0</span> FCFA</b></div>
    <div class="flex" style="justify-content:center"><button class="btn lg" data-act="print"><?= icon('print') ?>Imprimer le ticket</button><button class="btn pri lg" data-act="new">Nouvelle vente <span class="kbd">Entrée</span></button></div>
  </div>
</dialog>

<dialog id="dlg-disc" aria-labelledby="disc-t">
  <div class="dh"><h2 class="h2" id="disc-t">Remise</h2><button class="ibtn" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
  <div class="db">
    <div class="chips" id="disc-chips"></div>
    <div><label class="lbl" for="disc-amt">Montant de la remise (FCFA)</label><input id="disc-amt" class="inp num" inputmode="numeric"></div>
    <div id="disc-pin-w" hidden>
      <label class="lbl" for="disc-pin">Code d'un responsable <span class="opt" id="disc-who"></span></label>
      <input id="disc-pin" class="inp num" type="password" inputmode="numeric" maxlength="4" style="width:160px;letter-spacing:.4em;font-size:20px">
      <p class="hint">La remise sera enregistrée avec le nom de la personne qui l'autorise.</p>
    </div>
    <p class="err-msg" id="disc-err" hidden></p>
  </div>
  <div class="df"><button class="btn" data-act="disc-remove">Supprimer la remise</button><button class="btn pri" data-act="disc-ok">Appliquer</button></div>
</dialog>

<dialog id="dlg-short" aria-labelledby="short-t">
  <div class="dh"><h2 class="h2" id="short-t">Stock insuffisant</h2></div>
  <div class="db"><div class="banner warn"><span id="short-txt"></span></div></div>
  <div class="df"><button class="btn" data-close>Annuler</button><button class="btn pri" data-act="short-ok">Vendre ce qui reste</button></div>
</dialog>

<dialog id="dlg-exp" aria-labelledby="exp-t">
  <div class="dh"><h2 class="h2" id="exp-t">Produit périmé</h2><button class="ibtn" data-close aria-label="Fermer"><?= icon('x') ?></button></div>
  <div class="db"><div class="banner bad"><span>Ce produit ne peut pas être vendu. Retirez les boîtes du rayon et prévenez le propriétaire.</span></div><p id="exp-txt"></p></div>
  <div class="df"><button class="btn" data-close>Fermer</button><button class="btn pri" data-act="exp-signal">Signaler le retrait du rayon</button></div>
</dialog>

<script id="pos-data" type="application/json"><?= json_encode([
    'session' => (int) $sess['id'],
    'user' => prenom($u['nom']),
    'remiseMax' => $remiseMax,
    'verrou' => $verrou,
    'urls' => [
        'catalogue' => url('caisse/catalogue'), 'valider' => url('caisse/valider'), 'autoriser' => url('caisse/autoriser'),
        'ticket' => url('caisse/ticket'), 'verrou' => url('verrou'), 'lock' => url('verrou/activer'), 'signaler' => url('caisse/signaler'),
    ],
], JSON_UNESCAPED_UNICODE) ?></script>
<script src="assets/app.js?v=1"></script>
<script src="assets/pos.js?v=1"></script>
</body>
</html>
