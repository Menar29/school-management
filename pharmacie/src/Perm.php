<?php
declare(strict_types=1);

/**
 * Rôles et droits. Chaque utilisateur (sauf le propriétaire) est rattaché à un seul site
 * et ses droits ne s'appliquent qu'à ce site.
 */
final class Perm
{
    public const ROLES = [
        'proprietaire' => 'Propriétaire',
        'gerant'       => 'Gérant',
        'vendeur'      => 'Vendeur',
        'magasinier'   => 'Magasinier',
    ];

    /** Remise maximale sans code du propriétaire, en % */
    public const REMISE_MAX = ['vendeur' => 10, 'gerant' => 20, 'proprietaire' => 100, 'magasinier' => 0];

    private const MATRIX = [
        'vendre'            => ['vendeur', 'gerant', 'proprietaire'],
        'dashboard'         => ['gerant', 'proprietaire'],
        'caisses.controler' => ['gerant', 'proprietaire'],
        'caisses.valider'   => ['proprietaire'],
        'creances'          => ['gerant', 'proprietaire'],
        'creances.gerer'    => ['proprietaire'],
        'stock.voir'        => ['gerant', 'magasinier', 'proprietaire'],
        'stock.gerer'       => ['gerant', 'magasinier', 'proprietaire'],
        'produits.voir'     => ['gerant', 'magasinier', 'proprietaire'],
        'produits.creer'    => ['gerant', 'magasinier', 'proprietaire'],
        'produits.prix'     => ['proprietaire'],
        'prix_achat'        => ['proprietaire'],
        'reappro'           => ['gerant', 'proprietaire'],
        'commandes'         => ['gerant', 'proprietaire'],
        'commandes.valider' => ['proprietaire'],
        'receptions'        => ['gerant', 'magasinier', 'proprietaire'],
        'transferts'        => ['gerant', 'magasinier', 'proprietaire'],
        'fournisseurs'      => ['proprietaire'],
        'utilisateurs'      => ['proprietaire'],
        'securite'          => ['proprietaire'],
        'journal'           => ['gerant', 'proprietaire'],
        'mobile'            => ['gerant', 'proprietaire'],
    ];

    public static function can(?array $user, string $perm): bool
    {
        if (!$user) return false;
        return in_array($user['role'], self::MATRIX[$perm] ?? [], true);
    }

    public static function role(string $role, string $siteType = 'officine'): string
    {
        return self::ROLES[$role] ?? $role;
    }

    /** Tableau affiché dans « Rôles et droits » : [libellé, vendeur, gérant, magasinier, propriétaire] */
    public static function tableau(): array
    {
        return [
            'Vente et caisse' => [
                ['Vendre et encaisser en espèces', 'y:Dans son officine', 'y:Dans son officine', 'n', 'y'],
                ['Voir les ventes du jour', 'l:Les siennes seulement', 'y:Toute son officine', 'n', 'y:Tout'],
                ['Accorder une remise', 'l:Jusqu\'à 10 %', 'l:Jusqu\'à 20 %', 'n', 'y'],
                ['Annuler une vente', 'l:Code gérant ou propriétaire', 'y:Dans son officine', 'n', 'y'],
                ['Clôturer sa caisse', 'y', 'y', 'n', 'y'],
                ['Contrôler les clôtures de caisse', 'n', 'y:Son officine', 'n', 'y'],
                ['Recevoir les versements d\'espèces', 'n', 'n', 'n', 'y'],
            ],
            'Stock et catalogue' => [
                ['Voir la quantité disponible d\'un produit', 'y:Dans son officine', 'y:Tous les sites', 'y', 'y'],
                ['Réceptionner, transférer, inventorier', 'n', 'y:Dans son officine', 'y', 'y'],
                ['Créer un nouveau produit', 'n', 'l:Vendable, le propriétaire est prévenu', 'l:Sans prix, non vendable', 'y'],
                ['Fixer le prix de vente', 'n', 'l:À la création uniquement', 'n', 'y'],
                ['Modifier un prix existant', 'n', 'n', 'n', 'y'],
                ['Voir prix d\'achat, marges, valeur du stock', 'n', 'l:Prix d\'achat à la réception', 'n', 'y'],
            ],
            'Approvisionnement et argent' => [
                ['Voir les besoins à commander', 'n', 'y:Son officine', 'n', 'y'],
                ['Créer et envoyer les bons de commande', 'n', 'l:Propose, le propriétaire valide', 'n', 'y'],
                ['Fournisseurs et paiements', 'n', 'n', 'n', 'y'],
                ['Vendre à crédit', 'l:Clients autorisés seulement', 'l:Clients autorisés seulement', 'n', 'y'],
            ],
            'Accès et administration' => [
                ['Application mobile', 'n', 'y', 'n', 'y'],
                ['Tableau de bord', 'n', 'y:Son officine, sans marges', 'n', 'y'],
                ['Créer, modifier, désactiver des utilisateurs', 'n', 'n', 'n', 'y'],
                ['Journal d\'activité', 'n', 'l:Son officine', 'n', 'y'],
            ],
        ];
    }
}
