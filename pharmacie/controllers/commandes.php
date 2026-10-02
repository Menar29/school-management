<?php
declare(strict_types=1);


function reappro(): void
{
    $u = Auth::requirePerm('reappro');
    $site = $u['role'] === 'proprietaire' ? null : (int) $u['site_id'];
    $f = input_int('f') ?: null;
    $rows = Reappro::suggestions($site, $f);
    $fournisseurs = Db::all('SELECT * FROM fournisseurs WHERE actif = 1 ORDER BY code');
    view('admin/reappro', ['title' => 'Réapprovisionnement', 'u' => $u, 'rows' => $rows, 'f' => $f, 'fournisseurs' => $fournisseurs, 'site' => $site]);
}

function creerDepuisReappro(): void
{
    $u = Auth::requirePerm('commandes');
    $sel = array_map('intval', (array) ($_POST['sel'] ?? []));
    $qte = (array) ($_POST['qte'] ?? []);
    $groupes = [];
    foreach ($sel as $pid) {
        $q = (int) ($qte[$pid] ?? 0);
        if ($q <= 0) continue;
        $p = Db::one('SELECT * FROM produits WHERE id = ?', [$pid]);
        if (!$p || !$p['fournisseur_id']) continue;
        $groupes[(int) $p['fournisseur_id']][] = [$p, $q];
    }
    if (!$groupes) {
        flash('bad', 'Sélectionnez au moins un produit avec une quantité et un fournisseur habituel.');
        redirect('reappro');
    }
    $dest = $u['role'] === 'proprietaire' ? (int) (input_int('dest') ?: array_key_first(array_filter(sites(), fn($s) => $s['type'] === 'magasin')) ?: array_key_first(sites())) : (int) $u['site_id'];
    $ids = [];
    Db::tx(function () use ($groupes, $u, $dest, &$ids) {
        foreach ($groupes as $fid => $lignes) {
            $id = Db::insert('commandes', [
                'fournisseur_id' => $fid, 'site_id' => $dest, 'statut' => $u['role'] === 'proprietaire' ? 'creee' : 'proposee',
                'created_by' => $u['id'], 'created_at' => now(), 'notes' => 'Créée depuis le réapprovisionnement',
            ]);
            Db::update('commandes', ['numero' => numero('BC', $id)], 'id = ?', [$id]);
            foreach ($lignes as [$p, $q]) {
                Db::insert('commande_lignes', ['commande_id' => $id, 'produit_id' => $p['id'], 'quantite' => $q, 'prix_achat' => (int) ($p['prix_achat'] ?? 0)]);
            }
            $ids[] = $id;
        }
    });
    foreach ($ids as $id) audit('commande_creee', numero('BC', $id) . ($u['role'] === 'proprietaire' ? '' : ' · proposée par le gérant'), $dest);
    flash('ok', count($ids) > 1 ? count($ids) . ' bons de commande créés (un par fournisseur).' : 'Bon de commande créé.');
    redirect(count($ids) === 1 ? 'commande' : 'commandes', count($ids) === 1 ? ['id' => $ids[0]] : []);
}

