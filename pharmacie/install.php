<?php
declare(strict_types=1);

/*
 * Installation de la base de données.
 *
 *   php install.php          crée les tables et le compte du propriétaire (mot de passe provisoire affiché)
 *   php install.php --demo   crée les tables avec des données d'exemple pour essayer l'application
 *
 * ATTENTION : les tables existantes sont supprimées.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('À lancer en ligne de commande : php install.php');
}

require __DIR__ . '/src/bootstrap.php';

$demo = in_array('--demo', $argv, true);
$force = in_array('--force', $argv, true);

try {
    $exists = (int) Db::val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
} catch (PDOException $e) {
    fwrite(STDERR, "Connexion à MySQL impossible : " . $e->getMessage() . "\nVérifiez config.local.php (voir README.md).\n");
    exit(1);
}
if ($exists && !$force) {
    echo "La base contient déjà des données. Elles seront EFFACÉES.\nTapez OUI pour continuer : ";
    if (trim((string) fgets(STDIN)) !== 'OUI') exit("Annulé.\n");
}

// ---------- Tables ----------
$sql = file_get_contents(__DIR__ . '/database/schema.sql');
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    Db::pdo()->exec($stmt);
}
echo "Tables créées.\n";

$now = now();
foreach ([
    'deux_etapes' => $demo ? '0' : '1',
    'postes_obligatoires' => $demo ? '0' : '1',
    'verrou_minutes' => '2',
] as $k => $v) setting_set($k, $v);

if (!$demo) {
    $pw = 'Pharma-' . random_int(100000, 999999) . '-' . bin2hex(random_bytes(2));
    Db::insert('users', [
        'nom' => 'Propriétaire', 'login' => 'proprietaire', 'role' => 'proprietaire', 'site_id' => null,
        'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_pw' => 1, 'created_at' => $now,
    ]);
    $s1 = Db::insert('sites', ['nom' => 'Officine principale', 'code' => 'OFFICINE', 'type' => 'officine', 'couleur' => 'r']);
    Db::insert('sites', ['nom' => 'Magasin central', 'code' => 'MAGASIN', 'type' => 'magasin', 'couleur' => 'g']);
    echo "\nCompte créé :\n  identifiant : proprietaire\n  mot de passe provisoire : $pw\n\n";
    echo "À la première connexion, choisissez votre mot de passe (12 caractères minimum)\n";
    echo "puis activez la vérification en deux étapes avec votre téléphone.\n";
    echo "Renommez les sites dans la base (table sites) si besoin.\n";
    exit(0);
}

// =====================================================================
// Données d'exemple
// =====================================================================
mt_srand(42);
$R = Db::insert('sites', ['nom' => 'Officine REMEDE', 'code' => 'REMEDE', 'type' => 'officine', 'couleur' => 'r']);
$M = Db::insert('sites', ['nom' => 'Officine MADOBI', 'code' => 'MADOBI', 'type' => 'officine', 'couleur' => 'm']);
$G = Db::insert('sites', ['nom' => 'Magasin central', 'code' => 'MAGASIN', 'type' => 'magasin', 'couleur' => 'g']);

$PW = 'pharma2026';
$hash = password_hash($PW, PASSWORD_DEFAULT);
$pin = fn($p) => password_hash($p, PASSWORD_DEFAULT);
$users = [
    'proprietaire'   => ['Propriétaire', 'proprietaire', null, '1379', 0],
    'hadiza.remede'  => ['Hadiza Moussa', 'gerant', $R, '2468', 0],
    'aicha.remede'   => ['Aïcha Issa', 'vendeur', $R, '2580', 0],
    'ibrahim.remede' => ['Ibrahim Sani', 'vendeur', $R, '2580', 0],
    'fatouma.remede' => ['Fatouma Issa', 'vendeur', $R, null, 1],
    'mariama.madobi' => ['Mariama Abdou', 'vendeur', $M, '2580', 0],
    'salif.magasin'  => ['Salif Garba', 'magasinier', $G, '2580', 0],
    'moussa.madobi'  => ['Moussa Daouda', 'vendeur', $M, '2580', 0],
];
$U = [];
foreach ($users as $login => [$nom, $role, $site, $p, $fresh]) {
    $U[$login] = Db::insert('users', [
        'nom' => $nom, 'login' => $login, 'role' => $role, 'site_id' => $site, 'password_hash' => $hash,
        'must_change_pw' => $fresh, 'pin_hash' => $p ? $pin($p) : null, 'created_at' => $now,
        'actif' => $login === 'moussa.madobi' ? 0 : 1,
    ]);
}

$NY = Db::insert('fournisseurs', ['code' => 'NY', 'nom' => 'Fournisseur NY', 'contact' => '[Nom du contact]', 'telephone' => '[Téléphone]', 'delai_jours' => 3, 'conditions' => 'À la livraison', 'created_at' => $now]);
$AZ = Db::insert('fournisseurs', ['code' => 'AZ', 'nom' => 'Fournisseur AZ', 'contact' => '[Nom du contact]', 'telephone' => '[Téléphone]', 'delai_jours' => 7, 'conditions' => 'À 30 jours', 'created_at' => $now]);

// désignation, dci, forme, contenance, unité, détail (nom, contenance), prix boîte, prix détail, achat, fournisseur, alias, ordonnance
$P = [
    ['AMOXICILLINE 500MG GEL B/12', 'Amoxicilline', 'Gélule', 12, 'gélules', ['Plaquette', 6], 4500, 500, 2650, $NY, ['AMOXI GEL', 'AMOXI'], 1],
    ['PARACETAMOL 500MG CP B/100', 'Paracétamol', 'Comprimé', 100, 'comprimés', ['Plaquette', 10], 900, 100, 520, $NY, ['PARA CP', 'PARA'], 0],
    ['CLOXACILLINE 500MG GEL B/12', 'Cloxacilline', 'Gélule', 12, 'gélules', ['Plaquette', 6], 2000, 1000, 1150, $NY, ['CLOXA GEL', 'CLOXA'], 1],
    ['METRONIDAZOLE 250MG CP B/20', 'Métronidazole', 'Comprimé', 20, 'comprimés', ['Plaquette', 10], 300, 150, 160, $NY, ['METRO CP', 'METRO'], 1],
    ['SRO SELS DE REHYDRATATION SACHET', 'Sels de réhydratation orale', 'Sachet', 1, 'sachet', null, 250, null, 120, $AZ, ['SRO'], 0],
    ['PARACETAMOL 120MG/5ML SIROP FL/100ML', 'Paracétamol', 'Sirop', 1, 'flacon', null, 1000, null, 560, $NY, ['PARA SIROP'], 0],
    ['EFFERALGAN 150MG/5ML SIROP FL/90ML', 'Paracétamol', 'Sirop', 1, 'flacon', null, 2500, null, 1600, $AZ, ['EFFERALGAN SIROP', 'EFFER'], 0],
    ['DOLIPRANE 1000MG CP B/8', 'Paracétamol', 'Comprimé', 8, 'comprimés', null, 2000, null, 1250, $AZ, ['DOLIPRANE', 'DOLI'], 0],
    ['ARTHEMETER/LUMEFANTRINE 20/120MG CP B/24', 'Artéméther + luméfantrine', 'Comprimé', 24, 'comprimés', ['Plaquette', 6], 4000, 1000, 2300, $AZ, ['ARTHEMETER', 'COARTEM'], 1],
    ['CIPROFLOXACINE 500MG CP B/10', 'Ciprofloxacine', 'Comprimé', 10, 'comprimés', null, 1500, null, 820, $NY, ['CIPRO'], 1],
    ['COTRIMOXAZOLE 480MG CP B/100', 'Cotrimoxazole', 'Comprimé', 100, 'comprimés', ['Plaquette', 10], 900, 100, 480, $NY, ['COTRIM CP', 'COTRIM'], 1],
    ['BANDELETTES GLYCEMIE G TEST', 'Test de glycémie', 'Autre', 1, 'unité', null, 500, null, 260, $AZ, ['G TEST'], 0],
    ['TRES ORIX SIROP FL/150ML', 'Cyproheptadine + vitamines', 'Sirop', 1, 'flacon', null, 5500, null, 3400, $AZ, ['TRES ORIX SP', 'TRESORIX'], 0],
    ['FEBRILEX CP B/20', 'Paracétamol + chlorphénamine', 'Comprimé', 20, 'comprimés', ['Plaquette', 10], 1000, 500, 540, $NY, ['FEBRILEX'], 0],
    ['PROFENID 100MG CP B/30', 'Kétoprofène', 'Comprimé', 30, 'comprimés', null, 3500, null, 2100, $AZ, ['PROFENID'], 1],
    ['FERCEFOL SIROP FL/200ML', 'Fer + acide folique', 'Sirop', 1, 'flacon', null, 3250, null, 1900, $AZ, ['FERCEFOL SP'], 0],
    ['BRUFEN 100MG/5ML SIROP FL/150ML', 'Ibuprofène', 'Sirop', 1, 'flacon', null, 4000, null, 2450, $AZ, ['BRUFEN SP', 'BRUFEN'], 0],
    ['PROMETHAZINE SIROP FL/150ML', 'Prométhazine', 'Sirop', 1, 'flacon', null, 1000, null, 520, $NY, ['PROMETHAZINE SP'], 0],
    ['GENTAMICINE 0,3% COLLYRE FL/10ML', 'Gentamicine', 'Collyre', 1, 'flacon', null, 500, null, 240, $NY, ['GENTA CY'], 1],
    ['ACIDE FOLIQUE TONG 5MG CP B/40', 'Acide folique', 'Comprimé', 40, 'comprimés', ['Plaquette', 10], 1500, 400, 396, $NY, ['ACIDE FOLIQUE', 'FOLIQUE', 'B9'], 0],
    ['ACUMOL 100MG/500MG CP B/20', 'Acéclofénac + paracétamol', 'Comprimé', 20, 'comprimés', null, 5500, null, 3322, $NY, ['ACUMOL'], 1],
    ['ABZ 200MG/5ML SUSP BUV F/10ML', 'Albendazole', 'Suspension', 1, 'flacon', null, 1250, null, 610, $NY, ['ABZ'], 0],
    ['CEFIX 200MG CPR B/10', 'Céfixime', 'Comprimé', 10, 'comprimés', null, 7500, null, 4399, $NY, ['CEFIX'], 1],
    ['CATACOL COLLYRE 0,1% FL/10ML', 'Catalase', 'Collyre', 1, 'flacon', null, 2000, null, 1063, $NY, ['CATACOL'], 0],
    ['CILOXAN 0.3% COLLYRE F/5ML', 'Ciprofloxacine', 'Collyre', 1, 'flacon', null, 7000, null, 4666, $NY, ['CILOXAN'], 1],
    ['FLEXPRO EXTRA 400/500MG CP B/20', 'Ibuprofène + paracétamol', 'Comprimé', 20, 'comprimés', null, 4500, null, 2651, $AZ, ['FLEXPRO'], 0],
    ['BIOGEST 200MG OVULE B/15', 'Progestérone', 'Autre', 15, 'ovules', null, 6000, null, 3777, $AZ, ['BIOGEST'], 1],
    ['CARTIOFEN CP B/30', 'Glucosamine', 'Comprimé', 30, 'comprimés', null, 7000, null, 4533, $AZ, ['CARTIOFEN'], 0],
    ['CARNIVITA FORTE CP B/30', 'L-carnitine + vitamines', 'Comprimé', 30, 'comprimés', null, 15000, null, 10404, $AZ, ['CARNIVITA'], 0],
    ['ACIDE FOLIQUE+FER (FOLIRON) CP B/30', 'Acide folique + fer', 'Comprimé', 30, 'comprimés', null, 3000, null, 1800, $AZ, ['FOLIRON'], 0],
    ['LORATADINE 10MG CP B/10', 'Loratadine', 'Comprimé', 10, 'comprimés', null, null, null, 800, $NY, ['LORA'], 0],
    ['AMOXICILLINE/AC. CLAVULANIQUE 1G/125MG CP B/8', 'Amoxicilline + acide clavulanique', 'Comprimé', 8, 'comprimés', ['Plaquette', 4], 5500, 2900, 3980, $NY, ['AMOXI CLAV', 'AUGMENTIN'], 1],
];
$PID = [];
foreach ($P as $i => [$des, $dci, $forme, $cont, $ub, $det, $pb, $pd, $pa, $f, $alias, $rx]) {
    $fractions = $det ? intdiv($cont, $det[1]) : 1;
    $id = Db::insert('produits', [
        'designation' => $des, 'dci' => $dci, 'forme' => $forme, 'categorie' => 'Médicament', 'ordonnance' => $rx,
        'contenance' => $cont, 'unite_base' => $ub, 'detail_nom' => $det[0] ?? null, 'detail_contenance' => $det[1] ?? null,
        'fractions' => $fractions, 'prix_boite' => $pb, 'prix_detail' => $pd, 'prix_achat' => $pa, 'prix_achat_prec' => $pa,
        'fournisseur_id' => $f, 'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
        'created_by' => $des === 'LORATADINE 10MG CP B/10' ? $U['salif.magasin'] : null,
        'a_verifier' => str_starts_with($des, 'AMOXICILLINE/AC') ? 1 : 0,
        'actif' => str_contains($des, 'FOLIRON') ? 0 : 1,
        'code_barre' => '61' . str_pad((string) (1000000 + $i * 7919), 11, '0', STR_PAD_LEFT),
    ]);
    if (str_starts_with($des, 'AMOXICILLINE/AC')) Db::update('produits', ['created_by' => $U['hadiza.remede'], 'created_at' => $now], 'id = ?', [$id]);
    foreach ($alias as $a) Db::insert('produit_alias', ['produit_id' => $id, 'alias' => $a]);
    foreach ([$R => 3, $M => 2, $G => 10] as $s => $seuil) Db::insert('seuils', ['produit_id' => $id, 'site_id' => $s, 'seuil' => $seuil]);
    $PID[$des] = $id;
}

// Stock initial : un lot par site (quantités en boîtes)
// L'historique de ventes est rejoué sur des lots très garnis, puis chaque lot est ramené
// à la quantité voulue aujourd'hui (le mouvement d'entrée initial est corrigé en conséquence).
$cibles = [];
$lot = function (string $des, int $site, int $boites, string $per, ?string $num = null) use ($PID, &$cibles) {
    $p = Db::one('SELECT * FROM produits WHERE id = ?', [$PID[$des]]);
    $big = 100000 * (int) $p['fractions'];
    $id = Stock::entrer((int) $p['id'], $site, $num ?? ('LT-' . random_int(1000, 9999)), $per, $big, (int) $p['prix_achat'], 'inventaire', 'STOCK-INITIAL', 'Reprise du stock existant');
    $cibles[$id] = [$big, $boites * (int) $p['fractions']];
};
$fut = fn($m) => date('Y-m-01', strtotime("+$m months"));
$stock = [
    'AMOXICILLINE 500MG GEL B/12' => [[$R, 40, 18], [$M, 20, 18], [$G, 60, 20]],
    'PARACETAMOL 500MG CP B/100' => [[$R, 30, 24], [$M, 15, 24], [$G, 40, 30]],
    'CLOXACILLINE 500MG GEL B/12' => [[$R, 25, 16], [$M, 10, 16], [$G, 20, 20]],
    'METRONIDAZOLE 250MG CP B/20' => [[$R, 30, 20], [$M, 12, 20]],
    'SRO SELS DE REHYDRATATION SACHET' => [[$R, 300, 30], [$M, 120, 30], [$G, 500, 30]],
    'PARACETAMOL 120MG/5ML SIROP FL/100ML' => [[$R, 28, 14], [$M, 10, 14]],
    'EFFERALGAN 150MG/5ML SIROP FL/90ML' => [[$R, 8, 10], [$M, 4, 10]],
    'DOLIPRANE 1000MG CP B/8' => [[$R, 25, 22], [$M, 10, 22], [$G, 30, 22]],
    'ARTHEMETER/LUMEFANTRINE 20/120MG CP B/24' => [[$R, 18, 12], [$M, 8, 12]],
    'CIPROFLOXACINE 500MG CP B/10' => [[$R, 70, 26], [$M, 20, 26]],
    'COTRIMOXAZOLE 480MG CP B/100' => [[$R, 22, 30], [$M, 8, 30]],
    'BANDELETTES GLYCEMIE G TEST' => [[$R, 30, 9], [$M, 15, 9]],
    'TRES ORIX SIROP FL/150ML' => [[$R, 10, 15], [$M, 6, 15]],
    'FEBRILEX CP B/20' => [[$R, 7, -1], [$M, 3, -1]],
    'FERCEFOL SIROP FL/200ML' => [[$R, 14, 11], [$M, 6, 11]],
    'BRUFEN 100MG/5ML SIROP FL/150ML' => [[$R, 9, 13], [$M, 5, 13]],
    'PROMETHAZINE SIROP FL/150ML' => [[$R, 16, 20], [$M, 6, 20]],
    'GENTAMICINE 0,3% COLLYRE FL/10ML' => [[$R, 18, 4], [$M, 8, 4]],
    'ACIDE FOLIQUE TONG 5MG CP B/40' => [[$R, 6, 25]],
    'ACUMOL 100MG/500MG CP B/20' => [[$R, 9, 20], [$M, 2, 20], [$G, 10, 20]],
    'ABZ 200MG/5ML SUSP BUV F/10ML' => [[$R, 6, 3], [$M, 2, 3]],
    'CEFIX 200MG CPR B/10' => [[$R, 3, 18], [$M, 1, 18]],
    'CATACOL COLLYRE 0,1% FL/10ML' => [[$R, 5, 4], [$G, 8, 4]],
    'CILOXAN 0.3% COLLYRE F/5ML' => [[$R, 2, 14]],
    'BIOGEST 200MG OVULE B/15' => [[$R, 2, 13]],
    'CARTIOFEN CP B/30' => [[$R, 3, 20], [$M, 1, 20]],
    'CARNIVITA FORTE CP B/30' => [[$R, 2, 19]],
    'ACIDE FOLIQUE+FER (FOLIRON) CP B/30' => [[$R, 5, -14]],
    'AMOXICILLINE/AC. CLAVULANIQUE 1G/125MG CP B/8' => [[$R, 6, 22]],
];
foreach ($stock as $des => $lots) foreach ($lots as [$s, $b, $m]) $lot($des, $s, $b, $fut($m));
Db::run("UPDATE mouvements SET at = ?", [date('Y-m-d 07:00:00', strtotime('-31 days'))]);
Db::run("UPDATE lots SET created_at = ?", [date('Y-m-d 07:00:00', strtotime('-31 days'))]);

// Clients autorisés à crédit
$C = [];
foreach ([['Clinique Ténéré', 'Professionnel', $R, 250000], ['Moussa D.', 'Particulier', $M, 20000], ['Harouna B.', 'Particulier', $R, 15000], ['Aïssa H.', 'Particulier', $R, 20000], ['Salamatou K.', 'Particulier', $M, 15000]] as [$n, $t, $s, $pl]) {
    $C[$n] = Db::insert('clients', ['nom' => $n, 'type' => $t, 'site_id' => $s, 'plafond' => $pl, 'telephone' => '[Téléphone]', 'created_at' => date('Y-m-d H:i:s', strtotime('-50 days'))]);
}

// ---------- Historique de ventes sur 30 jours ----------
$prods = Db::all('SELECT * FROM produits WHERE actif = 1 AND prix_boite IS NOT NULL');
$weights = ['AMOXICILLINE 500MG GEL B/12' => 9, 'PARACETAMOL 500MG CP B/100' => 9, 'CLOXACILLINE 500MG GEL B/12' => 6, 'METRONIDAZOLE 250MG CP B/20' => 6, 'SRO SELS DE REHYDRATATION SACHET' => 5, 'CIPROFLOXACINE 500MG CP B/10' => 3, 'DOLIPRANE 1000MG CP B/8' => 3, 'COTRIMOXAZOLE 480MG CP B/100' => 3];
$pool = [];
foreach ($prods as $p) for ($i = 0; $i < ($weights[$p['designation']] ?? 1); $i++) $pool[] = $p;

$vendre = function (int $sess, int $site, int $user, string $at, ?int $client = null) use ($pool) {
    $n = mt_rand(1, 3);
    $lines = [];
    $brut = 0;
    $pris = [];
    for ($i = 0; $i < $n; $i++) {
        $p = $pool[mt_rand(0, count($pool) - 1)];
        if (isset($pris[$p['id']])) continue;
        $pris[$p['id']] = true;
        $u = ($p['detail_nom'] && $p['prix_detail'] && mt_rand(0, 2) > 0) ? 'detail' : 'boite';
        $q = $u === 'detail' ? mt_rand(1, 3) : 1;
        $prix = (int) ($u === 'detail' ? $p['prix_detail'] : $p['prix_boite']);
        $fr = $u === 'detail' ? $q : $q * (int) $p['fractions'];
        if (Stock::dispo((int) $p['id'], $site) < $fr) continue;
        $lines[] = [$p, $u, $q, $prix, $fr];
        $brut += $prix * $q;
    }
    if (!$lines) return 0;
    if ($client) {
        $c = Db::one('SELECT plafond FROM clients WHERE id = ?', [$client]);
        if (du_client_total($client) + $brut > (int) $c['plafond']) $client = null;
    }
    $recu = (int) (ceil($brut / 500) * 500);
    $vid = Db::insert('ventes', [
        'session_id' => $sess, 'site_id' => $site, 'user_id' => $user, 'client_id' => $client, 'at' => $at,
        'total_brut' => $brut, 'remise' => 0, 'total' => $brut, 'mode' => $client ? 'credit' : 'especes',
        'recu' => $client ? null : $recu, 'rendu' => $client ? null : $recu - $brut,
    ]);
    $num = numero('V', $vid, 6);
    Db::update('ventes', ['numero' => $num], 'id = ?', [$vid]);
    foreach ($lines as [$p, $u, $q, $prix, $fr]) {
        $pris = Stock::sortir((int) $p['id'], $site, $fr, 'vente', $num);
        $cout = 0;
        foreach ($pris as $x) $cout += (int) round($x['prix_achat'] * $x['quantite'] / (int) $p['fractions']);
        $lid = Db::insert('vente_lignes', ['vente_id' => $vid, 'produit_id' => $p['id'], 'unite' => $u, 'quantite' => $q, 'prix_unitaire' => $prix, 'total' => $prix * $q, 'fractions' => $fr, 'cout' => $cout]);
        foreach ($pris as $x) Db::insert('vente_lots', ['ligne_id' => $lid, 'lot_id' => $x['lot_id'], 'quantite' => $x['quantite']]);
    }
    Db::run('UPDATE mouvements SET at = ? WHERE ref = ?', [$at, $num]);
    return $brut;
};

$vendeurs = [[$R, $U['aicha.remede']], [$R, $U['ibrahim.remede']], [$M, $U['mariama.madobi']]];
$owner = $U['proprietaire'];
Db::insert('mouvements_especes', ['at' => date('Y-m-d 07:00:00', strtotime('-31 days')), 'type' => 'solde_initial', 'libelle' => 'Solde de départ', 'montant' => 900000, 'user_id' => $owner]);
for ($d = 30; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-$d days"));
    $dow = (int) date('w', strtotime($day));
    foreach ($vendeurs as $vi => [$site, $user]) {
        if ($dow === 0 && $vi === 1) continue; // Ibrahim ne travaille pas le dimanche
        $open = "$day 0" . (7 + $vi % 2) . ':5' . $vi . ':00';
        $sid = Db::insert('caisse_sessions', ['site_id' => $site, 'user_id' => $user, 'poste' => $site === $R ? 'Caisse 1 · comptoir principal' : 'Caisse 1 · MADOBI', 'ouverte_at' => $open, 'fond' => 0, 'statut' => 'ouverte']);
        $nb = ($site === $R ? 14 : 10) + mt_rand(-3, 6) - ($dow === 0 ? 6 : 0);
        $especes = 0;
        for ($k = 0; $k < $nb; $k++) {
            $debut = strtotime("$day 08:00:00");
            $fin = $d === 0 ? max($debut + 600, time() - 300) : strtotime("$day 18:30:00");
            $at = date('Y-m-d H:i:s', $debut + intdiv(($fin - $debut) * $k, $nb) + mt_rand(0, 300));
            if ($d === 0 && strtotime($at) > time()) break;
            $client = (mt_rand(0, 40) === 0) ? ($site === $R ? $C['Harouna B.'] : $C['Moussa D.']) : null;
            $t = $vendre($sid, $site, $user, $at, $client);
            if (!$client) $especes += $t;
        }
        $dep = 0;
        if ($d === 0 && $vi === 0) {
            Db::insert('depenses', ['at' => "$day 10:15:00", 'site_id' => $site, 'session_id' => $sid, 'user_id' => $user, 'libelle' => 'Transport livraison', 'montant' => 6000]);
            $dep = 6000;
        }
        if ($d === 0 && $vi === 1) continue; // Ibrahim : caisse encore ouverte
        $att = $especes - $dep;
        $ecart = ($d === 0 && $vi === 0) ? -2000 : 0;
        $close = "$day 19:0" . $vi . ':00';
        $statut = $d === 0 ? 'cloturee' : 'validee';
        if ($d === 0 && $vi === 2) $statut = 'validee';
        Db::update('caisse_sessions', [
            'cloturee_at' => $close, 'attendu' => $att, 'compte' => $att + $ecart, 'ecart' => $ecart, 'statut' => $statut,
            'explication' => $ecart ? 'Monnaie rendue en trop à un client pendant l\'affluence de midi.' : null,
            'valide_par' => $statut === 'validee' ? $owner : null, 'valide_at' => $statut === 'validee' ? "$day 19:20:00" : null,
            'billetage' => json_encode(['10000' => intdiv($att + $ecart, 10000)]),
        ], 'id = ?', [$sid]);
        if ($statut === 'validee') {
            $who = Db::val('SELECT nom FROM users WHERE id = ?', [$user]);
            Db::insert('mouvements_especes', ['at' => "$day 19:20:00", 'type' => 'versement', 'libelle' => 'Versement validé · ' . prenom($who) . ' (' . site($site)['nom_court'] . ')', 'montant' => $att, 'ref' => 'S-' . $sid, 'user_id' => $owner]);
        }
    }
    if ($d % 10 === 5) {
        Db::insert('depenses', ['at' => "$day 10:02:00", 'site_id' => $R, 'session_id' => null, 'user_id' => $owner, 'libelle' => 'Électricité REMEDE', 'montant' => 18500]);
        Db::insert('mouvements_especes', ['at' => "$day 10:02:00", 'type' => 'depense', 'libelle' => 'Dépense · électricité REMEDE', 'montant' => -18500, 'user_id' => $owner]);
    }
}
foreach ($cibles as $lotId => [$big, $cible]) {
    $vendu = $big - (int) Db::val('SELECT quantite FROM lots WHERE id = ?', [$lotId]);
    Db::update('lots', ['quantite' => $cible], 'id = ?', [$lotId]);
    Db::run("UPDATE mouvements SET quantite = ? WHERE lot_id = ? AND ref = 'STOCK-INITIAL'", [$cible + $vendu, $lotId]);
}

// Créances : la Clinique Ténéré achète sur proforma
$s = Db::val("SELECT id FROM caisse_sessions WHERE site_id = ? AND statut = 'validee' ORDER BY id DESC LIMIT 1", [$R]);
foreach ([34, 28, 21, 15, 12, 9, 6] as $ago) $vendre((int) $s, $R, $U['aicha.remede'], date('Y-m-d 10:30:00', strtotime("-$ago days")), $C['Clinique Ténéré']);
Db::insert('creance_paiements', ['client_id' => $C['Harouna B.'], 'at' => date('Y-m-d 11:00:00', strtotime('-6 days')), 'montant' => 2000, 'user_id' => $owner, 'note' => 'Espèces']);
Db::insert('mouvements_especes', ['at' => date('Y-m-d 11:00:00', strtotime('-6 days')), 'type' => 'creance', 'libelle' => 'Remboursement · Harouna B.', 'montant' => 2000, 'user_id' => $owner]);

// ---------- Approvisionnement ----------
$cmd = function (int $f, int $site, string $statut, string $created, array $lignes) use ($owner, $PID) {
    $id = Db::insert('commandes', ['fournisseur_id' => $f, 'site_id' => $site, 'statut' => $statut, 'created_by' => $owner, 'created_at' => $created, 'envoyee_at' => $statut !== 'creee' ? date('Y-m-d 09:12:00', strtotime($created . ' +1 day')) : null]);
    Db::update('commandes', ['numero' => numero('BC', $id)], 'id = ?', [$id]);
    foreach ($lignes as [$des, $q, $prix]) Db::insert('commande_lignes', ['commande_id' => $id, 'produit_id' => $PID[$des], 'quantite' => $q, 'prix_achat' => $prix]);
    return $id;
};
$recevoir = function (int $cmdId, string $at, array $recus, int $user) {
    $c = Db::one('SELECT * FROM commandes WHERE id = ?', [$cmdId]);
    $rid = Db::insert('receptions', ['commande_id' => $cmdId, 'fournisseur_id' => $c['fournisseur_id'], 'site_id' => $c['site_id'], 'bl_numero' => 'BL-' . random_int(1000, 9999), 'date_livraison' => substr($at, 0, 10), 'statut' => 'validee', 'user_id' => $user, 'created_at' => $at, 'validee_at' => $at]);
    $num = numero('R', $rid);
    Db::update('receptions', ['numero' => $num], 'id = ?', [$rid]);
    foreach (Db::all('SELECT cl.*, p.fractions FROM commande_lignes cl JOIN produits p ON p.id = cl.produit_id WHERE commande_id = ?', [$cmdId]) as $i => $l) {
        $q = $recus[$i] ?? $l['quantite'];
        if ($q <= 0) continue;
        $per = date('Y-m-01', strtotime('+' . (14 + $i * 3) . ' months'));
        $lotNum = 'LT-' . random_int(1000, 9999);
        Db::insert('reception_lignes', ['reception_id' => $rid, 'produit_id' => $l['produit_id'], 'commande_ligne_id' => $l['id'], 'attendu' => $l['quantite'], 'quantite' => $q, 'numero_lot' => $lotNum, 'peremption' => $per, 'prix_facture' => $l['prix_achat']]);
        Stock::entrer((int) $l['produit_id'], (int) $c['site_id'], $lotNum, $per, $q * (int) $l['fractions'], (int) $l['prix_achat'], 'reception', $num);
        Db::run('UPDATE commande_lignes SET recu = recu + ? WHERE id = ?', [$q, $l['id']]);
    }
    Db::run('UPDATE mouvements SET at = ? WHERE ref = ?', [$at, $num]);
};
$b35 = $cmd($NY, $G, 'livree', date('Y-m-d 18:00:00', strtotime('-33 days')), [['PARACETAMOL 500MG CP B/100', 20, 520], ['AMOXICILLINE 500MG GEL B/12', 30, 2650], ['METRONIDAZOLE 250MG CP B/20', 20, 160]]);
$recevoir($b35, date('Y-m-d 16:00:00', strtotime('-30 days')), [], $U['salif.magasin']);
Db::insert('paiements_fournisseurs', ['fournisseur_id' => $NY, 'commande_id' => $b35, 'at' => date('Y-m-d 09:00:00', strtotime('-29 days')), 'montant' => 20 * 520 + 30 * 2650 + 20 * 160, 'user_id' => $owner, 'note' => 'Espèces']);
Db::insert('mouvements_especes', ['at' => date('Y-m-d 09:00:00', strtotime('-29 days')), 'type' => 'paiement_fournisseur', 'libelle' => 'Paiement fournisseur NY · BC-' . str_pad((string) $b35, 4, '0', STR_PAD_LEFT), 'montant' => -(20 * 520 + 30 * 2650 + 20 * 160), 'user_id' => $owner]);
$b41 = $cmd($AZ, $G, 'livree', date('Y-m-d 18:00:00', strtotime('-14 days')), [['DOLIPRANE 1000MG CP B/8', 30, 1250], ['FLEXPRO EXTRA 400/500MG CP B/20', 6, 2651], ['CARTIOFEN CP B/30', 4, 4533]]);
$recevoir($b41, date('Y-m-d 15:00:00', strtotime('-7 days')), [], $U['salif.magasin']);
$b42 = $cmd($NY, $G, 'partielle', date('Y-m-d 18:30:00', strtotime('-5 days')), [['ACUMOL 100MG/500MG CP B/20', 10, 3322], ['CEFIX 200MG CPR B/10', 6, 4399], ['CATACOL COLLYRE 0,1% FL/10ML', 8, 1063], ['CILOXAN 0.3% COLLYRE F/5ML', 4, 4666]]);
$recevoir($b42, date('Y-m-d 16:40:00', strtotime('-3 days')), [10, 0, 8, 2], $U['salif.magasin']);
// Le magasin a envoyé une partie à MADOBI aujourd'hui : en route
$t = Db::insert('transferts', ['site_from' => $G, 'site_to' => $M, 'statut' => 'en_route', 'created_by' => $U['salif.magasin'], 'created_at' => date('Y-m-d 09:30:00')]);
Db::update('transferts', ['numero' => numero('T', $t)], 'id = ?', [$t]);
foreach ([['ACUMOL 100MG/500MG CP B/20', 6], ['CATACOL COLLYRE 0,1% FL/10ML', 4], ['DOLIPRANE 1000MG CP B/8', 10]] as [$des, $q]) {
    $p = Db::one('SELECT * FROM produits WHERE id = ?', [$PID[$des]]);
    foreach (Stock::sortir((int) $p['id'], $G, $q * (int) $p['fractions'], 'transfert_out', numero('T', $t)) as $x) {
        Db::insert('transfert_lignes', ['transfert_id' => $t, 'produit_id' => $p['id'], 'numero_lot' => $x['numero'], 'peremption' => $x['peremption'], 'prix_achat' => $x['prix_achat'], 'envoye' => $x['quantite']]);
    }
}

// Sécurité : un poste de caisse, un compte bloqué ce matin, des tentatives sur des identifiants inexistants
Db::insert('postes', ['nom' => 'Caisse 1 · comptoir principal', 'site_id' => $R, 'type' => 'caisse', 'token_hash' => hash('sha256', 'demo-remede'), 'created_at' => $now, 'last_seen_at' => $now, 'last_user' => 'Aïcha']);
Db::insert('postes', ['nom' => 'Caisse 1 · MADOBI', 'site_id' => $M, 'type' => 'caisse', 'token_hash' => hash('sha256', 'demo-madobi'), 'created_at' => $now, 'last_seen_at' => date('Y-m-d 18:47:00', strtotime('-1 day')), 'last_user' => 'Mariama']);
foreach ([['03:40:00', 'root', 'inexistant'], ['03:41:00', 'admin', 'inexistant']] as [$h, $l, $r]) {
    Db::insert('login_log', ['at' => date('Y-m-d ') . $h, 'login' => $l, 'appareil' => 'Appareil inconnu', 'ip' => '185.220.101.7', 'resultat' => $r]);
}
Db::insert('audit_log', ['at' => $now, 'user_id' => null, 'site_id' => null, 'poste' => null, 'action' => 'installation', 'details' => 'Données d\'exemple']);

echo "\nDonnées d'exemple installées.\n\n";
echo "Mot de passe de tous les comptes : $PW\n";
echo "  proprietaire     propriétaire (code de déverrouillage 1379)\n";
echo "  hadiza.remede    gérante REMEDE (code 2468)\n";
echo "  aicha.remede     vendeuse REMEDE (code 2580)\n";
echo "  ibrahim.remede   vendeur REMEDE (code 2580)\n";
echo "  mariama.madobi   vendeuse MADOBI (code 2580)\n";
echo "  salif.magasin    magasinier (code 2580)\n";
echo "  fatouma.remede   vendeuse REMEDE : première connexion, choisit son mot de passe\n";
echo "\nLa vérification en deux étapes et les postes enregistrés sont désactivés pour la démonstration.\n";
echo "Activez-les dans Sécurité › Règles d'accès avant la mise en service.\n";
