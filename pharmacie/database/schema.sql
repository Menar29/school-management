-- Gestion de pharmacie · schéma MySQL / MariaDB
-- Toutes les sommes sont en FCFA (entiers). Les quantités de stock sont
-- comptées en « fractions » : 1 boîte = produits.fractions fractions
-- (1 si le produit ne se vend qu'à la boîte, 4 si une boîte contient 4 plaquettes...).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS vente_lots, vente_lignes, ventes, depenses, caisse_sessions,
  creance_paiements, clients, mouvements_especes, paiements_fournisseurs,
  reception_lignes, receptions, commande_lignes, commandes, transfert_lignes, transferts,
  mouvements, lots, seuils, produit_alias, produits, fournisseurs,
  audit_log, login_log, poste_codes, postes, backup_codes, users, sites, settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(80) NOT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  type VARCHAR(10) NOT NULL DEFAULT 'officine',  -- officine | magasin
  couleur CHAR(1) NOT NULL DEFAULT 'r',           -- r | m | g (repère visuel)
  actif TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  login VARCHAR(60) NOT NULL UNIQUE,
  telephone VARCHAR(30) NULL,
  role VARCHAR(20) NOT NULL,                      -- proprietaire | gerant | vendeur | magasinier
  site_id INT NULL,
  password_hash VARCHAR(255) NOT NULL,
  must_change_pw TINYINT NOT NULL DEFAULT 1,
  pin_hash VARCHAR(255) NULL,
  totp_secret VARCHAR(64) NULL,
  totp_enabled TINYINT NOT NULL DEFAULT 0,
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  actif TINYINT NOT NULL DEFAULT 1,
  session_version INT NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  last_login_device VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE backup_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  used_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Postes de caisse et appareils autorisés
CREATE TABLE postes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  site_id INT NULL,
  type VARCHAR(12) NOT NULL DEFAULT 'caisse',    -- caisse | appareil
  token_hash CHAR(64) NOT NULL UNIQUE,
  user_id INT NULL,                              -- appareil personnel (propriétaire, gérant)
  created_at DATETIME NOT NULL,
  last_seen_at DATETIME NULL,
  last_user VARCHAR(120) NULL,
  revoked_at DATETIME NULL,
  FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE poste_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code CHAR(6) NOT NULL,
  site_id INT NOT NULL,
  created_by INT NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL,
  login VARCHAR(60) NOT NULL,
  user_id INT NULL,
  appareil VARCHAR(160) NULL,
  ip VARCHAR(45) NULL,
  resultat VARCHAR(30) NOT NULL,                 -- ok | ok_2fa | deverrouillage | mauvais_mdp | inexistant | bloque | mauvais_site | desactive | poste_inconnu
  KEY idx_login_at (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Journal d'activité : on n'y fait que des INSERT
CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL,
  user_id INT NULL,
  site_id INT NULL,
  poste VARCHAR(120) NULL,
  action VARCHAR(60) NOT NULL,
  details TEXT NULL,
  KEY idx_audit_at (at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE fournisseurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  nom VARCHAR(120) NOT NULL,
  contact VARCHAR(120) NULL,
  telephone VARCHAR(40) NULL,
  adresse VARCHAR(200) NULL,
  delai_jours INT NOT NULL DEFAULT 7,
  conditions VARCHAR(120) NULL,
  actif TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE produits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  designation VARCHAR(160) NOT NULL UNIQUE,
  dci VARCHAR(160) NULL,
  dosage VARCHAR(60) NULL,
  forme VARCHAR(40) NOT NULL,
  categorie VARCHAR(40) NOT NULL DEFAULT 'Médicament',
  classe VARCHAR(120) NULL,
  code_barre VARCHAR(40) NULL,
  ordonnance TINYINT NOT NULL DEFAULT 0,
  contenance INT NOT NULL DEFAULT 1,             -- unités par boîte (ex. 40 comprimés)
  unite_base VARCHAR(30) NOT NULL DEFAULT 'unités',
  detail_nom VARCHAR(30) NULL,                   -- ex. Plaquette (NULL = vendu à la boîte uniquement)
  detail_contenance INT NULL,                    -- unités par plaquette (ex. 10)
  fractions INT NOT NULL DEFAULT 1,              -- plaquettes par boîte (1 sans vente au détail)
  prix_boite INT NULL,
  prix_detail INT NULL,
  prix_achat INT NULL,                           -- dernier prix d'achat d'une boîte
  prix_achat_prec INT NULL,
  fournisseur_id INT NULL,
  a_verifier TINYINT NOT NULL DEFAULT 0,         -- créé par un gérant, à vérifier par le propriétaire
  actif TINYINT NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_prod_cb (code_barre),
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE produit_alias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produit_id INT NOT NULL,
  alias VARCHAR(80) NOT NULL,
  FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE seuils (
  produit_id INT NOT NULL,
  site_id INT NOT NULL,
  seuil INT NOT NULL DEFAULT 0,                  -- en boîtes
  PRIMARY KEY (produit_id, site_id),
  FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE,
  FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  produit_id INT NOT NULL,
  site_id INT NOT NULL,
  numero VARCHAR(40) NOT NULL,
  peremption DATE NULL,
  quantite INT NOT NULL DEFAULT 0,               -- en fractions
  prix_achat INT NULL,                           -- par boîte
  created_at DATETIME NOT NULL,
  KEY idx_lot_ps (produit_id, site_id),
  FOREIGN KEY (produit_id) REFERENCES produits(id),
  FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mouvements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL,
  produit_id INT NOT NULL,
  lot_id INT NULL,
  site_id INT NOT NULL,
  type VARCHAR(20) NOT NULL,                     -- vente | annulation | reception | transfert_out | transfert_in | ajustement | inventaire | retrait
  quantite INT NOT NULL,                         -- signée, en fractions
  ref VARCHAR(40) NULL,
  motif VARCHAR(200) NULL,
  user_id INT NULL,
  KEY idx_mvt_prod (produit_id, at),
  FOREIGN KEY (produit_id) REFERENCES produits(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(120) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'Particulier',
  telephone VARCHAR(40) NULL,
  site_id INT NOT NULL,
  plafond INT NOT NULL DEFAULT 0,
  autorise TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE caisse_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  site_id INT NOT NULL,
  user_id INT NOT NULL,
  poste VARCHAR(120) NULL,
  ouverte_at DATETIME NOT NULL,
  fond INT NOT NULL DEFAULT 0,
  cloturee_at DATETIME NULL,
  attendu INT NULL,
  compte INT NULL,
  ecart INT NULL,
  billetage TEXT NULL,
  explication TEXT NULL,
  statut VARCHAR(12) NOT NULL DEFAULT 'ouverte', -- ouverte | cloturee | precisions | validee
  remarque_admin TEXT NULL,
  valide_par INT NULL,
  valide_at DATETIME NULL,
  KEY idx_sess_user (user_id, statut),
  FOREIGN KEY (site_id) REFERENCES sites(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ventes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(20) NULL UNIQUE,
  client_uuid VARCHAR(40) NULL UNIQUE,           -- évite les doublons des ventes envoyées hors connexion
  session_id INT NOT NULL,
  site_id INT NOT NULL,
  user_id INT NOT NULL,
  client_id INT NULL,
  at DATETIME NOT NULL,
  total_brut INT NOT NULL,
  remise INT NOT NULL DEFAULT 0,
  remise_autorisee_par INT NULL,
  total INT NOT NULL,
  mode VARCHAR(10) NOT NULL DEFAULT 'especes',   -- especes | credit
  recu INT NULL,
  rendu INT NULL,
  statut VARCHAR(10) NOT NULL DEFAULT 'payee',   -- payee | annulee
  annulee_par INT NULL,
  annulee_at DATETIME NULL,
  motif_annulation VARCHAR(200) NULL,
  KEY idx_vente_at (site_id, at),
  FOREIGN KEY (session_id) REFERENCES caisse_sessions(id),
  FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vente_lignes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vente_id INT NOT NULL,
  produit_id INT NOT NULL,
  unite VARCHAR(10) NOT NULL,                    -- boite | detail
  quantite INT NOT NULL,
  prix_unitaire INT NOT NULL,
  total INT NOT NULL,
  fractions INT NOT NULL,                        -- fractions sorties du stock
  cout INT NOT NULL DEFAULT 0,                   -- coût d'achat de la ligne
  FOREIGN KEY (vente_id) REFERENCES ventes(id) ON DELETE CASCADE,
  FOREIGN KEY (produit_id) REFERENCES produits(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vente_lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ligne_id INT NOT NULL,
  lot_id INT NOT NULL,
  quantite INT NOT NULL,
  FOREIGN KEY (ligne_id) REFERENCES vente_lignes(id) ON DELETE CASCADE,
  FOREIGN KEY (lot_id) REFERENCES lots(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE depenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL,
  site_id INT NULL,
  session_id INT NULL,                           -- dépense payée avec l'argent d'une caisse
  user_id INT NOT NULL,
  libelle VARCHAR(160) NOT NULL,
  montant INT NOT NULL,
  FOREIGN KEY (session_id) REFERENCES caisse_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE creance_paiements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  at DATETIME NOT NULL,
  montant INT NOT NULL,
  user_id INT NOT NULL,
  note VARCHAR(160) NULL,
  FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Espèces détenues par le propriétaire (versements reçus, paiements, dépenses)
CREATE TABLE mouvements_especes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL,
  type VARCHAR(20) NOT NULL,                     -- solde_initial | versement | paiement_fournisseur | depense | creance | autre
  libelle VARCHAR(200) NOT NULL,
  montant INT NOT NULL,                          -- signé
  ref VARCHAR(40) NULL,
  user_id INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE commandes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(20) NULL UNIQUE,
  fournisseur_id INT NOT NULL,
  site_id INT NOT NULL,                          -- lieu de livraison
  statut VARCHAR(12) NOT NULL DEFAULT 'creee',   -- proposee | creee | envoyee | partielle | livree | annulee
  notes TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  envoyee_at DATETIME NULL,
  livree_at DATETIME NULL,
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
  FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE commande_lignes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  commande_id INT NOT NULL,
  produit_id INT NOT NULL,
  quantite INT NOT NULL,                         -- boîtes
  prix_achat INT NOT NULL,
  recu INT NOT NULL DEFAULT 0,
  FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
  FOREIGN KEY (produit_id) REFERENCES produits(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE receptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(20) NULL UNIQUE,
  commande_id INT NULL,
  fournisseur_id INT NOT NULL,
  site_id INT NOT NULL,
  bl_numero VARCHAR(60) NULL,
  date_livraison DATE NULL,
  montant_facture INT NULL,
  statut VARCHAR(10) NOT NULL DEFAULT 'brouillon', -- brouillon | validee
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  validee_at DATETIME NULL,
  FOREIGN KEY (commande_id) REFERENCES commandes(id),
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
  FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reception_lignes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reception_id INT NOT NULL,
  produit_id INT NOT NULL,
  commande_ligne_id INT NULL,
  attendu INT NOT NULL DEFAULT 0,
  quantite INT NOT NULL DEFAULT 0,               -- boîtes reçues
  numero_lot VARCHAR(40) NULL,
  peremption DATE NULL,
  prix_facture INT NULL,
  FOREIGN KEY (reception_id) REFERENCES receptions(id) ON DELETE CASCADE,
  FOREIGN KEY (produit_id) REFERENCES produits(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE paiements_fournisseurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fournisseur_id INT NOT NULL,
  commande_id INT NULL,
  at DATETIME NOT NULL,
  montant INT NOT NULL,
  user_id INT NOT NULL,
  note VARCHAR(160) NULL,
  FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id),
  FOREIGN KEY (commande_id) REFERENCES commandes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transferts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(20) NULL UNIQUE,
  site_from INT NOT NULL,
  site_to INT NOT NULL,
  statut VARCHAR(10) NOT NULL DEFAULT 'en_route', -- en_route | recu | ecart
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  recu_by INT NULL,
  recu_at DATETIME NULL,
  note TEXT NULL,
  FOREIGN KEY (site_from) REFERENCES sites(id),
  FOREIGN KEY (site_to) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transfert_lignes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfert_id INT NOT NULL,
  produit_id INT NOT NULL,
  numero_lot VARCHAR(40) NOT NULL,
  peremption DATE NULL,
  prix_achat INT NULL,
  envoye INT NOT NULL,                           -- fractions
  recu INT NULL,
  FOREIGN KEY (transfert_id) REFERENCES transferts(id) ON DELETE CASCADE,
  FOREIGN KEY (produit_id) REFERENCES produits(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
