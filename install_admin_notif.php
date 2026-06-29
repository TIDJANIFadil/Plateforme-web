<?php
/**
 * Script d'installation : crée la table admin_notifications
 * Exécutez : php install_admin_notif.php
 */
require_once __DIR__ . '/ifri_gestion_docs.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications (
        id_notification INT NOT NULL AUTO_INCREMENT,
        titre VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type_notif ENUM('nouvelle_demande','connexion','statut','info','systeme') NOT NULL DEFAULT 'info',
        lue TINYINT(1) NOT NULL DEFAULT 0,
        id_etudiant INT DEFAULT NULL,
        id_demande INT DEFAULT NULL,
        cree_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_notification),
        KEY fk_admin_notif_etudiant (id_etudiant),
        KEY fk_admin_notif_demande (id_demande)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Table 'admin_notifications' créée avec succès !\n";
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
