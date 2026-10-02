<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

start_session();

// route => [fichier du contrôleur, fonction]
$routes = [
    // Connexion et sécurité du poste
    'login'              => ['auth', 'login'],
    'logout'             => ['auth', 'logout'],
    'deux-etapes'        => ['auth', 'deuxEtapes'],
    'deux-etapes/config' => ['auth', 'deuxEtapesConfig'],
    'mot-de-passe'       => ['auth', 'premierMotDePasse'],
    'verrou'             => ['auth', 'verrou'],
    'verrou/activer'     => ['auth', 'verrouiller'],
    'poste'              => ['auth', 'enregistrerPoste'],

    // Espace vendeur
    'caisse'             => ['caisse', 'vente'],
    'caisse/ouvrir'      => ['caisse', 'ouvrir'],
    'caisse/catalogue'   => ['caisse', 'catalogue'],
    'caisse/valider'     => ['caisse', 'enregistrerVente'],
    'caisse/autoriser'   => ['caisse', 'autoriser'],
    'caisse/ticket'      => ['caisse', 'ticket'],
    'caisse/ventes'      => ['caisse', 'mesVentes'],
    'caisse/cloturer'    => ['caisse', 'cloturer'],
    'caisse/annuler'     => ['caisse', 'annuler'],
    'caisse/depense'     => ['caisse', 'depense'],
    'caisse/precision'   => ['caisse', 'repondrePrecision'],
    'caisse/signaler'    => ['caisse', 'signalerPerime'],

    // Pilotage et argent
    'dashboard'          => ['dashboard', 'index'],
    'caisses'            => ['caisses', 'index'],
    'caisses/valider'    => ['caisses', 'valider'],
    'caisses/precisions' => ['caisses', 'precisions'],
    'caisses/depense'    => ['caisses', 'depense'],
    'creances'           => ['creances', 'index'],
    'creances/client'    => ['creances', 'enregistrerClient'],
    'creances/encaisser' => ['creances', 'encaisser'],
    'creances/historique'=> ['creances', 'historique'],

    // Approvisionnement
    'reappro'            => ['commandes', 'reappro'],
    'reappro/creer'      => ['commandes', 'creerDepuisReappro'],
    'commandes'          => ['commandes', 'index'],
    'commande'           => ['commandes', 'voir'],
    'commande/nouvelle'  => ['commandes', 'nouvelle'],
    'commande/statut'    => ['commandes', 'statut'],
    'commande/imprimer'  => ['commandes', 'imprimer'],
    'commande/paiement'  => ['commandes', 'paiement'],
    'receptions'         => ['receptions', 'index'],
    'reception'          => ['receptions', 'formulaire'],
    'reception/enregistrer' => ['receptions', 'enregistrer'],
    'fournisseurs'       => ['fournisseurs', 'index'],
    'fournisseur/enregistrer' => ['fournisseurs', 'enregistrer'],

    // Stock et catalogue
    'stock'              => ['stock', 'index'],
    'stock/ajuster'      => ['stock', 'ajuster'],
    'stock/inventaire'   => ['stock', 'inventaire'],
    'stock/retirer'      => ['stock', 'retirerPerimes'],
    'stock/mouvements'   => ['stock', 'mouvements'],
    'produits'           => ['produits', 'index'],
    'produit'            => ['produits', 'fiche'],
    'produit/enregistrer'=> ['produits', 'enregistrer'],
    'produit/nouveau'    => ['produits', 'nouveau'],
    'produit/recherche'  => ['produits', 'recherche'],
    'produit/verifie'    => ['produits', 'marquerVerifie'],
    'transferts'         => ['transferts', 'index'],
    'transfert/envoyer'  => ['transferts', 'envoyer'],
    'transfert/recevoir' => ['transferts', 'recevoir'],

    // Administration
    'utilisateurs'       => ['utilisateurs', 'index'],
    'utilisateur/creer'  => ['utilisateurs', 'creer'],
    'utilisateur/modifier' => ['utilisateurs', 'modifier'],
    'utilisateur/reinitialiser' => ['utilisateurs', 'reinitialiser'],
    'utilisateur/activer'=> ['utilisateurs', 'activer'],
    'droits'             => ['utilisateurs', 'droits'],
    'securite'           => ['securite', 'index'],
    'securite/retirer'   => ['securite', 'retirerAppareil'],
    'securite/debloquer' => ['securite', 'debloquer'],
    'securite/regles'    => ['securite', 'regles'],
    'securite/code'      => ['securite', 'codePoste'],
    'journal'            => ['securite', 'journal'],
];

$r = (string) ($_GET['r'] ?? '');
if ($r === '') {
    $u = Auth::user();
    if (!$u) redirect('login');
    redirect(Perm::can($u, 'dashboard') ? 'dashboard' : (Perm::can($u, 'vendre') ? 'caisse' : 'stock'));
}
if (!isset($routes[$r])) not_found();

[$file, $fn] = $routes[$r];
require_once ROOT . '/controllers/' . $file . '.php';
if (is_post()) csrf_check();

try {
    $fn();
} catch (Throwable $e) {
    @mkdir(ROOT . '/logs', 0775, true);
    error_log('[' . now() . '] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 3, ROOT . '/logs/erreurs.log');
    if (Auth::isAjax()) json_out(['error' => 'Erreur du serveur. Réessayez.'], 500);
    http_response_code(500);
    view('erreur', ['titre' => 'Erreur', 'message' => 'Une erreur est survenue. Elle a été enregistrée ; réessayez dans un instant.'], Auth::user() ? 'admin' : 'auth');
}
