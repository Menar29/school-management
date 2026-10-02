<?php
declare(strict_types=1);

const TYPES_MVT = [
    'vente' => 'Vente', 'annulation' => 'Vente annulée', 'reception' => 'Réception', 'transfert_out' => 'Transfert envoyé',
    'transfert_in' => 'Transfert reçu', 'ajustement' => 'Ajustement', 'inventaire' => 'Inventaire', 'retrait' => 'Retrait périmé',
];

/** Peut modifier le stock de ce site ? */
function peut_gerer_site(array $u, int $siteId): bool
{
    return Perm::can($u, 'stock.gerer') && ($u['role'] === 'proprietaire' || (int) $u['site_id'] === $siteId);
}

function index(): void
{
    $u = Auth::requirePerm('stock.voir');
    $f = (string) input('f', 'all');
    $q = trim((string) input('q'));
    $sites = sites();
    $monSite = $u['role'] === 'proprietaire' ? null : (int) $u['site_id'];

    $qte = [];
    $perime = [];
    $premier = [];
    $valeur = [];
    foreach (Db::all('SELECT l.produit_id, l.site_id, l.quantite, l.peremption, l.prix_achat, ' . Stock::PERIME . ' AS perime FROM lots l WHERE l.quantite > 0') as $l) {
        $pid = (int) $l['produit_id'];
        if ($l['perime']) { $perime[$pid] = ($perime[$pid] ?? 0) + (int) $l['quantite']; continue; }
        $qte[$pid][(int) $l['site_id']] = ($qte[$pid][(int) $l['site_id']] ?? 0) + (int) $l['quantite'];
        if ($l['peremption'] && (!isset($premier[$pid]) || $l['peremption'] < $premier[$pid])) $premier[$pid] = $l['peremption'];
        $valeur[$pid] = ($valeur[$pid] ?? 0) + (int) $l['quantite'] * (int) $l['prix_achat'];
    }
    $seuils = [];
    foreach (Db::all('SELECT * FROM seuils') as $s) $seuils[(int) $s['produit_id']][(int) $s['site_id']] = (int) $s['seuil'];
    $alias = [];
    foreach (Db::all('SELECT produit_id, GROUP_CONCAT(alias SEPARATOR " ") a FROM produit_alias GROUP BY produit_id') as $a) $alias[(int) $a['produit_id']] = $a['a'];
    $lotsQ = [];
    if ($q !== '') foreach (Db::all('SELECT DISTINCT produit_id FROM lots WHERE numero LIKE ?', ['%' . $q . '%']) as $l) $lotsQ[(int) $l['produit_id']] = true;

    $rows = [];
    $kpi = ['valeur' => 0, 'refs' => 0, 'avec' => 0, 'soon' => 0, 'exp' => 0];
    $soonLots = (int) Db::val('SELECT COUNT(DISTINCT l.produit_id) FROM lots l WHERE l.quantite > 0 AND ' . Stock::BIENTOT . ($monSite ? ' AND l.site_id = ' . $monSite : ''));
    $count = ['all' => 0, 'order' => 0, 'soon' => 0, 'exp' => 0, 'out' => 0];
    foreach (Db::all('SELECT * FROM produits WHERE actif = 1 ORDER BY designation') as $p) {
        $pid = (int) $p['id'];
        $fr = max(1, (int) $p['fractions']);
        $parSite = $qte[$pid] ?? [];
        $scope = $monSite ? [$monSite => $parSite[$monSite] ?? 0] : $parSite;
        $total = array_sum($scope);
        $kpi['refs']++;
        if ($total > 0) $kpi['avec']++;
        $kpi['valeur'] += (int) round(($valeur[$pid] ?? 0) / $fr);
        $sous = false;
        foreach (($monSite ? [$monSite] : array_keys($sites)) as $sid) {
            if (($scope[$sid] ?? 0) / $fr < ($seuils[$pid][$sid] ?? 0)) $sous = true;
        }
        $perimeIci = $monSite ? (int) Db::val('SELECT COALESCE(SUM(l.quantite), 0) FROM lots l WHERE l.produit_id = ? AND l.site_id = ? AND l.quantite > 0 AND ' . Stock::PERIME, [$pid, $monSite]) : ($perime[$pid] ?? 0);
        $soon = isset($premier[$pid]) && strtotime($premier[$pid]) < strtotime('+6 months');
        $st = $perimeIci > 0 ? 'exp' : ($total <= 0 ? 'out' : ($sous ? 'order' : ($soon ? 'soon' : 'ok')));
        if ($st === 'exp') $kpi['exp']++;
        $count['all']++;
        if (isset($count[$st])) $count[$st]++;
        if ($f !== 'all' && $f !== $st) continue;
        if ($q !== '' && !isset($lotsQ[$pid]) && mb_stripos(norm($p['designation'] . ' ' . $p['dci'] . ' ' . ($alias[$pid] ?? '') . ' ' . $p['code_barre']), norm($q)) === false) continue;
        $rows[] = ['p' => $p, 'sites' => $parSite, 'perime' => $perimeIci, 'premier' => $premier[$pid] ?? null, 'valeur' => (int) round(($valeur[$pid] ?? 0) / $fr),
            'seuil' => $monSite ? ($seuils[$pid][$monSite] ?? 0) : array_sum($seuils[$pid] ?? []), 'st' => $st];
    }
    $kpi['soon'] = $soonLots;
    view('admin/stock', ['title' => 'Stock', 'u' => $u, 'rows' => $rows, 'f' => $f, 'q' => $q, 'kpi' => $kpi, 'count' => $count, 'monSite' => $monSite]);
}

