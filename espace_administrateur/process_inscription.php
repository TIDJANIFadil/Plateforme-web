<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// Vérifier si l'admin est bien connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

// Connexion BDD via db.php
require_once __DIR__ . '/../ifri_gestion_docs.php';

// Détection appel AJAX
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

function respond($success, $message) {
    global $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    if ($success) {
        $_SESSION['inscription_success'] = $message;
    } else {
        $_SESSION['inscription_error'] = $message;
    }
    header('Location: dashboard_admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $matricule = trim($_POST['matricule'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!empty($prenom) && !empty($nom) && !empty($matricule) && !empty($email)) {

        // 1. Vérifier si le matricule ou l'email existe déjà
        $check = $pdo->prepare("SELECT id_etudiant FROM etudiants WHERE matricule = ? OR email = ?");
        $check->execute([$matricule, $email]);

        if ($check->rowCount() > 0) {
            respond(false, "Ce matricule ou cet email est déjà utilisé.");
        }

        // 2. Génération d'un mot de passe temporaire aléatoire (8 caractères)
        $password_brut = bin2hex(random_bytes(4));
        $password_hash = password_hash($password_brut, PASSWORD_BCRYPT);

        // 3. Insertion dans la table 'etudiants'
        $req = $pdo->prepare("INSERT INTO etudiants (matricule, nom, prenom, email, mot_de_passe, statut_compte) VALUES (?, ?, ?, ?, ?, 'valide')");

        if ($req->execute([$matricule, strtoupper($nom), $prenom, $email, $password_hash])) {

            // 4. Construction du lien de connexion
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/';
            $login_url = $base_url . 'index.php';

            // 5. Envoi de l'email avec PHPMailer (si installé)
            $mail_sent = false;
            $mail_error = '';

            if (file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
                try {
                    require __DIR__ . '/PHPMailer/Exception.php';
                    require __DIR__ . '/PHPMailer/PHPMailer.php';
                    require __DIR__ . '/PHPMailer/SMTP.php';

                    $mail = new PHPMailer(true);

                    // Configuration du serveur SMTP
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'tidjanifadilakambi@gmail.com';
                    $mail->Password   = 'iiqwrvzyfcciwrcr';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->setFrom('tidjanifadilakambi@gmail.com', 'IFRI Portail');

                    $mail->addAddress($email, "$prenom $nom");

                    $mail->isHTML(true);
                    $mail->Subject = 'Vos identifiants de connexion - IFRI Portail';

                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 10px;'>
                            <div style='text-align: center; margin-bottom: 20px;'>
                                <img src='{$base_url}images/IFRI.png' alt='IFRI' style='height: 60px;' />
                                <h1 style='color: #004A99; margin: 10px 0 0; font-size: 22px;'>IFRI Portail</h1>
                            </div>
                            <p>Bonjour <strong>$prenom $nom</strong>,</p>
                            <p>Votre compte étudiant a été créé par l'administration. Vous pouvez dès à présent accéder à la plateforme avec les identifiants suivants :</p>
                            <table style='background: #f8fafc; padding: 15px; border-radius: 8px; width: 100%; margin: 15px 0;'>
                                <tr><td style='padding: 6px 10px;'><strong>Matricule :</strong></td><td style='padding: 6px;'><code>$matricule</code></td></tr>
                                <tr><td style='padding: 6px 10px;'><strong>Mot de passe :</strong></td><td style='padding: 6px;'><code>$password_brut</code></td></tr>
                            </table>
                            <div style='text-align: center; margin: 25px 0;'>
                                <a href='$login_url' style='background-color: #004A99; color: white; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 16px;'>
                                    Se connecter à la plateforme
                                </a>
                            </div>
                            <p style='color: #64748b; font-size: 0.85rem; text-align: center;'>⚠️ Pour des raisons de sécurité, veuillez modifier ce mot de passe dès votre première connexion.</p>
                            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;' />
                            <p style='color: #94a3b8; font-size: 0.8rem; text-align: center;'>Ce message est généré automatiquement, merci de ne pas y répondre.</p>
                        </div>
                    ";

                    $mail->send();
                    $mail_sent = true;
                } catch (Exception $e) {
                    $mail_error = $mail->ErrorInfo;
                }
            }

            if ($mail_sent) {
                respond(true, "Étudiant inscrit avec succès. Les identifiants ont été envoyés par email.");
            } else {
                respond(true, "Étudiant inscrit avec succès. Email non envoyé (SMTP non configuré).");
            }

        } else {
            respond(false, "Erreur lors de l'inscription en base de données.");
        }
    } else {
        respond(false, "Veuillez remplir tous les champs.");
    }
}
