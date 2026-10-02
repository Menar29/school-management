<?php
declare(strict_types=1);

/**
 * Stock par lot et par site. Les quantités sont en « fractions » (voir schema.sql).
 * Les sorties se font au premier périmé, premier sorti (FEFO) ; un lot périmé n'est jamais vendu.
 */
final class Stock
{
    /** Condition SQL : le lot n'est pas périmé (périme à la fin de son mois) */
    public const NON_PERIME = "(l.peremption IS NULL OR LAST_DAY(l.peremption) >= CURDATE())";
    public const PERIME = "(l.peremption IS NOT NULL AND LAST_DAY(l.peremption) < CURDATE())";
    public const BIENTOT = "(l.peremption IS NOT NULL AND LAST_DAY(l.peremption) >= CURDATE() AND l.peremption < DATE_ADD(CURDATE(), INTERVAL 6 MONTH))";

    /** Quantité vendable d'un produit sur un site, en fractions */
    public static function dispo(int $produitId, int $siteId): int
    {
        return (int) Db::val('SELECT COALESCE(SUM(l.quantite), 0) FROM lots l WHERE l.produit_id = ? AND l.site_id = ? AND l.quantite > 0 AND ' . self::NON_PERIME, [$produitId, $siteId]);
    }

    /** Lots ayant du stock, dans l'ordre de sortie */
    public static function lots(int $produitId, ?int $siteId = null, bool $avecPerimes = true): array
    {
        $sql = 'SELECT l.*, ' . self::PERIME . ' AS perime, ' . self::BIENTOT . ' AS bientot FROM lots l WHERE l.produit_id = ? AND l.quantite > 0';
        $p = [$produitId];
        if ($siteId) { $sql .= ' AND l.site_id = ?'; $p[] = $siteId; }
        if (!$avecPerimes) $sql .= ' AND ' . self::NON_PERIME;
        $sql .= ' ORDER BY (l.peremption IS NULL), l.peremption, l.id';
        return Db::all($sql, $p);
    }

    /**
     * Sort une quantité du stock (FEFO). Retourne la liste [lot_id, quantite, prix_achat].
     * Lève une exception si le stock vendable est insuffisant.
     */
    public static function sortir(int $produitId, int $siteId, int $qte, string $type, ?string $ref, ?string $motif = null, bool $forcer = false): array
    {
        if ($qte <= 0) return [];
        $lots = Db::all('SELECT l.* FROM lots l WHERE l.produit_id = ? AND l.site_id = ? AND l.quantite > 0 AND ' . self::NON_PERIME
            . ' ORDER BY (l.peremption IS NULL), l.peremption, l.id FOR UPDATE', [$produitId, $siteId]);
        $reste = $qte;
        $pris = [];
        foreach ($lots as $l) {
            if ($reste <= 0) break;
            $q = min($reste, (int) $l['quantite']);
            Db::run('UPDATE lots SET quantite = quantite - ? WHERE id = ?', [$q, $l['id']]);
            self::mouvement($produitId, (int) $l['id'], $siteId, $type, -$q, $ref, $motif);
            $pris[] = ['lot_id' => (int) $l['id'], 'quantite' => $q, 'prix_achat' => $l['prix_achat'] !== null ? (int) $l['prix_achat'] : null, 'numero' => $l['numero'], 'peremption' => $l['peremption']];
            $reste -= $q;
        }
        if ($reste > 0 && $forcer) {
            // Vente faite hors connexion alors que le stock affiché était en retard : on trace l'écart
            self::mouvement($produitId, null, $siteId, $type, -$reste, $ref, 'Stock insuffisant à la synchronisation (vente hors connexion)');
            $reste = 0;
        }
        if ($reste > 0) {
            $p = Db::one('SELECT designation FROM produits WHERE id = ?', [$produitId]);
            throw new RuntimeException('Stock insuffisant pour ' . ($p['designation'] ?? 'ce produit') . '.');
        }
        return $pris;
    }

    /** Ajoute une quantité dans un lot (créé s'il n'existe pas sur ce site) */
    public static function entrer(int $produitId, int $siteId, string $numero, ?string $peremption, int $qte, ?int $prixAchat, string $type, ?string $ref, ?string $motif = null): int
    {
        $lot = Db::one('SELECT id FROM lots WHERE produit_id = ? AND site_id = ? AND numero = ? AND (peremption <=> ?) FOR UPDATE', [$produitId, $siteId, $numero, $peremption]);
        if ($lot) {
            Db::run('UPDATE lots SET quantite = quantite + ?, prix_achat = COALESCE(?, prix_achat) WHERE id = ?', [$qte, $prixAchat, $lot['id']]);
            $lotId = (int) $lot['id'];
        } else {
            $lotId = Db::insert('lots', [
                'produit_id' => $produitId, 'site_id' => $siteId, 'numero' => $numero, 'peremption' => $peremption,
                'quantite' => $qte, 'prix_achat' => $prixAchat, 'created_at' => now(),
            ]);
        }
        self::mouvement($produitId, $lotId, $siteId, $type, $qte, $ref, $motif);
        return $lotId;
    }

