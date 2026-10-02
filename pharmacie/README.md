# Gestion de pharmacie

Application web de gestion d'une pharmacie à plusieurs sites (officines et magasin central) :
caisse en espèces, stock par lot avec péremption, approvisionnement, argent et sécurité.
Construite d'après la maquette « Gestion de pharmacie (caisse, stock, argent, mobile) ».

PHP 8.1+ · MySQL / MariaDB · aucune dépendance à installer (pas de Composer, pas de npm).

## Fonctionnalités

**Espace vendeur (caisse, plein écran)**
- Connexion par identifiant et mot de passe, blocage 15 min après 5 erreurs
- Première connexion : choix du mot de passe et d'un code de déverrouillage à 4 chiffres
- Vente : accès rapide aux 12 produits les plus vendus, recherche par nom, molécule, abréviation du comptoir (« PARA CP ») ou code-barres
- Vente à la boîte ou au détail (plaquette), contrôle du stock, lots périmés bloqués, rappel « Ordonnance »
- Encaissement en espèces avec pavé numérique et monnaie à rendre, ticket imprimable (80 mm)
- Vente à crédit aux clients autorisés, dans la limite de leur plafond
- Remise : 10 % pour un vendeur, 20 % pour un gérant ; au-delà, code d'un responsable
- Tickets en attente, raccourcis clavier (F2 recherche, F9 encaisser, Entrée valider)
- Verrouillage automatique après 1, 2 ou 5 minutes sans activité (déverrouillage par code ; 3 erreurs ferment la session)
- **Hors connexion** : les ventes sont gardées sur le poste et envoyées au retour du réseau, sans doublon
- Annulation d'une vente avec le code d'un gérant ou du propriétaire (stock remis en rayon)
- Dépenses payées avec la caisse, clôture par comptage des billets, écart à expliquer

**Espace propriétaire / gérant**
- Tableau de bord : recette du jour, espèces à recevoir, écarts, recette sur 14 jours par officine, tâches à traiter, meilleures ventes
- Caisses et versements : contrôle des clôtures, demande de précisions au vendeur, validation des versements, dépenses, mouvements d'espèces
- Créances clients : clients autorisés, plafond, ancienneté, encaissement des remboursements, relevé
- Réapprovisionnement : quantités suggérées (ventes/jour × (délai fournisseur + 15 j) − stock − en commande), création d'un bon par fournisseur
- Bons de commande (proposés par le gérant, validés par le propriétaire), impression, suivi de livraison et paiements
- Réceptions : lot, péremption, prix facturé, alerte péremption courte et écart de prix, brouillon, produits hors commande
- Fournisseurs : commandes, évolution des prix, paiements, taux de livraisons complètes
- Stock par site et par lot (sorties au premier périmé), filtres, inventaire, ajustements motivés, retrait des périmés, historique des mouvements
- Catalogue produits : abréviations du comptoir, unités et prix, marge, seuils d'alerte par site, produits à vérifier ou sans prix
- Transferts entre sites (stock « en route » jusqu'à confirmation, écarts signalés)
- Utilisateurs (vendeur, gérant, magasinier), mot de passe provisoire, désactivation immédiate
- Rôles et droits, sécurité (appareils autorisés, postes de caisse enregistrés, journal des connexions, comptes bloqués), journal d'activité non modifiable
- Vérification en deux étapes (Google / Microsoft Authenticator) obligatoire pour le propriétaire et les gérants, avec codes de secours

**Mobile** : toutes les pages s'adaptent au téléphone (onglets en bas d'écran) et l'application peut être installée sur l'écran d'accueil.

## Installation

1. Créez une base et un utilisateur MySQL :
   ```sql
   CREATE DATABASE pharmacie CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'pharma'@'localhost' IDENTIFIED BY 'un-mot-de-passe-solide';
   GRANT ALL ON pharmacie.* TO 'pharma'@'localhost';
   ```
2. Copiez `config.local.example.php` en `config.local.php` et renseignez le nom de la pharmacie et les accès à la base.
   Ce fichier n'est jamais envoyé sur GitHub.
3. Créez les tables :
   ```sh
   php install.php           # mise en service : crée le compte « proprietaire » et affiche son mot de passe provisoire
   php install.php --demo    # ou : données d'exemple pour essayer l'application
   ```
4. Lancez le serveur :
   - pour essayer : `php -S localhost:8000 -t public` puis ouvrez http://localhost:8000
   - en production : faites pointer Apache ou Nginx sur le dossier `public/`, en HTTPS, et mettez `'https' => true` dans `config.local.php`
   - avec XAMPP / WAMP : copiez le dossier dans `htdocs` et ouvrez http://localhost/pharmacie/

### Comptes de démonstration (`--demo`)

Mot de passe de tous les comptes : `pharma2026`

| Identifiant | Rôle | Code de déverrouillage |
|---|---|---|
| `proprietaire` | Propriétaire | 1379 |
| `hadiza.remede` | Gérante REMEDE | 2468 |
| `aicha.remede`, `ibrahim.remede` | Vendeurs REMEDE | 2580 |
| `mariama.madobi` | Vendeuse MADOBI | 2580 |
| `salif.magasin` | Magasinier | 2580 |
| `fatouma.remede` | Vendeuse, première connexion | — |

En démonstration, la vérification en deux étapes et les postes enregistrés sont désactivés.
**Activez-les dans Sécurité › Règles d'accès avant la mise en service.**

## Mise en service

1. Connectez-vous avec `proprietaire`, choisissez votre mot de passe (12 caractères minimum) et activez la vérification en deux étapes.
2. Renommez les sites dans la table `sites` si besoin (l'installation crée « Officine principale » et « Magasin central »).
3. Créez les fournisseurs, les produits (ou importez-les) et les utilisateurs.
4. Sur chaque ordinateur de caisse : Sécurité › Enregistrer un poste de caisse, puis sur le poste « Enregistrer ce poste comme caisse » avec le code affiché.
5. Saisissez le stock existant par une réception ou un inventaire.

## Sauvegardes

`scripts/sauvegarde.sh` fait une copie compressée de la base et garde 30 jours. À programmer chaque nuit :
```sh
crontab -e
0 3 * * * /chemin/vers/pharmacie/scripts/sauvegarde.sh
```
Les identifiants MySQL sont lus dans `~/.my.cnf` (section `[mysqldump]`). Copiez régulièrement les sauvegardes hors du serveur.

## Organisation du code

```
public/          seul dossier exposé au web : index.php (routeur), assets (CSS, JS, icônes), PWA
src/             accès à la base, connexion et sécurité, droits, stock par lot (FEFO), calculs de caisse
controllers/     une fonction par écran ou action
views/           gabarits HTML (layout/, auth/, caisse/, admin/)
database/        schema.sql
install.php      création des tables et données d'exemple
```

Sécurité : requêtes préparées, jetons CSRF sur tous les formulaires, mots de passe hachés (`password_hash`),
sessions régénérées à la connexion, fermeture des sessions à la désactivation ou au changement de rôle,
journal d'activité et journal des connexions en ajout seul.
