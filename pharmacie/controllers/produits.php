<?php
declare(strict_types=1);

const FORMES = ['Comprimé', 'Gélule', 'Sirop', 'Suspension', 'Sachet', 'Injectable', 'Pommade', 'Collyre', 'Solution', 'Crème', 'Autre'];
const CATEGORIES = ['Médicament', 'Parapharmacie', 'Matériel médical', 'Hygiène'];

function statut_produit(array $p): array
{
    if (!$p['actif']) return ['stopped', 'Arrêté', 'mute'];
    if ($p['prix_boite'] === null) return ['noprice', 'Sans prix · non vendable', 'warn'];
    if ($p['a_verifier']) return ['check', 'À vérifier · créé par un gérant', 'info'];
    return ['ok', 'En vente', 'ok'];
}

function index(): void
{
    $u = Auth::requirePerm('produits.voir');
    $f = (string) input('f', 'all');
    $q = trim((string) input('q'));
    $all = Db::all('SELECT p.*, u.nom auteur, u.role auteur_role, (SELECT GROUP_CONCAT(alias SEPARATOR " ") FROM produit_alias a WHERE a.produit_id = p.id) alias
        FROM produits p LEFT JOIN users u ON u.id = p.created_by ORDER BY (p.prix_boite IS NULL AND p.actif = 1) DESC, p.a_verifier DESC, p.designation');
    $count = ['all' => 0, 'check' => 0, 'noprice' => 0, 'ok' => 0, 'stopped' => 0];
    $rows = [];
    foreach ($all as $p) {
        $st = statut_produit($p);
        $count['all']++;
        $count[$st[0]]++;
        if ($f !== 'all' && $f !== $st[0]) continue;
        if ($q !== '' && mb_stripos(norm($p['designation'] . ' ' . $p['dci'] . ' ' . $p['alias'] . ' ' . $p['code_barre']), norm($q)) === false) continue;
        $p['st'] = $st;
        $rows[] = $p;
    }
    view('admin/produits', ['title' => 'Produits', 'u' => $u, 'rows' => $rows, 'count' => $count, 'f' => $f, 'q' => $q]);
}

function recherche(): void
{
    Auth::requirePerm('produits.voir');
    $q = trim((string) input('q'));
    if (mb_strlen($q) < 2) json_out([]);
    $words = array_filter(preg_split('/\s+/', norm($q)));
    $out = [];
    $voirPrix = Auth::role() !== 'magasinier';
    foreach (Db::all('SELECT p.*, (SELECT GROUP_CONCAT(alias SEPARATOR " ") FROM produit_alias a WHERE a.produit_id = p.id) alias FROM produits p WHERE p.actif = 1 ORDER BY p.designation') as $p) {
        $hay = norm($p['designation'] . ' ' . $p['dci'] . ' ' . $p['alias'] . ' ' . $p['code_barre']);
        foreach ($words as $w) if (!str_contains($hay, $w)) continue 2;
        $out[] = ['id' => (int) $p['id'], 'designation' => $p['designation'], 'dci' => $p['dci'], 'forme' => $p['forme'], 'alias' => $p['alias'],
            'prix_achat' => $voirPrix ? $p['prix_achat'] : null, 'fractions' => (int) $p['fractions'], 'detail' => $p['detail_nom']];
        if (count($out) >= 15) break;
    }
    json_out($out);
}

function fiche(): void
{
    $u = Auth::requirePerm('produits.voir');
    $p = Db::one('SELECT p.*, u.nom auteur FROM produits p LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ?', [input_int('id')]);
    if (!$p) not_found();
    $monSite = $u['role'] === 'proprietaire' ? null : (int) $u['site_id'];
    $seuils = [];
    foreach (Db::all('SELECT * FROM seuils WHERE produit_id = ?', [$p['id']]) as $s) $seuils[(int) $s['site_id']] = (int) $s['seuil'];
    $vendu = (int) Db::val("SELECT COALESCE(SUM(vl.fractions), 0) FROM vente_lignes vl JOIN ventes v ON v.id = vl.vente_id WHERE vl.produit_id = ? AND v.statut = 'payee' AND v.at >= ?" . ($monSite ? ' AND v.site_id = ' . $monSite : ''), [$p['id'], date('Y-m-d', strtotime('-30 days'))]);
    $dernierBC = Db::one("SELECT c.numero, cl.prix_achat FROM commande_lignes cl JOIN commandes c ON c.id = cl.commande_id WHERE cl.produit_id = ? AND c.statut <> 'annulee' ORDER BY c.created_at DESC LIMIT 1", [$p['id']]);
    view('admin/produit', [
        'title' => $p['designation'], 'u' => $u, 'p' => $p, 'st' => statut_produit($p), 'monSite' => $monSite,
        'alias' => Db::all('SELECT * FROM produit_alias WHERE produit_id = ? ORDER BY id', [$p['id']]),
        'lots' => Stock::lots((int) $p['id'], $monSite),
        'seuils' => $seuils,
        'vj' => $vendu / max(1, (int) $p['fractions']) / 30,
        'dernierBC' => $dernierBC,
        'fournisseurs' => Db::all('SELECT * FROM fournisseurs ORDER BY code'),
    ]);
}

/** Lit et vérifie les champs du formulaire produit (création ou modification) */
function lire_produit(array $u, ?array $existant): array
{
    $err = [];
    $d = [];
    $owner = $u['role'] === 'proprietaire';
    $creation = $existant === null;
    if ($owner || $creation) {
        $d['designation'] = mb_strtoupper(trim((string) input('designation')));
        $d['dci'] = trim((string) input('dci')) ?: null;
        $d['dosage'] = trim((string) input('dosage')) ?: null;
        $d['forme'] = in_array(input('forme'), FORMES, true) ? input('forme') : '';
        $d['categorie'] = in_array(input('categorie'), CATEGORIES, true) ? input('categorie') : 'Médicament';
        $d['classe'] = trim((string) input('classe')) ?: null;
        $d['code_barre'] = preg_replace('/\s+/', '', (string) input('code_barre')) ?: null;
        $d['ordonnance'] = input('ordonnance') ? 1 : 0;
        $d['contenance'] = max(0, input_int('contenance'));
        $d['unite_base'] = trim((string) input('unite_base')) ?: 'unités';
        $detail = (bool) input('detail');
        $d['detail_nom'] = $detail ? (trim((string) input('detail_nom')) ?: 'Plaquette') : null;
        $d['detail_contenance'] = $detail ? max(0, input_int('detail_contenance')) : null;
        if (mb_strlen($d['designation']) < 3) $err['designation'] = 'Indiquez la désignation du produit, comme sur la boîte.';
        elseif (Db::val('SELECT id FROM produits WHERE UPPER(designation) = ? AND id <> ?', [$d['designation'], $existant['id'] ?? 0])) $err['designation'] = 'Ce produit existe déjà dans le catalogue. Ouvrez sa fiche plutôt que de créer un doublon.';
        if ($d['forme'] === '') $err['forme'] = 'Choisissez la forme : elle sert à la recherche et à l\'étiquette du ticket.';
        if ($d['contenance'] <= 0) $err['contenance'] = 'Indiquez le contenu d\'une boîte.';
        if ($detail) {
            if ($d['detail_contenance'] <= 0) $err['detail'] = 'Indiquez combien de ' . $d['unite_base'] . ' contient une ' . mb_strtolower($d['detail_nom']) . '.';
            elseif ($d['contenance'] % $d['detail_contenance'] !== 0) $err['detail'] = $d['contenance'] . ' n\'est pas divisible par ' . $d['detail_contenance'] . ' : vérifiez le nombre de ' . $d['unite_base'] . ' par ' . mb_strtolower($d['detail_nom']) . '.';
        }
        if (!$err && $existant && (int) $existant['fractions'] !== ($detail && $d['detail_contenance'] ? intdiv($d['contenance'], $d['detail_contenance']) : 1)
            && (int) Db::val('SELECT COALESCE(SUM(quantite), 0) FROM lots WHERE produit_id = ?', [$existant['id']]) > 0) {
            $err['detail'] = 'Le conditionnement ne peut pas changer tant qu\'il reste du stock de ce produit.';
        }
        $d['fractions'] = $detail && !isset($err['detail']) && $d['detail_contenance'] ? intdiv($d['contenance'], $d['detail_contenance']) : 1;
    }
    // Prix de vente : le propriétaire toujours, le gérant à la création, jamais le magasinier
    if ($owner || ($creation && $u['role'] === 'gerant')) {
        $pb = (string) input('prix_boite');
        $pd = (string) input('prix_detail');
        $d['prix_boite'] = $pb === '' ? null : (int) preg_replace('/\D/', '', $pb);
        $d['prix_detail'] = ($d['detail_nom'] ?? $existant['detail_nom'] ?? null) && $pd !== '' ? (int) preg_replace('/\D/', '', $pd) : null;
        if ($u['role'] === 'gerant' && !$d['prix_boite']) $err['prix'] = 'Indiquez le prix de vente : sans lui, le produit ne peut pas être vendu.';
        if (($d['detail_nom'] ?? $existant['detail_nom'] ?? null) && $d['prix_boite'] && !$d['prix_detail']) $err['prix'] = 'Indiquez aussi le prix de la ' . mb_strtolower($d['detail_nom'] ?? $existant['detail_nom']) . '.';
    }
    if ($owner) {
        $pa = (string) input('prix_achat');
        if ($pa !== '') $d['prix_achat'] = (int) preg_replace('/\D/', '', $pa);
        $d['fournisseur_id'] = input_int('fournisseur_id') ?: null;
        if (!$creation) $d['actif'] = input('actif') ? 1 : 0;
    } elseif ($creation) {
        $d['fournisseur_id'] = input_int('fournisseur_id') ?: null;
    }
    return [$d, $err];
}

function enregistrer_seuils_alias(array $u, int $pid): void
{
    foreach ((array) ($_POST['seuil'] ?? []) as $sid => $v) {
        $sid = (int) $sid;
        if (!site($sid) || ($u['role'] !== 'proprietaire' && $sid !== (int) $u['site_id'])) continue;
        Db::run('INSERT INTO seuils (produit_id, site_id, seuil) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE seuil = VALUES(seuil)', [$pid, $sid, max(0, (int) $v)]);
    }
    if (isset($_POST['alias'])) {
        Db::run('DELETE FROM produit_alias WHERE produit_id = ?', [$pid]);
        foreach (array_unique(array_filter(array_map(fn($a) => mb_strtoupper(trim((string) $a)), (array) $_POST['alias']))) as $a) {
            Db::insert('produit_alias', ['produit_id' => $pid, 'alias' => mb_substr($a, 0, 80)]);
        }
    }
}

function nouveau(): void
{
    $u = Auth::requirePerm('produits.creer');
    $err = [];
    $old = $_POST;
    if (is_post()) {
        [$d, $err] = lire_produit($u, null);
        if (!$err) {
            $d += ['created_by' => $u['id'], 'created_at' => now(), 'a_verifier' => $u['role'] === 'gerant' ? 1 : 0];
            if ($u['role'] === 'magasinier') { $d['prix_boite'] = null; $d['prix_detail'] = null; }
            $id = Db::tx(function () use ($u, $d) {
                $id = Db::insert('produits', $d);
                enregistrer_seuils_alias($u, $id);
                foreach (sites() as $s) Db::run('INSERT IGNORE INTO seuils (produit_id, site_id, seuil) VALUES (?, ?, ?)', [$id, $s['id'], $s['type'] === 'magasin' ? 10 : 2]);
                return $id;
            });
            audit('produit_cree', $d['designation'] . ($u['role'] === 'magasinier' ? ' · sans prix' : ($u['role'] === 'gerant' ? ' · à vérifier par le propriétaire' : '')));
            view('admin/produit-cree', ['title' => 'Produit ajouté', 'u' => $u, 'nom' => $d['designation'], 'id' => $id]);
        }
    }
    view('admin/produit-nouveau', ['title' => 'Nouveau produit', 'u' => $u, 'err' => $err, 'old' => $old,
        'fournisseurs' => Db::all('SELECT * FROM fournisseurs WHERE actif = 1 ORDER BY code')]);
}

function enregistrer(): void
{
    $u = Auth::requirePerm('produits.voir');
    $p = Db::one('SELECT * FROM produits WHERE id = ?', [input_int('id')]);
    if (!$p) not_found();
    [$d, $err] = lire_produit($u, $p);
    if ($err) {
        flash('bad', 'Le produit n\'est pas enregistré : ' . implode(' ', $err));
        redirect('produit', ['id' => $p['id']]);
    }
    Db::tx(function () use ($u, $p, $d) {
        if ($d) Db::update('produits', $d, 'id = ?', [$p['id']]);
        enregistrer_seuils_alias($u, (int) $p['id']);
    });
    if (isset($d['prix_boite']) && ((int) $d['prix_boite'] !== (int) $p['prix_boite'] || (int) ($d['prix_detail'] ?? 0) !== (int) $p['prix_detail'])) {
        audit('prix_modifie', $p['designation'] . ' · boîte ' . fmt($p['prix_boite']) . ' → ' . fmt($d['prix_boite']) . ($p['detail_nom'] ? ' · détail ' . fmt($p['prix_detail']) . ' → ' . fmt($d['prix_detail'] ?? null) : ''));
    }
    flash('ok', 'Fiche enregistrée.');
    redirect('produit', ['id' => $p['id']]);
}

function marquerVerifie(): void
{
    Auth::requirePerm('produits.prix');
    $p = Db::one('SELECT * FROM produits WHERE id = ?', [input_int('id')]);
    if ($p) {
        Db::update('produits', ['a_verifier' => 0], 'id = ?', [$p['id']]);
        audit('produit_verifie', $p['designation']);
        flash('ok', 'Produit vérifié.');
    }
    redirect('produit', ['id' => input_int('id')]);
}