function ajuster(): void
{
    $u = Auth::requirePerm('stock.gerer');
    $lot = Db::one('SELECT l.*, p.fractions, p.designation, p.detail_nom, p.forme, p.contenance FROM lots l JOIN produits p ON p.id = l.produit_id WHERE l.id = ?', [input_int('lot')]);
    if (!$lot || !peut_gerer_site($u, (int) $lot['site_id'])) forbidden();
    $motif = trim((string) input('motif'));
    $nouv = max(0, input_int('boites')) * (int) $lot['fractions'] + max(0, input_int('detail'));
    if ($motif === '') {
        flash('bad', 'Indiquez le motif de l\'ajustement (casse, erreur de saisie, vol...).');
        redirect('produit', ['id' => $lot['produit_id']]);
    }
    $diff = Db::tx(fn() => Stock::fixer((int) $lot['id'], $nouv, 'ajustement', 'AJ-' . date('ymd'), $motif));
    audit('stock_ajuste', $lot['designation'] . ' · lot ' . $lot['numero'] . ' · ' . site((int) $lot['site_id'])['nom_court'] . ' · ' . Stock::qte($lot, $diff) . ' · ' . $motif, (int) $lot['site_id']);
    flash('ok', 'Quantité ajustée (' . ($diff >= 0 ? '+' : '') . Stock::qte($lot, $diff) . ').');
    redirect('produit', ['id' => $lot['produit_id']]);
}