    /** Remet des quantités dans des lots précis (annulation d'une vente) */
    public static function remettre(int $lotId, int $qte, string $type, ?string $ref, ?string $motif = null): void
    {
        $l = Db::one('SELECT * FROM lots WHERE id = ? FOR UPDATE', [$lotId]);
        Db::run('UPDATE lots SET quantite = quantite + ? WHERE id = ?', [$qte, $lotId]);
        self::mouvement((int) $l['produit_id'], $lotId, (int) $l['site_id'], $type, $qte, $ref, $motif);
    }

    /** Fixe la quantité d'un lot (ajustement, inventaire, retrait des périmés) */
    public static function fixer(int $lotId, int $nouvelle, string $type, ?string $ref, ?string $motif): int
    {
        $l = Db::one('SELECT * FROM lots WHERE id = ? FOR UPDATE', [$lotId]);
        $diff = $nouvelle - (int) $l['quantite'];
        if ($diff !== 0) {
            Db::update('lots', ['quantite' => $nouvelle], 'id = ?', [$lotId]);
            self::mouvement((int) $l['produit_id'], $lotId, (int) $l['site_id'], $type, $diff, $ref, $motif);
        }
        return $diff;
    }

    public static function mouvement(int $produitId, ?int $lotId, int $siteId, string $type, int $qte, ?string $ref, ?string $motif = null): void
    {
        Db::insert('mouvements', [
            'at' => now(), 'produit_id' => $produitId, 'lot_id' => $lotId, 'site_id' => $siteId, 'type' => $type,
            'quantite' => $qte, 'ref' => $ref, 'motif' => $motif, 'user_id' => Auth::id() ?: null,
        ]);
    }

    // ---------- Affichage ----------

    /** 9 fractions d'un produit à 4 plaquettes par boîte -> « 2 boîtes + 1 plaquette » */
    public static function qte(array $p, int $fractions, bool $court = false): string
    {
        $f = max(1, (int) $p['fractions']);
        $neg = $fractions < 0;
        $fractions = abs($fractions);
        $b = intdiv($fractions, $f);
        $r = $fractions % $f;
        $parts = [];
        if ($b > 0 || $r === 0) $parts[] = $b . ($court ? '' : ' ' . self::unite($p, 'boite', $b));
        if ($r > 0) $parts[] = $r . ' ' . mb_strtolower(self::unite($p, 'detail', $r));
        return ($neg ? '− ' : '') . implode(' + ', $parts);
    }

    /** Quantité en boîtes, décimale (pour les seuils et calculs) */
    public static function boites(array $p, int $fractions): float
    {
        return $fractions / max(1, (int) $p['fractions']);
    }

    public static function unite(array $p, string $u, int $n = 1): string
    {
        if ($u === 'detail' && $p['detail_nom']) {
            $nom = mb_strtolower($p['detail_nom']);
            return $n > 1 && !str_ends_with($nom, 's') ? $nom . 's' : $nom;
        }
        $nom = self::uniteStock($p);
        return $n > 1 && !str_ends_with($nom, 's') ? $nom . 's' : $nom;
    }

    /** Boîte, Flacon, Sachet... selon la forme */
    public static function uniteStock(array $p): string
    {
        return match ($p['forme'] ?? '') {
            'Sirop', 'Suspension', 'Collyre', 'Solution' => 'flacon',
            'Injectable' => 'ampoule',
            'Pommade', 'Crème' => 'tube',
            default => (int) ($p['contenance'] ?? 0) === 1 && ($p['forme'] ?? '') === 'Sachet' ? 'sachet' : 'boîte',
        };
    }

    /** Produits vendables : [boite => [label, prix, fractions], detail => ...] */
    public static function unitesVente(array $p): array
    {
        $u = [];
        if ($p['prix_boite'] !== null) {
            $u['boite'] = ['label' => ucfirst(self::uniteStock($p)), 'prix' => (int) $p['prix_boite'], 'fractions' => (int) $p['fractions']];
        }
        if ($p['detail_nom'] && $p['prix_detail'] !== null && (int) $p['fractions'] > 1) {
            $u['detail'] = ['label' => $p['detail_nom'], 'prix' => (int) $p['prix_detail'], 'fractions' => 1];
        }
        return $u;
    }
}
