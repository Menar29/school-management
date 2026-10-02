<?php
declare(strict_types=1);

function index(): void
{
    $u = Auth::requirePerm('receptions');
    $ws = $u['role'] === 'proprietaire' ? '' : ' AND c.site_id = ' . (int) $u['site_id'];
    $attendues = Db::all("SELECT c.*, f.nom f_nom, (SELECT COUNT(*) FROM commande_lignes cl WHERE cl.commande_id = c.id AND cl.recu < cl.quantite) restants
        FROM commandes c JOIN fournisseurs f ON f.id = c.fournisseur_id WHERE c.statut IN ('creee', 'envoyee', 'partielle') $ws ORDER BY c.created_at");
    $wr = $u['role'] === 'proprietaire' ? '' : ' WHERE r.site_id = ' . (int) $u['site_id'];
    $receptions = Db::all("SELECT r.*, f.nom f_nom, u.nom, c.numero cmd, (SELECT COUNT(*) FROM reception_lignes rl WHERE rl.reception_id = r.id) nb,
        (SELECT COALESCE(SUM(rl.quantite * COALESCE(rl.prix_facture, 0)), 0) FROM reception_lignes rl WHERE rl.reception_id = r.id) valeur
        FROM receptions r JOIN fournisseurs f ON f.id = r.fournisseur_id JOIN users u ON u.id = r.user_id LEFT JOIN commandes c ON c.id = r.commande_id
        $wr ORDER BY r.created_at DESC LIMIT 100");
    view('admin/receptions', ['title' => 'Réceptions', 'u' => $u, 'attendues' => $attendues, 'receptions' => $receptions,
        'fournisseurs' => Db::all('SELECT * FROM fournisseurs WHERE actif = 1 ORDER BY code')]);
}

function formulaire(): void
{
    $u = Auth::requirePerm('receptions');
    $rec = null;
    $cmd = null;
    $lignes = [];
    if ($id = input_int('id')) {
        $rec = Db::one("SELECT * FROM receptions WHERE id = ?", [$id]);
        if (!$rec || ($u['role'] !== 'proprietaire' && (int) $rec['site_id'] !== (int) $u['site_id'])) not_found();
        if ($rec['statut'] === 'validee') {
            $lignes = Db::all('SELECT rl.*, p.designation, p.prix_achat ref_prix FROM reception_lignes rl JOIN produits p ON p.id = rl.produit_id WHERE rl.reception_id = ?', [$rec['id']]);
            view('admin/reception-voir', ['title' => 'Réception ' . $rec['numero'], 'u' => $u, 'r' => $rec, 'lignes' => $lignes,
                'f' => Db::one('SELECT * FROM fournisseurs WHERE id = ?', [$rec['fournisseur_id']]), 'cmd' => $rec['commande_id'] ? Db::one('SELECT numero FROM commandes WHERE id = ?', [$rec['commande_id']]) : null]);
        }
        $cmd = $rec['commande_id'] ? Db::one('SELECT * FROM commandes WHERE id = ?', [$rec['commande_id']]) : null;
        foreach (Db::all('SELECT rl.*, p.designation, p.fractions, cl.prix_achat prix_cmd FROM reception_lignes rl JOIN produits p ON p.id = rl.produit_id LEFT JOIN commande_lignes cl ON cl.id = rl.commande_ligne_id WHERE rl.reception_id = ?', [$rec['id']]) as $l) {
            $lignes[] = ['produit_id' => $l['produit_id'], 'designation' => $l['designation'], 'cl' => $l['commande_ligne_id'], 'attendu' => $l['attendu'],
                'q' => $l['quantite'], 'lot' => $l['numero_lot'], 'per' => $l['peremption'] ? mois_annee($l['peremption']) : '', 'prix' => $l['prix_facture'], 'prix_cmd' => $l['prix_cmd']];
        }
    } elseif ($cid = input_int('commande')) {
        $cmd = Db::one("SELECT * FROM commandes WHERE id = ? AND statut IN ('creee', 'envoyee', 'partielle')", [$cid]);
        if (!$cmd || ($u['role'] !== 'proprietaire' && (int) $cmd['site_id'] !== (int) $u['site_id'])) not_found('Commande introuvable ou déjà livrée.');
        foreach (Db::all('SELECT cl.*, p.designation FROM commande_lignes cl JOIN produits p ON p.id = cl.produit_id WHERE cl.commande_id = ? AND cl.recu < cl.quantite ORDER BY p.designation', [$cid]) as $l) {
            $rest = (int) $l['quantite'] - (int) $l['recu'];
            $lignes[] = ['produit_id' => $l['produit_id'], 'designation' => $l['designation'], 'cl' => $l['id'], 'attendu' => $rest, 'q' => $rest, 'lot' => '', 'per' => '', 'prix' => $l['prix_achat'], 'prix_cmd' => $l['prix_achat']];
        }
    }
    $fid = $rec['fournisseur_id'] ?? $cmd['fournisseur_id'] ?? input_int('fournisseur');
    view('admin/reception', [
        'title' => 'Réceptionner une livraison', 'u' => $u, 'rec' => $rec, 'cmd' => $cmd, 'lignes' => $lignes,
        'fournisseur' => $fid ? Db::one('SELECT * FROM fournisseurs WHERE id = ?', [$fid]) : null,
        'fournisseurs' => Db::all('SELECT * FROM fournisseurs WHERE actif = 1 ORDER BY code'),
        'dest' => (int) ($rec['site_id'] ?? $cmd['site_id'] ?? ($u['site_id'] ?: array_key_first(array_filter(sites(), fn($s) => $s['type'] === 'magasin')))),
    ]);
}

function enregistrer(): void
{
    $u = Auth::requirePerm('receptions');
    $valider = input('action') === 'valider';
    $recId = input_int('id');
    $cmdId = input_int('commande') ?: null;
    $fid = input_int('fournisseur');
    $dest = $u['role'] === 'proprietaire' ? input_int('dest') : (int) $u['site_id'];
    $voirPrix = $u['role'] !== 'magasinier';
    $cmd = $cmdId ? Db::one('SELECT * FROM commandes WHERE id = ?', [$cmdId]) : null;
    if ($cmd) $fid = (int) $cmd['fournisseur_id'];
    if (!$fid || !site($dest)) {
        flash('bad', 'Choisissez le fournisseur et le stock de destination.');
        redirect('reception', $recId ? ['id' => $recId] : ($cmdId ? ['commande' => $cmdId] : []));
    }

    $L = [];
    $errors = [];
    foreach ((array) ($_POST['produit'] ?? []) as $i => $pid) {
        $p = Db::one('SELECT * FROM produits WHERE id = ?', [(int) $pid]);
        if (!$p) continue;
        $q = max(0, (int) ($_POST['q'][$i] ?? 0));
        $lot = trim((string) ($_POST['lot'][$i] ?? ''));
        $perTxt = trim((string) ($_POST['per'][$i] ?? ''));
        $per = parse_peremption($perTxt);
        $clId = (int) ($_POST['cl'][$i] ?? 0) ?: null;
        $cl = $clId ? Db::one('SELECT * FROM commande_lignes WHERE id = ? AND commande_id = ?', [$clId, $cmdId]) : null;
        $prix = $voirPrix ? (int) preg_replace('/\D/', '', (string) ($_POST['prix'][$i] ?? '')) : 0;
        if (!$prix) $prix = (int) ($cl['prix_achat'] ?? $p['prix_achat'] ?? 0);
        if ($valider && $q > 0) {
            if ($lot === '') $errors[] = $p['designation'] . ' : numéro de lot obligatoire';
            if (!$per) $errors[] = $p['designation'] . ' : péremption au format MM/AAAA';
            elseif (peremption_fin($per) < today()) $errors[] = $p['designation'] . ' : lot déjà périmé, refusez-le au livreur';
        }
        $L[] = ['p' => $p, 'q' => $q, 'lot' => $lot, 'per' => $per, 'perTxt' => $perTxt, 'prix' => $prix, 'cl' => $cl, 'attendu' => (int) ($_POST['attendu'][$i] ?? 0)];
    }
    if ($valider && !array_filter($L, fn($l) => $l['q'] > 0)) $errors[] = 'Aucune quantité reçue';
    if ($valider && $errors) {
        flash('bad', 'La réception n\'est pas validée. ' . implode(' · ', $errors) . '.');
        $valider = false; // on garde la saisie en brouillon
    }

    $recId = Db::tx(function () use ($u, $recId, $cmdId, $fid, $dest, $L, $valider) {
        $data = [
            'commande_id' => $cmdId, 'fournisseur_id' => $fid, 'site_id' => $dest,
            'bl_numero' => trim((string) input('bl')) ?: null,
            'date_livraison' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) input('date')) ? input('date') : today(),
            'montant_facture' => input('facture') !== '' ? input_int('facture') : null,
        ];
        if ($recId) {
            $r = Db::one("SELECT * FROM receptions WHERE id = ? AND statut = 'brouillon'", [$recId]);
            if (!$r) throw new RuntimeException('Réception déjà validée.');
            Db::update('receptions', $data, 'id = ?', [$recId]);
            Db::run('DELETE FROM reception_lignes WHERE reception_id = ?', [$recId]);
        } else {
            $recId = Db::insert('receptions', $data + ['statut' => 'brouillon', 'user_id' => $u['id'], 'created_at' => now()]);
            Db::update('receptions', ['numero' => numero('R', $recId)], 'id = ?', [$recId]);
        }
        foreach ($L as $l) {
            Db::insert('reception_lignes', [
                'reception_id' => $recId, 'produit_id' => $l['p']['id'], 'commande_ligne_id' => $l['cl']['id'] ?? null,
                'attendu' => $l['attendu'], 'quantite' => $l['q'], 'numero_lot' => $l['lot'] ?: null, 'peremption' => $l['per'], 'prix_facture' => $l['prix'] ?: null,
            ]);
        }
        if (!$valider) return $recId;

        $num = numero('R', $recId);
        foreach ($L as $l) {
            if ($l['q'] <= 0) continue;
            $p = $l['p'];
            Stock::entrer((int) $p['id'], $dest, $l['lot'], $l['per'], $l['q'] * (int) $p['fractions'], $l['prix'] ?: null, 'reception', $num);
            if ($l['cl']) Db::run('UPDATE commande_lignes SET recu = recu + ? WHERE id = ?', [$l['q'], $l['cl']['id']]);
            if ($l['prix'] && (int) $l['prix'] !== (int) $p['prix_achat']) {
                Db::update('produits', ['prix_achat_prec' => $p['prix_achat'], 'prix_achat' => $l['prix']], 'id = ?', [$p['id']]);
            }
        }
        Db::update('receptions', ['statut' => 'validee', 'validee_at' => now(), 'user_id' => $u['id']], 'id = ?', [$recId]);
        if ($cmdId) {
            $reste = (int) Db::val('SELECT COUNT(*) FROM commande_lignes WHERE commande_id = ? AND recu < quantite', [$cmdId]);
            Db::update('commandes', $reste ? ['statut' => 'partielle'] : ['statut' => 'livree', 'livree_at' => now()], 'id = ?', [$cmdId]);
        }
        return $recId;
    });

    if ($valider) {
        audit('reception_validee', numero('R', $recId) . ' · ' . count(array_filter($L, fn($l) => $l['q'] > 0)) . ' produit(s)', $dest);
        flash('ok', 'Réception validée : le stock de ' . site($dest)['nom'] . ' est à jour.');
        redirect($cmdId ? 'commande' : 'receptions', $cmdId ? ['id' => $cmdId] : []);
    }
    if (!$errors) flash('ok', 'Brouillon enregistré.');
    redirect('reception', ['id' => $recId]);
}
