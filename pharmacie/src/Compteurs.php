<?php
declare(strict_types=1);

/** Pastilles de la navigation : ce qui attend une action */
function nav_compteurs(array $u): array
{
    $site = $u['role'] === 'proprietaire' ? null : (int) $u['site_id'];
    $w = $site ? ' AND site_id = ' . $site : '';
    $wl = $site ? ' AND l.site_id = ' . $site : '';
    return [
        'caisses' => (int) Db::val("SELECT COUNT(*) FROM caisse_sessions WHERE statut IN ('cloturee') $w"),
        'perimes' => (int) Db::val('SELECT COUNT(DISTINCT l.produit_id) FROM lots l WHERE l.quantite > 0 AND ' . Stock::PERIME . $wl),
        'produits' => $u['role'] === 'proprietaire' ? (int) Db::val('SELECT COUNT(*) FROM produits WHERE actif = 1 AND (a_verifier = 1 OR prix_boite IS NULL)') : 0,
        'commandes' => $u['role'] === 'proprietaire' ? (int) Db::val("SELECT COUNT(*) FROM commandes WHERE statut = 'proposee'") : 0,
        'transferts' => (int) Db::val("SELECT COUNT(*) FROM transferts WHERE statut = 'en_route'" . ($site ? ' AND site_to = ' . $site : '')),
        'bloques' => $u['role'] === 'proprietaire' ? (int) Db::val('SELECT COUNT(*) FROM users WHERE locked_until > ?', [now()]) : 0,
        'creances' => $u['role'] === 'proprietaire' ? count(array_filter(creances(), fn($c) => ($c['age'] ?? 0) > 30)) : 0,
    ];
}

/** Valeur reçue (factures des réceptions validées) moins les paiements, par fournisseur ou par commande */
function du_fournisseur(int $fournisseurId): int
{
    $recu = (int) Db::val("SELECT COALESCE(SUM(rl.quantite * COALESCE(rl.prix_facture, 0)), 0) FROM reception_lignes rl JOIN receptions r ON r.id = rl.reception_id
        WHERE r.statut = 'validee' AND r.fournisseur_id = ?", [$fournisseurId]);
    $paye = (int) Db::val('SELECT COALESCE(SUM(montant), 0) FROM paiements_fournisseurs WHERE fournisseur_id = ?', [$fournisseurId]);
    return $recu - $paye;
}

function valeur_recue_commande(int $commandeId): int
{
    return (int) Db::val("SELECT COALESCE(SUM(rl.quantite * COALESCE(rl.prix_facture, 0)), 0) FROM reception_lignes rl JOIN receptions r ON r.id = rl.reception_id
        WHERE r.statut = 'validee' AND r.commande_id = ?", [$commandeId]);
}

function paye_commande(int $commandeId): int
{
    return (int) Db::val('SELECT COALESCE(SUM(montant), 0) FROM paiements_fournisseurs WHERE commande_id = ?', [$commandeId]);
}

/** Espèces détenues par le propriétaire */
function especes_proprietaire(): int
{
    return (int) Db::val('SELECT COALESCE(SUM(montant), 0) FROM mouvements_especes');
}

function du_client_total(int $clientId): int
{
    $credit = (int) Db::val("SELECT COALESCE(SUM(total), 0) FROM ventes WHERE client_id = ? AND mode = 'credit' AND statut = 'payee'", [$clientId]);
    $paye = (int) Db::val('SELECT COALESCE(SUM(montant), 0) FROM creance_paiements WHERE client_id = ?', [$clientId]);
    return $credit - $paye;
}

/** Créances : dû, plus ancienne vente à crédit non couverte (en jours) */
function creances(?int $siteId = null): array
{
    $sql = 'SELECT c.* FROM clients c' . ($siteId ? ' WHERE c.site_id = ' . $siteId : '') . ' ORDER BY c.nom';
    $out = [];
    foreach (Db::all($sql) as $c) {
        $du = du_client_total((int) $c['id']);
        // ancienneté : on impute les remboursements aux ventes les plus anciennes
        $paye = (int) Db::val('SELECT COALESCE(SUM(montant), 0) FROM creance_paiements WHERE client_id = ?', [$c['id']]);
        $age = null;
        foreach (Db::all("SELECT total, at FROM ventes WHERE client_id = ? AND mode = 'credit' AND statut = 'payee' ORDER BY at", [$c['id']]) as $v) {
            if ($paye >= (int) $v['total']) { $paye -= (int) $v['total']; continue; }
            $age = (int) floor((time() - strtotime($v['at'])) / 86400);
            break;
        }
        $c['du'] = $du;
        $c['age'] = $du > 0 ? $age : null;
        $out[] = $c;
    }
    return $out;
}