function index(): void
{
    $u = Auth::requirePerm('commandes');
    $st = (string) input('st', 'encours');
    $where = $u['role'] === 'proprietaire' ? '1' : 'c.site_id = ' . (int) $u['site_id'];
    $where .= match ($st) {
        'encours' => " AND c.statut IN ('proposee', 'creee', 'envoyee', 'partielle')",
        'livree' => " AND c.statut = 'livree'",
        'annulee' => " AND c.statut = 'annulee'",
        default => '',
    };
    $rows = Db::all("SELECT c.*, f.code f_code, f.nom f_nom, COUNT(cl.id) nb, COALESCE(SUM(cl.quantite * cl.prix_achat), 0) montant, SUM(cl.recu < cl.quantite) manquants
        FROM commandes c JOIN fournisseurs f ON f.id = c.fournisseur_id LEFT JOIN commande_lignes cl ON cl.commande_id = c.id
        WHERE $where GROUP BY c.id ORDER BY c.created_at DESC LIMIT 200");
    foreach ($rows as &$r) {
        $r['recu_val'] = valeur_recue_commande((int) $r['id']);
        $r['paye'] = paye_commande((int) $r['id']);
    }
    unset($r);
    view('admin/commandes', ['title' => 'Commandes', 'u' => $u, 'rows' => $rows, 'st' => $st]);
}

function commande_visible(array $u, int $id): array
{
    $c = Db::one('SELECT c.*, f.code f_code, f.nom f_nom, f.conditions, f.delai_jours, f.telephone f_tel, f.contact f_contact, f.adresse f_adresse, u.nom auteur
        FROM commandes c JOIN fournisseurs f ON f.id = c.fournisseur_id JOIN users u ON u.id = c.created_by WHERE c.id = ?', [$id]);
    if (!$c || ($u['role'] !== 'proprietaire' && (int) $c['site_id'] !== (int) $u['site_id'])) not_found();
    return $c;
}

function voir(): void
{
    $u = Auth::requirePerm('commandes');
    $c = commande_visible($u, input_int('id'));
    $lignes = Db::all('SELECT cl.*, p.designation FROM commande_lignes cl JOIN produits p ON p.id = cl.produit_id WHERE cl.commande_id = ? ORDER BY p.designation', [$c['id']]);
    $receptions = Db::all('SELECT r.*, u.nom FROM receptions r JOIN users u ON u.id = r.user_id WHERE r.commande_id = ? ORDER BY r.created_at', [$c['id']]);
    $paiements = Db::all('SELECT p.*, u.nom FROM paiements_fournisseurs p JOIN users u ON u.id = p.user_id WHERE p.commande_id = ? ORDER BY p.at', [$c['id']]);
    view('admin/commande', [
        'title' => $c['numero'], 'u' => $u, 'c' => $c, 'lignes' => $lignes, 'receptions' => $receptions, 'paiements' => $paiements,
        'recu' => valeur_recue_commande((int) $c['id']), 'paye' => paye_commande((int) $c['id']),
    ]);
}

function nouvelle(): void
{
    $u = Auth::requirePerm('commandes');
    if (is_post()) {
        $fid = input_int('fournisseur');
        $dest = $u['role'] === 'proprietaire' ? input_int('dest') : (int) $u['site_id'];
        $pids = (array) ($_POST['produit'] ?? []);
        $qtes = (array) ($_POST['qte'] ?? []);
        $prix = (array) ($_POST['prix'] ?? []);
        $lignes = [];
        foreach ($pids as $i => $pid) {
            $q = (int) ($qtes[$i] ?? 0);
            $p = Db::one('SELECT * FROM produits WHERE id = ?', [(int) $pid]);
            if ($p && $q > 0) $lignes[(int) $p['id']] = [$p, $q, $u['role'] === 'proprietaire' ? (int) preg_replace('/\D/', '', (string) ($prix[$i] ?? '')) : (int) $p['prix_achat']];
        }
        if (!Db::one('SELECT id FROM fournisseurs WHERE id = ?', [$fid]) || !site($dest) || !$lignes) {
            flash('bad', 'Choisissez le fournisseur, le lieu de livraison et au moins un produit.');
            redirect('commande/nouvelle');
        }
        $id = Db::tx(function () use ($u, $fid, $dest, $lignes) {
            $id = Db::insert('commandes', ['fournisseur_id' => $fid, 'site_id' => $dest, 'statut' => $u['role'] === 'proprietaire' ? 'creee' : 'proposee', 'created_by' => $u['id'], 'created_at' => now(), 'notes' => trim((string) input('notes')) ?: null]);
            Db::update('commandes', ['numero' => numero('BC', $id)], 'id = ?', [$id]);
            foreach ($lignes as [$p, $q, $px]) Db::insert('commande_lignes', ['commande_id' => $id, 'produit_id' => $p['id'], 'quantite' => $q, 'prix_achat' => $px ?: (int) $p['prix_achat']]);
            return $id;
        });
        audit('commande_creee', numero('BC', $id), $dest);
        redirect('commande', ['id' => $id]);
    }
    view('admin/commande-nouvelle', [
        'title' => 'Nouvelle commande', 'u' => $u,
        'fournisseurs' => Db::all('SELECT * FROM fournisseurs WHERE actif = 1 ORDER BY code'),
        'produits' => Db::all('SELECT id, designation, prix_achat, fournisseur_id FROM produits WHERE actif = 1 ORDER BY designation'),
    ]);
}

function statut(): void
{
    $u = Auth::requirePerm('commandes');
    $c = commande_visible($u, input_int('id'));
    $a = (string) input('action');
    $ok = match ($a) {
        'valider' => $c['statut'] === 'proposee' && Auth::can('commandes.valider'),
        'envoyer' => $c['statut'] === 'creee' && Auth::can('commandes.valider'),
        'annuler' => in_array($c['statut'], ['proposee', 'creee', 'envoyee'], true) && ($u['role'] === 'proprietaire' || $c['statut'] === 'proposee'),
        'cloturer' => $c['statut'] === 'partielle' && Auth::can('commandes.valider'),
        default => false,
    };
    if (!$ok) {
        flash('bad', 'Action impossible pour cette commande.');
        redirect('commande', ['id' => $c['id']]);
    }
    $data = match ($a) {
        'valider' => ['statut' => 'creee'],
        'envoyer' => ['statut' => 'envoyee', 'envoyee_at' => now()],
        'annuler' => ['statut' => 'annulee'],
        'cloturer' => ['statut' => 'livree', 'livree_at' => now()],
    };
    Db::update('commandes', $data, 'id = ?', [$c['id']]);
    $lib = ['valider' => 'commande_validee', 'envoyer' => 'commande_envoyee', 'annuler' => 'commande_annulee', 'cloturer' => 'commande_soldee'][$a];
    audit($lib, $c['numero'], (int) $c['site_id']);
    flash('ok', [
        'valider' => 'Commande validée. Imprimez le bon et remettez-le au fournisseur.',
        'envoyer' => 'Commande marquée comme envoyée au fournisseur.',
        'annuler' => 'Commande annulée.',
        'cloturer' => 'Commande soldée : les produits manquants ne sont plus attendus.',
    ][$a]);
    redirect('commande', ['id' => $c['id']]);
}

function imprimer(): void
{
    $u = Auth::requirePerm('commandes');
    $c = commande_visible($u, input_int('id'));
    $lignes = Db::all('SELECT cl.*, p.designation FROM commande_lignes cl JOIN produits p ON p.id = cl.produit_id WHERE cl.commande_id = ? ORDER BY p.designation', [$c['id']]);
    view('admin/commande-print', ['title' => 'Bon de commande ' . $c['numero'], 'c' => $c, 'lignes' => $lignes, 'prix' => Auth::can('prix_achat')], 'blank');
}

function paiement(): void
{
    $u = Auth::requirePerm('fournisseurs');
    $c = commande_visible($u, input_int('id'));
    $m = input_int('montant');
    $reste = valeur_recue_commande((int) $c['id']) - paye_commande((int) $c['id']);
    if ($m <= 0 || $m > $reste) {
        flash('bad', 'Le montant doit être compris entre 1 et ' . fmt($reste) . ' FCFA (valeur reçue non encore payée).');
        redirect('commande', ['id' => $c['id']]);
    }
    Db::tx(function () use ($u, $c, $m) {
        Db::insert('paiements_fournisseurs', ['fournisseur_id' => $c['fournisseur_id'], 'commande_id' => $c['id'], 'at' => now(), 'montant' => $m, 'user_id' => $u['id'], 'note' => trim((string) input('note')) ?: 'Espèces']);
        Db::insert('mouvements_especes', ['at' => now(), 'type' => 'paiement_fournisseur', 'libelle' => 'Paiement fournisseur ' . $c['f_code'] . ' · ' . $c['numero'], 'montant' => -$m, 'ref' => $c['numero'], 'user_id' => $u['id']]);
    });
    audit('paiement_fournisseur', $c['numero'] . ' · ' . fmt($m) . ' FCFA', (int) $c['site_id']);
    flash('ok', 'Paiement de ' . fmt($m) . ' FCFA enregistré.');
    redirect('commande', ['id' => $c['id']]);
}
