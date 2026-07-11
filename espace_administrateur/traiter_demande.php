<?php
session_start();

// 1. Sécurité : Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

// 2. Connexion à la base de données
require_once __DIR__ . '/../ifri_gestion_docs.php';
$pdo = getPDO();

// 3. Vérification et récupération de l'ID de la demande
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    header('Location: mes_demandes_admin.php');
    exit;
}

$id_demande = intval($_GET['id']);
$message_success = "";
$message_error = "";

// Fonction de génération de code secret
function genererCodeSecret(int $longueur = 8): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $longueur; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// 4. Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_statut'])) {
    $nouveau_statut = trim($_POST['statut_demande']);
    $motif_rejet = isset($_POST['motif_rejet']) ? trim($_POST['motif_rejet']) : null;
    $motif_admin = isset($_POST['motif_admin']) ? trim($_POST['motif_admin']) : null;

    if (in_array($nouveau_statut, ['En attente', 'En cours', 'Prêt', 'Terminée', 'Rejeté'])) {
        try {
            $pdo->beginTransaction();

            if ($nouveau_statut === 'Prêt') {
                // Gestion de l'upload du PDF
                if (isset($_FILES['document_pdf']) && $_FILES['document_pdf']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['document_pdf']['tmp_name'];
                    $file_name = $_FILES['document_pdf']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if ($file_ext !== 'pdf') {
                        throw new Exception('Seuls les fichiers PDF sont acceptés.');
                    }
                    if ($_FILES['document_pdf']['size'] > 20 * 1024 * 1024) {
                        throw new Exception('Le fichier ne doit pas dépasser 20 Mo.');
                    }

                    // Nom sécurisé : demande_{id}_{date}.pdf
                    $safe_name = 'demande_' . $id_demande . '_' . date('Ymd_His') . '.pdf';
                    $upload_dir = __DIR__ . '/../uploads/documents/';

                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $dest_path = $upload_dir . $safe_name;

                    if (!move_uploaded_file($file_tmp, $dest_path)) {
                        throw new Exception('Erreur lors de l\'enregistrement du fichier.');
                    }

                    $pdf_path = 'uploads/documents/' . $safe_name;

                    // Génération du code secret unique
                    $code_secret = genererCodeSecret();
                    $exists = true;
                    while ($exists) {
                        $check = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE code_secret = ?");
                        $check->execute([$code_secret]);
                        $exists = $check->fetchColumn() > 0;
                        if ($exists) $code_secret = genererCodeSecret();
                    }

                    // Mise à jour de la demande
                    $sql = "UPDATE demandes SET statut_demande = :statut, document_pdf = :pdf, code_secret = :code, commentaire_admin = :motif WHERE id_demande = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'statut' => $nouveau_statut,
                        'pdf' => $pdf_path,
                        'code' => $code_secret,
                        'motif' => $motif_admin,
                        'id' => $id_demande
                    ]);

                    // Récupération infos étudiant pour notification
                    $info = $pdo->prepare("SELECT e.id_etudiant, e.nom, e.prenom, e.email, t.libelle FROM demandes d JOIN etudiants e ON d.id_etudiant = e.id_etudiant JOIN types_documents t ON d.id_type_doc = t.id_type WHERE d.id_demande = ?");
                    $info->execute([$id_demande]);
                    $etudiant = $info->fetch();

                    if ($etudiant) {
                        // Création de la notification
                        $notif_titre = "📄 Document prêt — " . $etudiant['libelle'];
                        $notif_msg = "Bonjour " . $etudiant['prenom'] . ", votre " . strtolower($etudiant['libelle']) . " est disponible. Votre code secret : " . $code_secret . ". Utilisez-le pour télécharger votre document dans votre espace.";
                        $stmt_notif = $pdo->prepare("INSERT INTO notifications (titre, message, niveau, id_etudiant, cree_at) VALUES (?, ?, 'info', ?, NOW())");
                        $stmt_notif->execute([$notif_titre, $notif_msg, $etudiant['id_etudiant']]);

                        // Notification admin
                        $stmt_admin_notif = $pdo->prepare("INSERT INTO admin_notifications (titre, message, type_notif, id_etudiant, id_demande, cree_at) VALUES (?, ?, 'statut', ?, ?, NOW())");
                        $stmt_admin_notif->execute(["📄 Document prêt — #" . $id_demande, $etudiant['prenom'] . " " . $etudiant['nom'] . " — " . $etudiant['libelle'] . " est disponible. Code secret généré.", $etudiant['id_etudiant'], $id_demande]);

                        // Envoi du code secret par email
                        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
                        $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/';
                        $tel_url = $base_url . 'telechargement.php?id=' . $id_demande;

                        try {
                            require_once __DIR__ . '/PHPMailer/Exception.php';
                            require_once __DIR__ . '/PHPMailer/PHPMailer.php';
                            require_once __DIR__ . '/PHPMailer/SMTP.php';

                            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'tidjanifadilakambi@gmail.com';
                            $mail->Password   = 'iiqwrvzyfcciwrcr';
                            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;
                            $mail->setFrom('tidjanifadilakambi@gmail.com', 'IFRI Portail');
                            $mail->addAddress($etudiant['email'], $etudiant['prenom'] . ' ' . $etudiant['nom']);
                            $mail->isHTML(true);
                            $mail->Subject = '🔐 Votre document est prêt - Code secret - IFRI Portail';

                            $mail->Body = "
                                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 10px;'>
                                    <div style='text-align: center; margin-bottom: 20px;'>
                                        <img src='{$base_url}images/IFRI.png' alt='IFRI' style='height: 60px;' />
                                        <h1 style='color: #004A99; margin: 10px 0 0; font-size: 22px;'>IFRI Portail</h1>
                                    </div>
                                    <p>Bonjour <strong>{$etudiant['prenom']} {$etudiant['nom']}</strong>,</p>
                                    <p>Votre document <strong>{$etudiant['libelle']}</strong> est désormais disponible.</p>
                                    <p>Voici votre <strong>code secret</strong> pour le télécharger :</p>
                                    <div style='text-align: center; margin: 25px 0;'>
                                        <div style='background: #1a1a2e; color: white; font-family: \"Courier New\", monospace; font-size: 36px; font-weight: 900; letter-spacing: 12px; padding: 20px; border-radius: 12px; display: inline-block;'>
                                            $code_secret
                                        </div>
                                    </div>
                                    <div style='text-align: center; margin: 25px 0;'>
                                        <a href='$tel_url' style='background-color: #004A99; color: white; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 16px;'>
                                            Télécharger mon document
                                        </a>
                                    </div>
                                    <p style='color: #64748b; font-size: 0.85rem; text-align: center;'>Ce code est personnel et confidentiel. Ne le partagez pas.</p>
                                    <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;' />
                                    <p style='color: #94a3b8; font-size: 0.8rem; text-align: center;'>Ce message est généré automatiquement, merci de ne pas y répondre.</p>
                                </div>
                            ";

                            $mail->send();
                            $email_status = " Email envoyé.";
                        } catch (Exception $e) {
                            $email_status = " (envoi email échoué)";
                        }
                    }

                    $message_success = "Document prêt ! Le code secret a été généré et envoyé par email à l'étudiant.";
                } else {
                    $err_code = $_FILES['document_pdf']['error'] ?? -1;
                    if ($err_code === UPLOAD_ERR_NO_FILE) {
                        throw new Exception('Veuillez sélectionner un fichier PDF avant de passer le statut à "Prêt".');
                    } else {
                        throw new Exception('Erreur lors de l\'upload du fichier (code: ' . $err_code . ').');
                    }
                }
            } elseif ($nouveau_statut === 'En cours') {
                $stmt = $pdo->prepare("UPDATE demandes SET statut_demande = :statut, commentaire_admin = :motif WHERE id_demande = :id");
                $stmt->execute(['statut' => $nouveau_statut, 'motif' => $motif_admin, 'id' => $id_demande]);
                $message_success = "La demande a été mise en cours de traitement.";

                // Notification à l'étudiant
                $info = $pdo->prepare("SELECT e.id_etudiant, e.prenom, e.nom, t.libelle FROM demandes d JOIN etudiants e ON d.id_etudiant = e.id_etudiant JOIN types_documents t ON d.id_type_doc = t.id_type WHERE d.id_demande = ?");
                $info->execute([$id_demande]);
                $etudiant = $info->fetch();
                if ($etudiant) {
                    $raison = !empty($motif_admin) ? " Motif : " . $motif_admin : "";
                    $stmt_n = $pdo->prepare("INSERT INTO notifications (titre, message, niveau, id_etudiant, cree_at) VALUES (?, ?, 'info', ?, NOW())");
                    $stmt_n->execute(["⏳ " . $etudiant['libelle'] . " en traitement", "Bonjour " . $etudiant['prenom'] . ", votre " . strtolower($etudiant['libelle']) . " est en cours de traitement." . $raison, $etudiant['id_etudiant']]);

                    // Notification admin
                    $stmt_admin_notif = $pdo->prepare("INSERT INTO admin_notifications (titre, message, type_notif, id_etudiant, id_demande, cree_at) VALUES (?, ?, 'statut', ?, ?, NOW())");
                    $stmt_admin_notif->execute(["⏳ Demande #" . $id_demande . " en cours", $etudiant['prenom'] . " " . $etudiant['nom'] . " — " . $etudiant['libelle'] . " est en cours de traitement." . (!empty($motif_admin) ? " Motif : " . $motif_admin : ""), $etudiant['id_etudiant'], $id_demande]);
                }
            } elseif ($nouveau_statut === 'En attente') {
                $stmt = $pdo->prepare("UPDATE demandes SET statut_demande = :statut, commentaire_admin = :motif WHERE id_demande = :id");
                $stmt->execute(['statut' => $nouveau_statut, 'motif' => $motif_admin, 'id' => $id_demande]);
                $message_success = "La demande est repassée en attente.";

                // Notification étudiant
                $info = $pdo->prepare("SELECT e.id_etudiant, e.prenom, e.nom, t.libelle FROM demandes d JOIN etudiants e ON d.id_etudiant = e.id_etudiant JOIN types_documents t ON d.id_type_doc = t.id_type WHERE d.id_demande = ?");
                $info->execute([$id_demande]);
                $etudiant = $info->fetch();
                if ($etudiant) {
                    $raison = !empty($motif_admin) ? " Motif : " . $motif_admin : "";
                    $stmt_n = $pdo->prepare("INSERT INTO notifications (titre, message, niveau, id_etudiant, cree_at) VALUES (?, ?, 'info', ?, NOW())");
                    $stmt_n->execute(["🔄 " . $etudiant['libelle'] . " remise en attente", "Bonjour " . $etudiant['prenom'] . ", votre " . strtolower($etudiant['libelle']) . " a été remise en file d'attente pour révision." . $raison, $etudiant['id_etudiant']]);

                    // Notification admin
                    $stmt_admin_notif = $pdo->prepare("INSERT INTO admin_notifications (titre, message, type_notif, id_etudiant, id_demande, cree_at) VALUES (?, ?, 'statut', ?, ?, NOW())");
                    $stmt_admin_notif->execute(["🔄 Demande #" . $id_demande . " en attente", $etudiant['prenom'] . " " . $etudiant['nom'] . " — " . $etudiant['libelle'] . " a été remise en file d'attente." . (!empty($motif_admin) ? " Motif : " . $motif_admin : ""), $etudiant['id_etudiant'], $id_demande]);
                }
            } elseif ($nouveau_statut === 'Rejeté') {
                $stmt = $pdo->prepare("UPDATE demandes SET statut_demande = :statut, motif_rejet = :motif, commentaire_admin = :cmt WHERE id_demande = :id");
                $stmt->execute(['statut' => $nouveau_statut, 'motif' => $motif_rejet, 'cmt' => $motif_admin, 'id' => $id_demande]);
                $message_success = "La demande a été rejetée.";

                // Notification à l'étudiant avec le motif
                $info = $pdo->prepare("SELECT e.id_etudiant, e.prenom, e.nom, t.libelle FROM demandes d JOIN etudiants e ON d.id_etudiant = e.id_etudiant JOIN types_documents t ON d.id_type_doc = t.id_type WHERE d.id_demande = ?");
                $info->execute([$id_demande]);
                $etudiant = $info->fetch();
                if ($etudiant) {
                    $raison = !empty($motif_rejet) ? " Motif : " . $motif_rejet : "";
                    $stmt_n = $pdo->prepare("INSERT INTO notifications (titre, message, niveau, id_etudiant, cree_at) VALUES (?, ?, 'urgent', ?, NOW())");
                    $stmt_n->execute(["❌ " . $etudiant['libelle'] . " rejetée", "Bonjour " . $etudiant['prenom'] . ", votre " . strtolower($etudiant['libelle']) . " a été rejetée." . $raison, $etudiant['id_etudiant']]);

                    // Notification admin
                    $stmt_admin_notif = $pdo->prepare("INSERT INTO admin_notifications (titre, message, type_notif, id_etudiant, id_demande, cree_at) VALUES (?, ?, 'statut', ?, ?, NOW())");
                    $stmt_admin_notif->execute(["❌ Demande #" . $id_demande . " rejetée", $etudiant['prenom'] . " " . $etudiant['nom'] . " — " . $etudiant['libelle'] . ". Motif : " . (!empty($motif_rejet) ? $motif_rejet : "Non spécifié"), $etudiant['id_etudiant'], $id_demande]);
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message_error = $e->getMessage();
        }
    }
}

