<?php
declare(strict_types=1);

/**
 * Quantité suggérée = ventes par jour × (délai du fournisseur + 15 jours de réserve)
 *                     − stock des sites − quantités déjà en commande,
 * et au minimum de quoi remonter au seuil d'alerte.
 */
final class Reappro
{
    public const RESERVE_JOURS = 15;

    public static function suggestions(?int $siteId = null, ?int $fournisseurId = null): array
    {
        $ws = $siteId ? ' AND l.site_id = ' . $siteId : '';
        $wv = $siteId ? ' AND v.site_id = ' . $siteId : '';
        $stock = [];
        foreach (Db::all('SELECT l.produit_id, l.site_id, SUM(l.quantite) q FROM lots l WHERE l.quantite > 0 AND ' . Stock::NON_PERIME . $ws . ' GROUP BY l.produit_id, l.site_id') as $r) {
            $stock[(int) $r['produit_id']][(int) $r['site_id']] = (int) $r['q'];
        }
        $ventes = [];
        foreach (Db::all("SELECT vl.produit_id, SUM(vl.fractions) q FROM vente_lignes vl JOIN ventes v ON v.id = vl.vente_id
                WHERE v.statut = 'payee' AND v.at >= ? $wv GROUP BY vl.produit_id", [date('Y-m-d', strtotime('-30 days'))]) as $r) {
            $ventes[(int) $r['produit_id']] = (int) $r['q'];
        }
        $encmd = [];
        foreach (Db::all("SELECT cl.produit_id, SUM(GREATEST(cl.quantite - cl.recu, 0)) q FROM commande_lignes cl JOIN commandes c ON c.id = cl.commande_id
                WHERE c.statut IN ('proposee', 'creee', 'envoyee', 'partielle') GROUP BY cl.produit_id") as $r) {
            $encmd[(int) $r['produit_id']] = (int) $r['q'];
        }
        $seuils = [];
        foreach (Db::all('SELECT produit_id, SUM(seuil) s FROM seuils' . ($siteId ? ' WHERE site_id = ' . $siteId : '') . ' GROUP BY produit_id') as $r) {
            $seuils[(int) $r['produit_id']] = (int) $r['s'];
        }

        $sql = 'SELECT p.*, f.code AS f_code, f.delai_jours FROM produits p LEFT JOIN fournisseurs f ON f.id = p.fournisseur_id WHERE p.actif = 1';
        $params = [];
        if ($fournisseurId) { $sql .= ' AND p.fournisseur_id = ?'; $params[] = $fournisseurId; }
        $out = [];
        foreach (Db::all($sql . ' ORDER BY p.designation', $params) as $p) {
            $id = (int) $p['id'];
            $f = max(1, (int) $p['fractions']);
            $parSite = array_map(fn($q) => $q / $f, $stock[$id] ?? []);
            $st = array_sum($parSite);
            $vj = ($ventes[$id] ?? 0) / $f / 30;
            $delai = (int) ($p['delai_jours'] ?? 7);
            $enc = $encmd[$id] ?? 0;
            $seuil = $seuils[$id] ?? 0;
            $besoin = max((int) ceil($vj * ($delai + self::RESERVE_JOURS)), $seuil);
            $sug = (int) max(0, ceil($besoin - $st - $enc));
            if ($sug <= 0) continue;
            $out[] = [
                'p' => $p, 'stock_sites' => $parSite, 'stock' => $st, 'vj' => $vj, 'delai' => $delai,
                'encmd' => $enc, 'seuil' => $seuil, 'qte' => $sug,
                'couverture' => $vj > 0 ? (int) floor($st / $vj) : null,
                'prix' => (int) ($p['prix_achat'] ?? 0),
            ];
        }
        return $out;
    }
}
