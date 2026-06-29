<?php
declare(strict_types=1);

session_start();

// ── 1. Sécurité : Vérifier si l'étudiant est bien connecté ─────
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// ── 2. Connexion à la base de données ───────────────────────────
require_once __DIR__ . '/ifri_gestion_docs.php';
$pdo = getPDO();

// ── 3. Traitement des données du formulaire ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type_document = trim($_POST['type_doc'] ?? '');
    $id_etudiant = (int) $_SESSION['user_id'];

    // Vérification : type de document requis + au moins un fichier
    if (!empty($type_document) && isset($_FILES['piece_file']) && !empty($_FILES['piece_file']['name'][0])) {

        try {
            $pdo->beginTransaction();

            // ── 3a. Insérer la demande ────────────────────────────
            $query = "INSERT INTO demandes (id_etudiant, id_type_doc, statut_demande, date_demande)
                      VALUES (:id_etudiant, :id_type_doc, 'En attente', NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':id_etudiant', $id_etudiant, PDO::PARAM_INT);
            $stmt->bindValue(':id_type_doc', intval($type_document), PDO::PARAM_INT);
            $stmt->execute();

            $id_demande = (int) $pdo->lastInsertId();

            // ── 3b. Dossier pour les fichiers ─────────────────────
            $upload_dir = __DIR__ . '/uploads/pieces/demande_' . $id_demande;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // ── 3c. Parcourir chaque fichier uploadé ──────────────
            $files = $_FILES['piece_file'];
            $noms = $_POST['piece_nom'] ?? [];
            $uploaded_ok = 0;

            foreach ($files['name'] as $i => $name) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if (empty($name)) continue;

                $tmpPath = $files['tmp_name'][$i];
                $nom_piece = $noms[$i] ?? 'Pièce ' . ($i + 1);

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $safe_name = 'piece_' . time() . '_' . $i . '.' . $ext;
                $destPath = $upload_dir . '/' . $safe_name;

                if (move_uploaded_file($tmpPath, $destPath)) {
                    $relative_path = 'uploads/pieces/demande_' . $id_demande . '/' . $safe_name;

                    $stmt2 = $pdo->prepare(
                        "INSERT INTO pieces_demandes (id_demande, nom_piece, fichier, date_upload)
                         VALUES (:id_demande, :nom_piece, :fichier, NOW())"
                    );
                    $stmt2->execute([
                        ':id_demande' => $id_demande,
                        ':nom_piece'  => $nom_piece,
                        ':fichier'    => $relative_path,
                    ]);
                    $uploaded_ok++;
                }
            }

            if ($uploaded_ok === 0) {
                $pdo->rollBack();
                header('Location: mes_demandes.php?error=no_files_uploaded');
                exit;
            }

            $pdo->commit();

            // ── 3d. Notification admin de nouvelle demande ──────────
            try {
                $info_etud = $pdo->prepare("SELECT nom, prenom, matricule FROM etudiants WHERE id_etudiant = ?");
                $info_etud->execute([$id_etudiant]);
                $etu = $info_etud->fetch();
                $type_libelle = $pdo->prepare("SELECT libelle FROM types_documents WHERE id_type = ?");
                $type_libelle->execute([intval($type_document)]);
                $type = $type_libelle->fetch();
                if ($etu && $type) {
                    $admin_notif = $pdo->prepare("INSERT INTO admin_notifications (titre, message, type_notif, id_etudiant, id_demande, cree_at) VALUES (?, ?, 'nouvelle_demande', ?, ?, NOW())");
                    $admin_notif->execute([
                        "📝 Nouvelle demande — " . $type['libelle'],
                        $etu['prenom'] . " " . $etu['nom'] . " (" . $etu['matricule'] . ") a soumis une demande de " . strtolower($type['libelle']) . ".",
                        $id_etudiant,
                        $id_demande
                    ]);
                }
            } catch (PDOException $e) {
                // La notification admin ne doit pas bloquer la soumission
            }

            header('Location: mes_demandes.php?success=1');
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[DB ERROR] add_request : ' . $e->getMessage());
            die("Erreur lors de la soumission de la demande. Veuillez réessayer.");
        }
    } else {
        $error = 'missing_fields';
        if (!empty($type_document) && (!isset($_FILES['piece_file']) || empty($_FILES['piece_file']['name'][0]))) {
            $error = 'missing_files';
        }
        header('Location: mes_demandes.php?error=' . $error);
        exit;
    }
} else {
    header('Location: mes_demandes.php');
    exit;
}