// 5. Récupération des détails de la demande
try {
    $query = "SELECT d.*, e.nom, e.prenom, e.matricule, e.email, t.libelle
              FROM demandes d
              JOIN etudiants e ON d.id_etudiant = e.id_etudiant
              JOIN types_documents t ON d.id_type_doc = t.id_type
              WHERE d.id_demande = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $id_demande]);
    $demande = $stmt->fetch();

    if (!$demande) {
        header('Location: mes_demandes_admin.php');
        exit;
    }

    // Récupération des pièces uploadées par l'étudiant
    $stmt_pieces = $pdo->prepare("SELECT * FROM pieces_demandes WHERE id_demande = ? ORDER BY id_piece ASC");
    $stmt_pieces->execute([$id_demande]);
    $pieces_uploaded = $stmt_pieces->fetchAll();

} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

$student_email = htmlspecialchars($demande['email'] ?? '');
$doc_libelle = htmlspecialchars($demande['libelle'] ?? 'Document');
$matricule = htmlspecialchars($demande['matricule'] ?? '');
$student_nom = htmlspecialchars(($demande['prenom'] ?? '') . ' ' . ($demande['nom'] ?? ''));
$init_p = mb_strtoupper(mb_substr($demande['prenom'] ?? '', 0, 1, 'UTF-8'), 'UTF-8');
$init_n = mb_strtoupper(mb_substr($demande['nom'] ?? '', 0, 1, 'UTF-8'), 'UTF-8');
$initiales = (!empty($init_p) || !empty($init_n)) ? $init_p . $init_n : "ET";

