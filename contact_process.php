<?php
session_start();

// Sécurité : étudiant doit être connecté
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour envoyer un message.']);
    exit;
}

require_once __DIR__ . '/ifri_gestion_docs.php';
$pdo = getPDO();

$id_etudiant = intval($_SESSION['user_id']);

// Récupération des données
$sujet = isset($_POST['sujet']) ? trim($_POST['sujet']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validation
if (empty($sujet)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner un sujet.']);
    exit;
}
if (empty($message)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Veuillez écrire votre message.']);
    exit;
}

$piece_jointe_path = null;

// Gestion de la pièce jointe (optionnelle)
if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5 Mo

    if (!in_array($_FILES['piece_jointe']['type'], $allowed_types)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Format de fichier non autorisé (JPG, PNG, GIF, WEBP, PDF uniquement).']);
        exit;
    }
    if ($_FILES['piece_jointe']['size'] > $max_size) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Le fichier ne doit pas dépasser 5 Mo.']);
        exit;
    }

    $upload_dir = __DIR__ . '/uploads/contacts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION));
    $safe_name = 'contact_' . $id_etudiant . '_' . time() . '.' . $ext;
    $dest = $upload_dir . $safe_name;

    if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $dest)) {
        $piece_jointe_path = 'uploads/contacts/' . $safe_name;
    }
}

try {
    // Insertion du message
    $stmt = $pdo->prepare("INSERT INTO messages_contact (id_etudiant, sujet, message, piece_jointe, cree_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$id_etudiant, $sujet, $message, $piece_jointe_path]);

    // Récupération des infos étudiant pour la notification admin
    $info = $pdo->prepare("SELECT nom, prenom, matricule FROM etudiants WHERE id_etudiant = ?");
    $info->execute([$id_etudiant]);
    $etudiant = $info->fetch();

    if ($etudiant) {
        $titre_notif = "💬 Message de " . $etudiant['prenom'] . " " . $etudiant['nom'];
        $msg_notif = $etudiant['prenom'] . " " . $etudiant['nom'] . " (" . $etudiant['matricule'] . ") a envoyé un message : \"" . $sujet . "\"";
        $stmt_notif = $pdo->prepare("INSERT INTO admin_notifications (titre, message, type_notif, id_etudiant, cree_at) VALUES (?, ?, 'info', ?, NOW())");
        $stmt_notif->execute([$titre_notif, $msg_notif, $id_etudiant]);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Votre message a été envoyé avec succès. L\'équipe administrative vous répondra dans les plus brefs délais.']);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi du message. Veuillez réessayer.']);
}
