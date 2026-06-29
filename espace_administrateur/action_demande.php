<?php
/**
 * action_demande.php
 * Traite les actions rapides (traiter, terminer) depuis les tableaux admin
 */

session_start();

// 1. Sécurité : Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

// 2. Connexion BDD
require_once __DIR__ . '/../ifri_gestion_docs.php';

// 3. Vérifier que la requête est bien en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard_admin.php');
    exit;
}

$id_demande = isset($_POST['id_demande']) ? intval($_POST['id_demande']) : 0;
$action_type = isset($_POST['action_type']) ? trim($_POST['action_type']) : '';

if ($id_demande <= 0 || empty($action_type)) {
    header('Location: dashboard_admin.php');
    exit;
}

// 4. Définir le nouveau statut selon l'action
switch ($action_type) {
    case 'traiter':
        $nouveau_statut = 'En cours';
        break;
    case 'terminer':
        $nouveau_statut = 'Prêt';
        break;
    default:
        header('Location: dashboard_admin.php');
        exit;
}

// 5. Mise à jour en base de données
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE demandes SET statut_demande = ? WHERE id_demande = ?");
    $stmt->execute([$nouveau_statut, $id_demande]);

    // Récupération infos étudiant pour notification
    $info = $pdo->prepare("SELECT e.id_etudiant, e.prenom, e.nom, t.libelle FROM demandes d JOIN etudiants e ON d.id_etudiant = e.id_etudiant JOIN types_documents t ON d.id_type_doc = t.id_type WHERE d.id_demande = ?");
    $info->execute([$id_demande]);
    $etudiant = $info->fetch();

    if ($etudiant) {
        if ($nouveau_statut === 'En cours') {
            // Notification étudiant
            $stmt_n = $pdo->prepare("INSERT INTO notifications (titre, message, niveau, id_etudiant, cree_at) VALUES (?, ?, 'info', ?, NOW())");
            $stmt_n->execute(["⏳ " . $etudiant['libelle'] . " en traitement", "Bonjour " . $etudiant['prenom'] . ", votre " . strtolower($etudiant['libelle']) . " est en cours de traitement.", $etudiant['id_etudiant']]);

            // Notification admin
            $stmt_admin = $pdo->prepare("INSERT INTO admin_notifications (titre, message, type_notif, id_etudiant, id_demande, cree_at) VALUES (?, ?, 'statut', ?, ?, NOW())");
            $stmt_admin->execute(["⏳ Demande #" . $id_demande . " en cours", $etudiant['prenom'] . " " . $etudiant['nom'] . " — " . $etudiant['libelle'] . " est en cours de traitement.", $etudiant['id_etudiant'], $id_demande]);
        } elseif ($nouveau_statut === 'Prêt') {
            // Notification étudiant
            $stmt_n = $pdo->prepare("INSERT INTO notifications (titre, message, niveau, id_etudiant, cree_at) VALUES (?, ?, 'info', ?, NOW())");
            $stmt_n->execute(["📄 " . $etudiant['libelle'] . " prête", "Bonjour " . $etudiant['prenom'] . ", votre " . strtolower($etudiant['libelle']) . " est maintenant disponible.", $etudiant['id_etudiant']]);

            // Notification admin
            $stmt_admin = $pdo->prepare("INSERT INTO admin_notifications (titre, message, type_notif, id_etudiant, id_demande, cree_at) VALUES (?, ?, 'statut', ?, ?, NOW())");
            $stmt_admin->execute(["📄 Demande #" . $id_demande . " terminée", $etudiant['prenom'] . " " . $etudiant['nom'] . " — " . $etudiant['libelle'] . " est marquée comme prête.", $etudiant['id_etudiant'], $id_demande]);
        }
    }

    $pdo->commit();

    // Redirection vers la page précédente si disponible, sinon dashboard
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard_admin.php';
    header('Location: ' . $redirect);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Erreur lors de la mise à jour de la demande : " . $e->getMessage());
}