$statut = $demande['statut_demande'] ?? 'En attente';
$current_pdf = $demande['document_pdf'] ?? null;
$current_code = $demande['code_secret'] ?? null;

$show_code = $current_code && (strpos($message_success ?? '', 'code secret') !== false);

// Compteur notifications admin
$admin_notif_count = 0;
try {
    $admin_notif_count = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE lue = 0")->fetchColumn();
} catch (PDOException $e) {}

// Avatar header
$admin_avatar = isset($_SESSION['admin_avatar']) ? $_SESSION['admin_avatar'] : null;
$admin_nom_header = 'Admin';
if (isset($_SESSION['admin_email'])) {
    try {
        $stmt_h = $pdo->prepare("SELECT nom, prenom FROM administrateurs WHERE email = ?");
        $stmt_h->execute([$_SESSION['admin_email']]);
        $h_admin = $stmt_h->fetch();
        if ($h_admin) {
            $admin_nom_header = htmlspecialchars($h_admin['prenom'] . ' ' . $h_admin['nom']);
        }
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Admin - Traitement Demande #<?= $id_demande; ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 24px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        .glass-card-strong {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(226,232,240,0.6);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
        }

        /* === DROP ZONE === */
        .drop-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 28px 20px;
            text-align: center;
            transition: all 0.25s ease;
            cursor: pointer;
            background: #f8fafc;
            position: relative;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #004A99;
            background: #eff6ff;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,74,153,0.10);
        }
        .drop-zone.has-file {
            border-color: #006e0c;
            background: #f0fdf4;
        }
        .drop-zone-icon { font-size: 40px; color: #94a3b8; transition: all 0.25s; }
        .drop-zone:hover .drop-zone-icon { color: #004A99; }
        .file-info {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-top: 12px;
        }
        .file-info.show { display: flex; }

        /* === SECRET CODE DISPLAY === */
        .secret-code-card {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            border-radius: 20px;
            padding: 24px 28px;
            position: relative;
            overflow: hidden;
        }
        .secret-code-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 50%, rgba(0,110,12,0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        .secret-code-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 70% 50%, rgba(0,74,153,0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        .secret-code {
            font-family: 'Courier New', monospace;
            font-size: 32px;
            font-weight: 900;
            letter-spacing: 12px;
            color: #fff;
            text-shadow: 0 0 30px rgba(0,110,12,0.3);
            animation: codeReveal 0.8s cubic-bezier(0.16,1,0.3,1) both;
            background: rgba(255,255,255,0.06);
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.08);
            display: inline-block;
            transform-origin: center;
        }
        @keyframes codeReveal {
            0% { opacity: 0; transform: scale(0.7) rotateX(90deg); filter: blur(8px); }
            100% { opacity: 1; transform: scale(1) rotateX(0); filter: blur(0); }
        }
        .code-glow { animation: glowPulse 2s ease-in-out infinite; }
        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(0,110,12,0.2); }
            50% { box-shadow: 0 0 40px rgba(0,110,12,0.4); }
        }

        /* === TIMELINE === */
        .timeline-track {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 0 4px;
        }
        .timeline-track::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 32px;
            right: 32px;
            height: 3px;
            background: #e2e8f0;
            border-radius: 2px;
        }
        .timeline-fill {
            position: absolute;
            top: 20px;
            left: 32px;
            height: 3px;
            background: linear-gradient(90deg, #004A99, #006e0c);
            border-radius: 2px;
            transition: width 0.8s cubic-bezier(0.16,1,0.3,1);
        }
        .tl-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            z-index: 2;
        }
        .tl-dot {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            transition: all 0.5s cubic-bezier(0.34,1.56,0.64,1);
            border: 3px solid #e2e8f0;
            background: white;
        }
        .tl-step.active .tl-dot {
            border-color: #004A99;
            background: #004A99;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,74,153,0.3);
        }
        .tl-step.completed .tl-dot {
            border-color: #006e0c;
            background: #006e0c;
            box-shadow: 0 4px 12px rgba(0,110,12,0.3);
        }
        .tl-step.rejected .tl-dot {
            border-color: #dc2626;
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(220,38,38,0.3);
        }
        .tl-label {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: #94a3b8; transition: color 0.3s;
        }
        .tl-step.active .tl-label { color: #004A99; }
        .tl-step.completed .tl-label { color: #006e0c; }
        .tl-step.rejected .tl-label { color: #dc2626; }

        .badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 14px; border-radius: 100px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.02em;
        }

        .fade-up {
            opacity: 0; transform: translateY(16px);
            animation: fadeUp 0.5s ease-out forwards;
        }
        .fade-up-d1 { animation-delay: 0.1s; }
        .fade-up-d2 { animation-delay: 0.2s; }
        .fade-up-d3 { animation-delay: 0.3s; }
        .fade-up-d4 { animation-delay: 0.4s; }
        .fade-up-d5 { animation-delay: 0.5s; }
        .fade-up-d6 { animation-delay: 0.6s; }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

        .slide-down {
            animation: slideDown 0.4s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes slideDown {
            0% { opacity: 0; transform: translateY(-12px) scaleY(0.95); }
            100% { opacity: 1; transform: translateY(0) scaleY(1); }
        }

        .toast-success {
            background: linear-gradient(135deg, #006e0c, #00a815);
            color: white;
            border-radius: 16px;
            padding: 16px 24px;
            box-shadow: 0 8px 32px rgba(0,110,12,0.25);
        }
        .toast-error {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            color: white;
            border-radius: 16px;
            padding: 16px 24px;
            box-shadow: 0 8px 32px rgba(220,38,38,0.25);
        }

        input[type="file"] { display: none; }

        .btn-primary {
            background: #004A99; color: white; padding: 12px 28px; border-radius: 14px;
            font-weight: 600; font-size: 14px; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
        }
        .btn-primary:hover { background: #00387a; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0,74,153,0.25); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

        .confetti-piece {
            position: fixed; width: 10px; height: 10px;
            top: -10px; z-index: 999;
            animation: confettiFall 3s ease-in forwards;
            pointer-events: none;
        }
        @keyframes blink-badge {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
        }
        .blink-badge { animation: blink-badge 1.2s ease-in-out infinite; }
        @keyframes bell-ring {
            0%, 100% { transform: rotate(0); }
            25% { transform: rotate(8deg); }
            75% { transform: rotate(-8deg); }
        }
        .bell-ring { animation: bell-ring 0.5s ease-in-out infinite; }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="h-16 bg-white/90 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-6 sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <img src="../images/IFRI.png" alt="IFRI" class="h-8 w-auto">
            <span class="font-bold text-lg text-[#004A99] tracking-tight">IFRI <span class="font-normal text-slate-400">/ Traitement</span></span>
        </div>
        <div class="flex items-center gap-4">
            <!-- Notification bell -->
            <a href="notifications_admin.php" class="relative inline-flex items-center justify-center w-8 h-8 rounded-full <?= $admin_notif_count > 0 ? 'bg-amber-100' : 'hover:bg-gray-100'; ?> transition-all">
                <span class="material-symbols-outlined <?= $admin_notif_count > 0 ? 'text-amber-600' : 'text-gray-500'; ?>" style="font-size:18px;">notifications</span>
                <?php if ($admin_notif_count > 0): ?>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow-lg"><?= min($admin_notif_count, 99); ?></span>
                <?php endif; ?>
            </a>
            <span class="text-sm font-medium text-slate-500"><?= $admin_nom_header; ?></span>
            <?php if ($admin_avatar): ?>
                <img src="<?= $admin_avatar; ?>" class="w-9 h-9 rounded-full object-cover border border-gray-200 shadow-md" alt="Avatar"/>
            <?php else: ?>
                <div class="w-9 h-9 rounded-full bg-[#004A99] text-white flex items-center justify-center font-bold text-xs shadow-md">AD</div>
            <?php endif; ?>
        </div>
    </header>

    <div class="max-w-5xl mx-auto p-4 md:p-8">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-slate-400 mb-6 fade-up fade-up-d1">
            <a href="dashboard_admin.php" class="hover:text-[#004A99] transition-colors">Dashboard</a>
            <span>/</span>
            <a href="mes_demandes_admin.php" class="hover:text-[#004A99] transition-colors">Demandes</a>
            <span>/</span>
            <span class="text-slate-700 font-semibold">#<?= $id_demande; ?></span>
        </div>

        <!-- En-tête -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-7 fade-up fade-up-d2">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Demande #<?= $id_demande; ?></h1>
                    <?php
                        $b_colors = match($statut) {
                            'En attente' => 'bg-amber-100 text-amber-700',
                            'En cours' => 'bg-blue-100 text-blue-700',
                            'Prêt', 'Terminée' => 'bg-green-100 text-green-700',
                            'Rejeté' => 'bg-red-100 text-red-700',
                            default => 'bg-slate-100 text-slate-700'
                        };
                    ?>
                    <span class="badge <?= $b_colors; ?>">
                        <span class="w-2 h-2 rounded-full <?= match($statut) { 'En attente' => 'bg-amber-500', 'En cours' => 'bg-blue-500', 'Prêt','Terminée' => 'bg-green-500', 'Rejeté' => 'bg-red-500', default => 'bg-slate-400' }; ?>"></span>
                        <?= $statut; ?>
                    </span>
                </div>
                <p class="text-slate-500 text-sm mt-1">Gérez le document et le statut de cette demande</p>
            </div>
        </div>

        <!-- Messages toast -->
        <?php if (!empty($message_success)): ?>
            <div class="toast-success flex items-center gap-3 mb-6 fade-up slide-down">
                <span class="material-symbols-outlined">check_circle</span>
                <span class="font-medium"><?= $message_success; ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($message_error)): ?>
            <div class="toast-error flex items-center gap-3 mb-6 fade-up slide-down">
                <span class="material-symbols-outlined">error</span>
                <span class="font-medium"><?= $message_error; ?></span>
            </div>
        <?php endif; ?>

        <!-- AFFICHAGE DU CODE SECRET APRÈS GÉNÉRATION -->
        <?php if ($show_code): ?>
            <div class="fade-up fade-up-d3 mb-6">
                <div class="secret-code-card">
                    <div class="flex items-center justify-between relative z-10">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-emerald-300/70"> Code Secret Généré</p>
                            <p class="text-sm text-slate-400 mt-1">Transmettez ce code à l'étudiant pour télécharger son document</p>
                        </div>
                        <button onclick="copierCode()" class="text-xs bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-sm">content_copy</span> Copier
                        </button>
                    </div>
                    <div class="mt-4 text-center relative z-10">
                        <span class="secret-code code-glow" id="secretCode"><?= htmlspecialchars($current_code); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- COLONNE GAUCHE (2/3) -->
            <div class="lg:col-span-2 flex flex-col gap-6">

                <!-- Carte étudiant -->
                <div class="glass-card-strong p-6 fade-up fade-up-d3">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#004A99] to-[#00387a] text-white flex items-center justify-center font-bold text-2xl shadow-lg shrink-0">
                            <?= $initiales; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h2 class="text-xl font-bold text-slate-900 truncate"><?= $student_nom; ?></h2>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-sm text-slate-500">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">badge</span>
                                    <?= $matricule; ?>
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">mail</span>
                                    <?= $student_email; ?>
                                </span>
                            </div>
                        </div>
                        <div class="hidden sm:block text-right">
                            <p class="text-xs text-slate-400 font-medium">Document</p>
                            <p class="text-sm font-bold text-[#004A99]"><?= $doc_libelle; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Détails du document -->
                <div class="glass-card-strong p-6 fade-up fade-up-d4">
                    <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">description</span>
                        Détails de la demande
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Type de document</p>
                            <p class="text-base font-bold text-[#004A99] mt-1"><?= $doc_libelle; ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Date de soumission</p>
                            <p class="text-base font-semibold text-slate-800 mt-1"><?= date('d F Y à H:i', strtotime($demande['date_demande'] ?? 'now')); ?></p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Motif de l'étudiant</p>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-slate-700 italic text-sm leading-relaxed">
                            <?php if (!empty($demande['motif'])): ?>
                                "<?= htmlspecialchars($demande['motif']); ?>"
                            <?php else: ?>
                                <span class="text-slate-400">Aucun motif spécifié (nouveau formulaire simplifié).</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Commentaire admin -->
                    <?php if (!empty($demande['commentaire_admin'])): ?>
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Commentaire de l'administration</p>
                            <div class="bg-blue-50 p-4 rounded-xl border border-blue-200 text-slate-700 text-sm leading-relaxed">
                                <?= htmlspecialchars($demande['commentaire_admin']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Document déjà uploadé -->
                    <?php if ($current_pdf && file_exists(__DIR__ . '/../' . $current_pdf)): ?>
                        <div class="mt-5 pt-4 border-t border-slate-100">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Document PDF</p>
                            <div class="flex items-center justify-between bg-green-50 border border-green-200 rounded-xl p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center text-green-600">
                                        <span class="material-symbols-outlined">picture_as_pdf</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-green-800">Document prêt</p>
                                        <p class="text-xs text-green-600">Téléchargé le <?= date('d/m/Y H:i'); ?></p>
                                    </div>
                                </div>
                                <a href="../<?= $current_pdf; ?>" target="_blank" onclick="return previewFile('../<?= $current_pdf; ?>', 'Demande #<?= $current_code; ?>');" class="inline-flex items-center gap-1.5 bg-white text-[#004A99] font-semibold text-sm px-4 py-2 rounded-xl border border-green-200 hover:bg-green-50 transition-colors">
                                    <span class="material-symbols-outlined text-sm">download</span> Voir PDF
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Code secret déjà généré -->
                    <?php if ($current_code && !$show_code): ?>
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between bg-slate-800 rounded-xl p-4">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-emerald-400">lock</span>
                                    <div>
                                        <p class="text-sm font-semibold text-white">Code secret généré</p>
                                        <p class="text-xs text-slate-400">Communiquez ce code à l'étudiant</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-mono font-bold text-lg tracking-[6px] text-emerald-400"><?= htmlspecialchars($current_code); ?></span>
                                    <button onclick="copierCodeSimple('<?= htmlspecialchars($current_code); ?>')" class="text-white/60 hover:text-white transition-colors">
                                        <span class="material-symbols-outlined text-sm">content_copy</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pièces fournies par l'étudiant -->
                <div class="glass-card-strong p-6 fade-up fade-up-d5">
                    <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">inventory_2</span>
                        Pièces fournies par l'étudiant
                        <?php if (count($pieces_uploaded) > 0): ?>
                            <span class="ml-auto text-xs font-bold bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full"><?= count($pieces_uploaded); ?> fichier(s)</span>
                        <?php endif; ?>
                    </h3>

                    <?php if (count($pieces_uploaded) > 0): ?>
                        <div class="space-y-3">
                            <?php foreach ($pieces_uploaded as $i => $piece): ?>
                                <div class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 bg-white hover:border-blue-200 hover:bg-blue-50/30 transition-all">
                                    <!-- Numéro -->
                                    <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-xs font-bold shrink-0">
                                        <?= $i + 1; ?>
                                    </span>

                                    <!-- Infos -->
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($piece['nom_piece']); ?></p>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            <?= date('d/m/Y H:i', strtotime($piece['date_upload'])); ?>
                                            · <?php
                                                $ext = strtolower(pathinfo($piece['fichier'], PATHINFO_EXTENSION));
                                                echo strtoupper($ext);
                                            ?>
                                        </p>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex items-center gap-2 shrink-0">
                                        <?php
                                            $file_full = __DIR__ . '/../' . $piece['fichier'];
                                            $file_exists = file_exists($file_full);
                                        ?>
                                        <?php if ($file_exists): ?>
                                            <a href="../<?= htmlspecialchars($piece['fichier']); ?>" target="_blank"
                                               onclick="return previewFile('../<?= htmlspecialchars($piece['fichier']); ?>', '<?= htmlspecialchars(addslashes($piece['nom_piece'])); ?>');"
                                               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-50 text-blue-700 rounded-lg text-xs font-semibold hover:bg-blue-100 transition-all border border-blue-200">
                                                <span class="material-symbols-outlined text-sm">visibility</span>
                                                Voir
                                            </a>
                                            <a href="../<?= htmlspecialchars($piece['fichier']); ?>" download
                                               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-50 text-slate-600 rounded-lg text-xs font-semibold hover:bg-slate-100 transition-all border border-slate-200">
                                                <span class="material-symbols-outlined text-sm">download</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-red-500 italic flex items-center gap-1">
                                                <span class="material-symbols-outlined text-sm">error_outline</span>
                                                Fichier introuvable
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700 flex items-start gap-2">
                            <span class="material-symbols-outlined text-sm shrink-0 mt-0.5">info</span>
                            <span>Vérifiez attentivement chaque document avant de prendre une décision. En cas de doute, contactez l'étudiant.</span>
                        </div>
                    <?php else: ?>
                        <div class="p-6 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-center">
                            <span class="material-symbols-outlined text-slate-300" style="font-size: 32px;">cloud_off</span>
                            <p class="text-sm text-slate-500 mt-2">Aucune pièce jointe pour cette demande.</p>
                            <p class="text-xs text-slate-400 mt-1">L'étudiant n'a pas encore uploadé les documents requis.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Timeline -->
                <div class="glass-card-strong p-6 fade-up fade-up-d5">
                    <h3 class="text-base font-bold text-slate-900 mb-5 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">timeline</span>
                        Progression
                    </h3>
                    <?php
                        $is_final = $statut === 'Prêt' || $statut === 'Terminée';
                        $is_rejected = $statut === 'Rejeté';
                        $in_progress = $statut === 'En cours';
                        $is_pending = $statut === 'En attente';

                        $step1 = 'completed';
                        $step2 = $in_progress || $is_final ? 'completed' : ($is_pending ? '' : '');
                        $step3 = $is_final ? 'completed' : ($is_rejected ? 'rejected' : '');

                        if ($is_rejected) { $step1 = 'completed'; $step2 = 'rejected'; $step3 = 'rejected'; }

                        $progress = $is_rejected ? 100 : ($is_final ? 100 : ($in_progress ? 55 : 10));
                    ?>
                    <div class="timeline-track">
                        <div class="timeline-fill" style="width: <?= $progress; ?>%"></div>

                        <div class="tl-step <?= $step1; ?>">
                            <div class="tl-dot">
                                <?php if ($step1 === 'completed' || $is_rejected): ?>
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M4.5 12.75l6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php else: ?>
                                    <span class="text-slate-400 text-sm font-bold">1</span>
                                <?php endif; ?>
                            </div>
                            <span class="tl-label">Reçue</span>
                        </div>

                        <div class="tl-step <?= $step2; ?>">
                            <div class="tl-dot">
                                <?php if ($step2 === 'completed'): ?>
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M4.5 12.75l6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php elseif ($is_rejected): ?>
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php elseif ($in_progress): ?>
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M12 6v6l4 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php else: ?>
                                    <span class="text-slate-400 text-sm font-bold">2</span>
                                <?php endif; ?>
                            </div>
                            <span class="tl-label"><?= $is_rejected ? 'Rejet' : 'Traitement'; ?></span>
                        </div>

                        <div class="tl-step <?= $step3; ?>">
                            <div class="tl-dot">
                                <?php if ($step3 === 'completed'): ?>
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M4.5 12.75l6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php elseif ($is_rejected): ?>
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php else: ?>
                                    <span class="text-slate-400 text-sm font-bold">3</span>
                                <?php endif; ?>
                            </div>
                            <span class="tl-label"><?= $is_rejected ? 'Rejeté' : 'Prêt'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLONNE DROITE (1/3) : ACTIONS ADMIN -->
            <div class="lg:col-span-1">
                <div class="glass-card-strong p-6 sticky top-24 fade-up fade-up-d4">
                    <h3 class="text-base font-bold text-slate-900 mb-5 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-400">gavel</span>
                        Actions
                    </h3>

                    <form action="" method="POST" enctype="multipart/form-data" class="flex flex-col gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Statut</label>
                            <select id="statut_select" name="statut_demande" onchange="toggleRejet(this.value); toggleUpload(this.value);"
                                class="w-full p-3 rounded-xl border border-slate-200 focus:border-[#004A99] focus:ring-1 focus:ring-[#004A99] bg-white text-sm font-semibold transition-all">
                                <option value="En attente" <?= $statut === 'En attente' ? 'selected' : ''; ?>> En attente</option>
                                <option value="En cours" <?= $statut === 'En cours' ? 'selected' : ''; ?>> En cours</option>
                                <option value="Prêt" <?= $statut === 'Prêt' ? 'selected' : ''; ?> <?= $statut === 'Prêt' || $statut === 'Terminée' ? 'disabled' : ''; ?>> Prêt / Disponible</option>
                                <option value="Rejeté" <?= $statut === 'Rejeté' ? 'selected' : ''; ?> <?= $statut === 'Rejeté' ? 'disabled' : ''; ?>> Rejeté</option>
                            </select>
                        </div>

                        <!-- Zone d'upload PDF (visible seulement pour "Prêt") -->
                        <div id="upload_section" class="hidden">
                            <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Document PDF</label>
                            <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                                <div class="drop-zone-icon" id="dropIcon">
                                    <span class="material-symbols-outlined" style="font-size:40px;">cloud_upload</span>
                                </div>
                                <p class="text-sm font-semibold text-slate-700 mt-2">
                                    <span class="text-[#004A99]">Cliquez</span> pour parcourir
                                </p>
                                <p class="text-xs text-slate-400 mt-1">ou glissez-déposez votre PDF ici</p>
                                <input type="file" id="fileInput" name="document_pdf" accept=".pdf" onchange="handleFile(this)"/>
                                <div class="file-info" id="fileInfo">
                                    <span class="material-symbols-outlined text-green-600 text-sm">picture_as_pdf</span>
                                    <span class="text-sm font-medium text-slate-700 flex-1 truncate" id="fileName"></span>
                                    <span class="text-xs text-slate-400" id="fileSize"></span>
                                    <button type="button" onclick="removeFile(event)" class="text-red-500 hover:text-red-700">
                                        <span class="material-symbols-outlined text-sm">close</span>
                                    </button>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">PDF uniquement, max 20 Mo</p>
                        </div>

                        <!-- Motif / Commentaire (visible pour tous les statuts) -->
                        <div id="motif_section">
                            <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider" id="motif_label">Motif / Commentaire</label>
                            <textarea name="motif_admin" rows="3" id="motif_textarea"
                                class="w-full p-3 rounded-xl border border-slate-200 focus:border-[#004A99] focus:ring-1 focus:ring-[#004A99] text-sm bg-white resize-none"
                                placeholder="Ajoutez un commentaire ou un motif..."><?= htmlspecialchars($demande['commentaire_admin'] ?? ''); ?></textarea>
                        </div>

                        <!-- Motif de rejet (supplémentaire) -->
                        <div id="rejet_section" class="hidden">
                            <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Motif du rejet</label>
                            <textarea name="motif_rejet" rows="3"
                                class="w-full p-3 rounded-xl border border-red-200 focus:border-red-500 focus:ring-1 focus:ring-red-500 text-sm bg-white resize-none"
                                placeholder="Ex: Document illisible..."><?= $demande['motif_rejet'] ?? ''; ?></textarea>
                        </div>

                        <!-- Info si déjà Prêt ou Rejeté -->
                        <?php if ($statut === 'Prêt' || $statut === 'Terminée'): ?>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-700">
                                <div class="flex items-center gap-2 font-semibold mb-1">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    Document déjà finalisé
                                </div>
                                <p class="text-xs text-emerald-600">Ce document est marqué comme prêt.</p>
                            </div>
                        <?php elseif ($statut === 'Rejeté'): ?>
                            <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
                                <div class="flex items-center gap-2 font-semibold mb-1">
                                    <span class="material-symbols-outlined text-sm">cancel</span>
                                    Demande rejetée
                                </div>
                            </div>
                        <?php endif; ?>

                        <button type="submit" name="update_statut" id="submitBtn"
                            class="w-full mt-2 bg-[#004A99] text-white font-bold py-3 rounded-xl text-sm shadow-sm hover:bg-[#00387a] active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="material-symbols-outlined text-sm">save</span>
                            Enregistrer
                        </button>

                        <a href="mes_demandes_admin.php" class="block text-center text-xs text-slate-400 hover:text-slate-600 transition-colors mt-1">
                            ← Retour aux demandes
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleRejet(val) {
            document.getElementById('rejet_section').classList.toggle('hidden', val !== 'Rejeté');
            var label = document.getElementById('motif_label');
            var textarea = document.getElementById('motif_textarea');
            if (val === 'Rejeté') {
                label.textContent = 'Commentaire supplémentaire';
                textarea.placeholder = 'Ajoutez un commentaire si nécessaire...';
            } else if (val === 'En cours') {
                label.textContent = 'Motif de mise en cours';
                textarea.placeholder = 'Ex: Demande prise en charge, vérification en cours...';
            } else if (val === 'En attente') {
                label.textContent = 'Motif de mise en attente';
                textarea.placeholder = 'Ex: En attente de documents complémentaires...';
            } else if (val === 'Prêt') {
                label.textContent = 'Commentaire (optionnel)';
                textarea.placeholder = 'Ajoutez un commentaire si nécessaire...';
            }
        }

        function toggleUpload(val) {
            const upload = document.getElementById('upload_section');
            const select = document.getElementById('statut_select');
            if (val === 'Prêt') {
                upload.classList.remove('hidden');
                upload.classList.add('slide-down');
                select.classList.add('border-[#006e0c]', 'ring-1', 'ring-[#006e0c]');
            } else {
                upload.classList.add('hidden');
                upload.classList.remove('slide-down');
                select.classList.remove('border-[#006e0c]', 'ring-1', 'ring-[#006e0c]');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sel = document.getElementById('statut_select');
            toggleRejet(sel.value);
            toggleUpload(sel.value);
        });

        // Drag & Drop
        const dropZone = document.getElementById('dropZone');
        ['dragenter','dragover'].forEach(evt => {
            dropZone.addEventListener(evt, function(e) { e.preventDefault(); e.stopPropagation(); this.classList.add('dragover'); });
        });
        ['dragleave','drop'].forEach(evt => {
            dropZone.addEventListener(evt, function(e) { e.preventDefault(); e.stopPropagation(); this.classList.remove('dragover'); });
        });
        dropZone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('fileInput').files = files;
                handleFile({ files: files });
            }
        });

        function handleFile(input) {
            const files = input.files || input.target?.files;
            if (!files || !files.length) return;
            const file = files[0];
            if (file.type !== 'application/pdf') {
                alert('Seuls les fichiers PDF sont acceptés.');
                document.getElementById('fileInput').value = '';
                return;
            }
            if (file.size > 20 * 1024 * 1024) {
                alert('Le fichier ne doit pas dépasser 20 Mo.');
                document.getElementById('fileInput').value = '';
                return;
            }
            const info = document.getElementById('fileInfo');
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = (file.size / 1024 / 1024).toFixed(1) + ' Mo';
            info.classList.add('show');
            dropZone.classList.add('has-file');
            document.getElementById('dropIcon').innerHTML = '<span class="material-symbols-outlined" style="font-size:40px;color:#006e0c;">check_circle</span>';
        }

        function removeFile(e) {
            e.stopPropagation();
            document.getElementById('fileInput').value = '';
            document.getElementById('fileInfo').classList.remove('show');
            dropZone.classList.remove('has-file');
            document.getElementById('dropIcon').innerHTML = '<span class="material-symbols-outlined" style="font-size:40px;">cloud_upload</span>';
        }

        function copierCode() {
            const code = document.getElementById('secretCode').textContent;
            navigator.clipboard.writeText(code).then(() => {
                const btn = event.currentTarget;
                btn.innerHTML = '<span class="material-symbols-outlined text-sm">check</span> Copié !';
                setTimeout(() => { btn.innerHTML = '<span class="material-symbols-outlined text-sm">content_copy</span> Copier'; }, 2000);
            });
        }

        function copierCodeSimple(code) {
            navigator.clipboard.writeText(code).then(() => {
                const btn = event.currentTarget;
                btn.innerHTML = '<span class="material-symbols-outlined text-sm text-emerald-400">check</span>';
                setTimeout(() => { btn.innerHTML = '<span class="material-symbols-outlined text-sm">content_copy</span>'; }, 2000);
            });
        }

        // Confetti si code secret généré
        <?php if ($show_code): ?>
        (function() {
            const colors = ['#004A99','#006e0c','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6'];
            for (let i = 0; i < 60; i++) {
                const el = document.createElement('div');
                el.className = 'confetti-piece';
                el.style.left = Math.random() * 100 + 'vw';
                el.style.background = colors[Math.floor(Math.random() * colors.length)];
                el.style.width = (Math.random() * 8 + 4) + 'px';
                el.style.height = (Math.random() * 8 + 4) + 'px';
                el.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
                el.style.animationDuration = (Math.random() * 2 + 2) + 's';
                el.style.animationDelay = (Math.random() * 1.5) + 's';
                document.body.appendChild(el);
                setTimeout(() => el.remove(), 5000);
            }
        })();
        <?php endif; ?>

    </script>

<script src="../assets/js/app.js"></script>
</body>
</html>