function inventaire(): void
{
    $u = Auth::requirePerm('stock.gerer');
    $siteId = $u['role'] === 'proprietaire' ? input_int('site') : (int) $u['site_id'];
    if (!site($siteId)) {
        view('admin/inventaire-choix', ['title' => 'Inventaire', 'u' => $u]);
    }
    if (is_post()) {
        $ref = 'INV-' . date('ymd-Hi');
        $diffs = 0;
        $n = 0;
        Db::tx(function () use ($siteId, $ref, &$diffs, &$n) {
            foreach ((array) ($_POST['lot'] ?? []) as $lotId => $v) {
                if (!is_array($v) || ($v['b'] ?? '') === '') continue;
                $lot = Db::one('SELECT l.*, p.fractions FROM lots l JOIN produits p ON p.id = l.produit_id WHERE l.id = ? AND l.site_id = ?', [(int) $lotId, $siteId]);
                if (!$lot) continue;
                $nouv = max(0, (int) $v['b']) * (int) $lot['fractions'] + max(0, (int) ($v['d'] ?? 0));
                $n++;
                if (Stock::fixer((int) $lot['id'], $nouv, 'inventaire', $ref, 'Inventaire') !== 0) $diffs++;
            }
        });
        audit('inventaire', site($siteId)['nom'] . ' · ' . $n . ' lot(s) comptés · ' . $diffs . ' écart(s) · ' . $ref, $siteId);
        flash('ok', 'Inventaire enregistré : ' . $n . ' lot(s) comptés, ' . $diffs . ' écart(s) corrigé(s).');
        redirect('stock');
    }
    $lots = Db::all('SELECT l.*, p.designation, p.fractions, p.detail_nom, p.forme, p.contenance, ' . Stock::PERIME . ' perime FROM lots l JOIN produits p ON p.id = l.produit_id
        WHERE l.site_id = ? AND (l.quantite > 0 OR l.created_at >= ?) ORDER BY p.designation, l.peremption', [$siteId, date('Y-m-d', strtotime('-90 days'))]);
    view('admin/inventaire', ['title' => 'Inventaire · ' . site($siteId)['nom'], 'u' => $u, 'site' => site($siteId), 'lots' => $lots]);
}

function retirerPerimes(): void
{
    $u = Auth::requirePerm('stock.gerer');
    $where = $u['role'] === 'proprietaire' ? (input_int('site') ? ' AND l.site_id = ' . input_int('site') : '') : ' AND l.site_id = ' . (int) $u['site_id'];
    $lots = Db::all('SELECT l.*, p.designation, p.fractions, p.detail_nom, p.forme, p.contenance FROM lots l JOIN produits p ON p.id = l.produit_id WHERE l.quantite > 0 AND ' . Stock::PERIME . $where);
    $valeur = 0;
    Db::tx(function () use ($lots, &$valeur) {
        foreach ($lots as $l) {
            $valeur += (int) round((int) $l['quantite'] * (int) $l['prix_achat'] / max(1, (int) $l['fractions']));
            Stock::fixer((int) $l['id'], 0, 'retrait', 'RET-' . date('ymd'), 'Lot périmé retiré du rayon');
        }
    });
    foreach ($lots as $l) audit('perime_retire', $l['designation'] . ' · lot ' . $l['numero'] . ' (' . mois_annee($l['peremption']) . ') · ' . Stock::qte($l, (int) $l['quantite']), (int) $l['site_id']);
    flash('ok', count($lots) . ' lot(s) périmé(s) retiré(s) du stock' . (Auth::can('prix_achat') ? ' · valeur d\'achat ' . fmt($valeur) . ' FCFA' : '') . '. Mettez les boîtes de côté pour destruction.');
    redirect('stock', ['f' => 'exp']);
}

function mouvements(): void
{
    $u = Auth::requirePerm('stock.voir');
    $p = Db::one('SELECT * FROM produits WHERE id = ?', [input_int('produit')]);
    if (!$p) not_found();
    $ws = $u['role'] === 'proprietaire' ? '' : ' AND m.site_id = ' . (int) $u['site_id'];
    $rows = Db::all('SELECT m.*, l.numero lot, u.nom FROM mouvements m LEFT JOIN lots l ON l.id = m.lot_id LEFT JOIN users u ON u.id = m.user_id WHERE m.produit_id = ?' . $ws . ' ORDER BY m.at DESC, m.id DESC LIMIT 500', [$p['id']]);
    view('admin/mouvements', ['title' => 'Mouvements · ' . $p['designation'], 'u' => $u, 'p' => $p, 'rows' => $rows]);
}
