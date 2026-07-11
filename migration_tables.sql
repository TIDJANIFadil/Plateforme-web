-- ==============================================================
-- Migration : Tables complémentaires pour IFRI Portail
-- ==============================================================
-- Exécute ce fichier dans phpMyAdmin ou via la console MySQL :
-- mysql -u root ifri_gestion_docs < migration.sql
-- ==============================================================

-- 1. Table des administrateurs
CREATE TABLE IF NOT EXISTS `administrateurs` (
  `id_admin` INT NOT NULL AUTO_INCREMENT,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(180) NOT NULL UNIQUE,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'Administrateur',
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table des notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id_notification` INT NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `niveau` ENUM('info', 'urgent', 'systeme') NOT NULL DEFAULT 'info',
  `lue` TINYINT(1) NOT NULL DEFAULT 0,
  `id_etudiant` INT DEFAULT NULL,
  `cree_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notification`),
  KEY `fk_notification_etudiant` (`id_etudiant`),
  CONSTRAINT `fk_notification_etudiant` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Vérification/Mise à jour de la colonne libelle (vérifier que la colonne s'appelle bien "libelle")
-- ALTER TABLE types_documents CHANGE libelle_type libelle VARCHAR(255) NOT NULL;

-- 4. Ajout d'un administrateur par défaut (mot de passe : admin123)
INSERT IGNORE INTO `administrateurs` (`nom`, `prenom`, `email`, `mot_de_passe`, `role`)
VALUES ('Admin', 'Principal', 'admin@ifri.bj', '$2y$12$lmw6.YOk6yAiyGC/MbWzoubFAnQzvFPMdXLpi/R0FQ/h2yvPsYGWe', 'Administrateur Principal');

-- 5. Ajout de la colonne actif pour activer/désactiver les types de documents
ALTER TABLE types_documents ADD COLUMN IF NOT EXISTS `actif` TINYINT(1) NOT NULL DEFAULT 1 AFTER `libelle`;

-- 6. Ajout des colonnes pour le workflow de téléchargement de documents
ALTER TABLE demandes ADD COLUMN IF NOT EXISTS `document_pdf` VARCHAR(255) DEFAULT NULL AFTER `statut_demande`;
ALTER TABLE demandes ADD COLUMN IF NOT EXISTS `code_secret` VARCHAR(10) DEFAULT NULL AFTER `document_pdf`;
ALTER TABLE demandes ADD COLUMN IF NOT EXISTS `date_retrait` DATETIME DEFAULT NULL AFTER `code_secret`;

-- ==============================================================
-- Mise à jour des types de documents pour le formulaire étudiant
-- ==============================================================
-- Exécute ces requêtes dans phpMyAdmin (onglet SQL)
-- ==============================================================

-- Mise à jour des types existants (1-3)
UPDATE types_documents SET libelle = 'Attestation d''inscription / Certificat de scolarité'
WHERE id_type = 1;

UPDATE types_documents SET libelle = 'Relevé de notes'
WHERE id_type = 2;

UPDATE types_documents SET libelle = 'Attestation de succès'
WHERE id_type = 3;

-- Ajout des nouveaux types (4-9) : ignore si déjà existants
INSERT IGNORE INTO types_documents (id_type, libelle) VALUES
(4, 'Duplicata de scolarité'),
(5, 'Réclamation'),
(6, 'Attestation de main-levée'),
(7, 'Supplément au diplôme'),
(8, 'Certification de documents'),
(9, 'Attestation d''admissibilité');

-- ==============================================================
-- 7. Table pour stocker les pièces uploadées par les étudiants
-- ==============================================================
CREATE TABLE IF NOT EXISTS `pieces_demandes` (
  `id_piece` INT NOT NULL AUTO_INCREMENT,
  `id_demande` INT NOT NULL,
  `nom_piece` VARCHAR(255) NOT NULL COMMENT 'Description de la pièce (ex: Copie de fiche inscription)',
  `fichier` VARCHAR(255) NOT NULL COMMENT 'Chemin du fichier uploadé',
  `date_upload` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_piece`),
  KEY `fk_piece_demande` (`id_demande`),
  CONSTRAINT `fk_piece_demande` FOREIGN KEY (`id_demande`) REFERENCES `demandes` (`id_demande`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 8. Table pour les notifications administrateur
-- ==============================================================
CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `id_notification` INT NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type_notif` ENUM('nouvelle_demande', 'connexion', 'statut', 'info', 'systeme') NOT NULL DEFAULT 'info',
  `lue` TINYINT(1) NOT NULL DEFAULT 0,
  `id_etudiant` INT DEFAULT NULL,
  `id_demande` INT DEFAULT NULL,
  `cree_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notification`),
  KEY `fk_admin_notif_etudiant` (`id_etudiant`),
  KEY `fk_admin_notif_demande` (`id_demande`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- 9. Table pour les messages de contact des étudiants
-- ==============================================================
CREATE TABLE IF NOT EXISTS `messages_contact` (
  `id_message` INT NOT NULL AUTO_INCREMENT,
  `id_etudiant` INT DEFAULT NULL,
  `sujet` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `piece_jointe` VARCHAR(255) DEFAULT NULL,
  `lu` TINYINT(1) NOT NULL DEFAULT 0,
  `cree_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_message`),
  KEY `fk_contact_etudiant` (`id_etudiant`),
  CONSTRAINT `fk_contact_etudiant` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
