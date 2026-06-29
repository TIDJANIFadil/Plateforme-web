<?php
/**
 * ifri_gestion_docs.php
 * Connexion à la base de données avec PDO
 */

declare(strict_types=1);

// ── Configuration de la base de données ──────────────────────────
$host    = 'localhost';
$dbname  = 'ifri_gestion_docs';
$username = 'root';
$password = ''; // Par défaut sous XAMPP, le mot de passe est vide

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Activation des erreurs PDO pour le développement
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Récupération des données sous forme de tableau associatif par défaut
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En production, on évite d'afficher $e->getMessage() pour ne pas donner d'infos sur la BDD
    error_log('Erreur de connexion BDD : ' . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}

/**
 * Retourne l'instance PDO partagée.
 * Utilisation : $pdo = getPDO();
 */
function getPDO(): PDO
{
    global $pdo;
    return $pdo;
}