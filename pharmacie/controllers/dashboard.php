<?php
declare(strict_types=1);

function index(): void
{
    $u = Auth::requirePerm('dashboard');
    $site = site_filtre();
    $ws = $site ? ' AND site_id = ' . $site : '';
    $wvs = $site ? ' AND v.site_id = ' . $site : '';
    $wl = $site ? ' AND l.site_id = ' . $site : '';
    $today = today();

    // Recette du jour et comparaison avec le même jour de la semaine dernière, à la même heure
    $ca = (int) Db::val("SELECT COALESCE(SUM(total), 0) FROM ventes WHERE statut = 'payee' AND at >= ? $ws", [$today]);
    $lastWeek = date('Y-m-d', strtotime('-7 days'));
    $caAvant = (int) Db::val("SELECT COALESCE(SUM(total), 0) FROM ventes WHERE statut = 'payee' AND at >= ? AND at <= ? $ws", [$lastWeek, $lastWeek . ' ' . date('H:i:s')]);
    $delta = $caAvant > 0 ? (int) round(($ca - $caAvant) / $caAvant * 100) : null;

    // Caisses non encore validées
    $sessions = Db::all("SELECT * FROM caisse_sessions WHERE statut <> 'validee' $ws");
    $cash = 0;
    $ouvertes = 0;
    foreach ($sessions as $s) {
        if ($s['statut'] === 'ouverte') { $cash += Caisse::resume((int) $s['id'])['attendu']; $ouvertes++; }
        else $cash += (int) $s['compte'];
    }
    $ecarts = Db::one("SELECT COALESCE(SUM(ecart), 0) total, SUM(ecart <> 0) nb, SUM(ecart <> 0 AND statut <> 'validee') a_voir FROM caisse_sessions WHERE cloturee_at >= ? $ws", [$today]);

    // Recette des 14 derniers jours, par officine
    $jours = [];
    for ($i = 13; $i >= 0; $i--) $jours[date('Y-m-d', strtotime("-$i days"))] = [];
    foreach (Db::all("SELECT DATE(at) d, site_id, SUM(total) t FROM ventes WHERE statut = 'payee' AND at >= ? $ws GROUP BY DATE(at), site_id", [array_key_first($jours)]) as $r) {
        $jours[$r['d']][(int) $r['site_id']] = (int) $r['t'];
    }
    $max = max(1, ...array_values(array_map(fn($j) => array_sum($j), $jours)));

    // À traiter
    $todo = [];
    $nEcart = (int) Db::val("SELECT COUNT(*) FROM caisse_sessions WHERE statut = 'cloturee' AND ecart <> 0 $ws");
    $nValider = (int) Db::val("SELECT COUNT(*) FROM caisse_sessions WHERE statut = 'cloturee' AND ecart = 0 $ws");
    if ($nEcart) $todo[] = ['bad', $nEcart, 'Écart' . ($nEcart > 1 ? 's' : '') . ' de caisse à examiner', 'caisses'];
    if ($nValider && $u['role'] === 'proprietaire') $todo[] = ['warn', $nValider, 'Versement' . ($nValider > 1 ? 's' : '') . ' à valider', 'caisses'];
    $nPer = (int) Db::val('SELECT COUNT(DISTINCT l.produit_id) FROM lots l WHERE l.quantite > 0 AND ' . Stock::PERIME . $wl);
    if ($nPer) $todo[] = ['bad', $nPer, 'Produit' . ($nPer > 1 ? 's' : '') . ' périmé' . ($nPer > 1 ? 's' : '') . ' encore en rayon', 'stock', ['f' => 'exp']];
    $nReap = count(Reappro::suggestions($u['role'] === 'proprietaire' ? null : $site));
    if ($nReap) $todo[] = ['warn', $nReap, 'Produit' . ($nReap > 1 ? 's' : '') . ' à recommander', 'reappro'];
    $nLiv = (int) Db::val("SELECT COUNT(*) FROM commandes WHERE statut IN ('envoyee', 'partielle')" . $ws);
    if ($nLiv) $todo[] = ['info', $nLiv, 'Livraison' . ($nLiv > 1 ? 's' : '') . ' à réceptionner', 'commandes'];
    $nTr = (int) Db::val("SELECT COUNT(*) FROM transferts WHERE statut = 'en_route'" . ($site ? ' AND site_to = ' . $site : ''));
    if ($nTr) $todo[] = ['info', $nTr, 'Transfert' . ($nTr > 1 ? 's' : '') . ' à confirmer à l\'arrivée', 'transferts'];
    if ($u['role'] === 'proprietaire') {
        $nProp = (int) Db::val("SELECT COUNT(*) FROM commandes WHERE statut = 'proposee'");
        if ($nProp) $todo[] = ['warn', $nProp, 'Commande' . ($nProp > 1 ? 's' : '') . ' proposée' . ($nProp > 1 ? 's' : '') . ' par un gérant', 'commandes'];
        $nProd = (int) Db::val('SELECT COUNT(*) FROM produits WHERE actif = 1 AND (a_verifier = 1 OR prix_boite IS NULL)');
        if ($nProd) $todo[] = ['info', $nProd, 'Nouveau' . ($nProd > 1 ? 'x' : '') . ' produit' . ($nProd > 1 ? 's' : '') . ' à vérifier', 'produits', ['f' => 'check']];
        $nBl = (int) Db::val('SELECT COUNT(*) FROM users WHERE locked_until > ?', [now()]);
        if ($nBl) $todo[] = ['bad', $nBl, 'Compte' . ($nBl > 1 ? 's' : '') . ' bloqué' . ($nBl > 1 ? 's' : '') . ' après des erreurs de mot de passe', 'securite'];
    }
    $vieilles = count(array_filter(creances($site), fn($c) => ($c['age'] ?? 0) > 30));
    if ($vieilles) $todo[] = ['warn', $vieilles, 'Créance' . ($vieilles > 1 ? 's' : '') . ' de plus de 30 jours', 'creances'];

    // Caisses du jour
    $tills = Db::all("SELECT s.*, u.nom FROM caisse_sessions s JOIN users u ON u.id = s.user_id WHERE (s.ouverte_at >= ? OR s.statut IN ('ouverte', 'cloturee', 'precisions')) " . str_replace("site_id", "s.site_id", $ws) . " ORDER BY s.ouverte_at", [$today]);
    foreach ($tills as &$t) {
        $t['montant'] = $t['statut'] === 'ouverte' ? Caisse::resume((int) $t['id'])['attendu'] : (int) $t['compte'];
        $t['st'] = Caisse::statut($t);
        if ($t['statut'] !== 'ouverte' && (int) $t['ecart'] !== 0 && $t['statut'] !== 'validee') $t['st'] = ['Écart ' . fmt_signe((int) $t['ecart']), 'bad'];
    }
    unset($t);

    $top = Db::all("SELECT p.designation, (SELECT alias FROM produit_alias a WHERE a.produit_id = p.id ORDER BY a.id LIMIT 1) alias, SUM(vl.quantite) q
        FROM vente_lignes vl JOIN ventes v ON v.id = vl.vente_id JOIN produits p ON p.id = vl.produit_id
        WHERE v.statut = 'payee' AND v.at >= ? $wvs GROUP BY p.id ORDER BY q DESC LIMIT 5", [date('Y-m-d', strtotime('-30 days'))]);

    $kpi4 = null;
    if ($u['role'] === 'proprietaire') {
        $du = 0; $nf = 0;
        foreach (Db::all('SELECT id FROM fournisseurs WHERE actif = 1') as $f) { $d = du_fournisseur((int) $f['id']); if ($d > 0) { $du += $d; $nf++; } }
        $kpi4 = ['À payer aux fournisseurs', $du, $nf . ' fournisseur' . ($nf > 1 ? 's' : '') . ' · espèces en main ' . fmt(especes_proprietaire()) . ' F'];
    } else {
        $cr = array_sum(array_column(creances($site), 'du'));
        $kpi4 = ['Dû par les clients à crédit', $cr, 'Clients autorisés de l\'officine'];
    }

    view('admin/dashboard', compact('u', 'site', 'ca', 'delta', 'cash', 'ouvertes', 'sessions', 'ecarts', 'jours', 'max', 'todo', 'tills', 'top', 'kpi4') + ['title' => 'Tableau de bord']);
}
