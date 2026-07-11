<?php
session_start();

// 1. Sécurité : Vérifier si l'étudiant est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 2. Connexion à la base de données via db.php
require_once __DIR__ . '/ifri_gestion_docs.php';

$id_etudiant = $_SESSION['user_id'];
$upload_error = '';
$upload_success = '';

// Notifications non lues
$non_lues = 0;
try {
    $nn = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_etudiant = ? AND lue = 0");
    $nn->execute([$id_etudiant]);
    $non_lues = $nn->fetchColumn();
} catch (PDOException $e) {
    $non_lues = 0;
}

// Récupération des messages de succès ou d'erreur via l'URL (si add_request.php redirige ici avec des paramètres)
if (isset($_GET['success'])) {
    if ($_GET['success'] == 2) {
        $upload_success = 'Photo de profil enregistrée avec succès.';
    } elseif ($_GET['success'] == 3) {
        $upload_success = 'Photo de profil supprimée avec succès.';
    } elseif ($_GET['success'] == 4) {
        $upload_success = 'Votre demande de document a été soumise avec succès !';
    }
}

// --- LOGIQUE DE MISE À JOUR DE LA PHOTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_profile_photo'])) {
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = 'Veuillez sélectionner une photo valide.';
    } else {
        $file = $_FILES['profile_photo'];
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!isset($allowedTypes[$mimeType])) {
            $upload_error = 'Le format doit être JPG, PNG, GIF ou WEBP.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $upload_error = 'La photo doit être inférieure à 2 Mo.';
        } else {
            $extension = $allowedTypes[$mimeType];
            $uploadDir = __DIR__ . '/uploads/profile_photos';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = 'profile_' . $id_etudiant . '.' . $extension;
            $destination = $uploadDir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $relativePath = 'uploads/profile_photos/' . $filename;
                $_SESSION['user_photo'] = $relativePath;
                header('Location: profile.php?success=2');
                exit;
            } else {
                $upload_error = 'Impossible de sauvegarder la photo. Réessayez.';
            }
        }
    }
}

// --- LOGIQUE DE SUPPRESSION DE LA PHOTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile_photo'])) {
    $photo_path_session = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : null;
    if (!empty($photo_path_session)) {
        $fullPath = __DIR__ . '/' . $photo_path_session;
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }
        unset($_SESSION['user_photo']);
        header('Location: profile.php?success=3');
        exit;
    } else {
        $upload_error = 'Aucune photo à supprimer.';
    }
}

// --- RECUPERATION DES DONNEES DEPUIS LA BDD ---
$stmt = $pdo->prepare("SELECT * FROM etudiants WHERE id_etudiant = :id");
$stmt->execute(['id' => $id_etudiant]);
$etudiant_bdd = $stmt->fetch();

$nom = !empty($etudiant_bdd['nom']) ? trim($etudiant_bdd['nom']) : (isset($_SESSION['nom']) ? trim($_SESSION['nom']) : '');
$prenom = !empty($etudiant_bdd['prenom']) ? trim($etudiant_bdd['prenom']) : (isset($_SESSION['prenom']) ? trim($_SESSION['prenom']) : '');
$nom_complet = !empty($nom) ? trim($prenom . ' ' . $nom) : "Étudiant Connecté";
$matricule = !empty($etudiant_bdd['matricule']) ? $etudiant_bdd['matricule'] : (isset($_SESSION['user_matricule']) ? $_SESSION['user_matricule'] : "IFRI-XXXX-XXXX");
$email = !empty($etudiant_bdd['email']) ? $etudiant_bdd['email'] : (isset($_SESSION['email']) ? $_SESSION['email'] : "etudiant@ifri.uac.bj");
$photo_path = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : null;

$initiale_prenom = (!empty($prenom)) ? mb_strtoupper(mb_substr($prenom, 0, 1, 'UTF-8'), 'UTF-8') : '';
$initiale_nom = (!empty($nom)) ? mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8') : '';
$initiales = (!empty($initiale_prenom) || !empty($initiale_nom)) ? $initiale_prenom . $initiale_nom : "ET";

$stmt = $pdo->prepare("SELECT d.*, t.libelle FROM demandes d JOIN types_documents t ON d.id_type_doc = t.id_type WHERE d.id_etudiant = :id ORDER BY d.date_demande DESC LIMIT 3");
$stmt->execute(['id' => $id_etudiant]);
$activites = $stmt->fetchAll();

// Types de documents pour le modal
$stmt_types = $pdo->query("SELECT * FROM types_documents WHERE actif = 1 OR actif IS NULL ORDER BY id_type");
$types_docs = $stmt_types->fetchAll();

$pieces_map = [
    1 => [
        'Copie de fiche d\'inscription',
        'Copie simple du dernier diplôme',
        'Photo d\'identité',
        'Reçu de paiement (si applicable)',
    ],
    2 => [
        'Copie du relevé de notes complet',
        'Attestation de session',
    ],
    3 => [
        'Copie du relevé de notes',
        'Attestation de succès',
    ],
    4 => [
        'Copie de la scolarité originale',
        'Constat de perte (déclaration)',
        'Photo d\'identité',
        'Reçu de paiement',
        'Demande manuscrite timbrée',
    ],
    5 => [
        'Lettre de réclamation détaillée',
        'Copie du document contesté (si disponible)',
        'Preuve de dépôt ou de paiement',
    ],
    6 => [
        'Copie de la caution ou du contrat',
        'Attestation de fin de formation',
        'Certificat de libération',
        'Photo d\'identité',
        'Reçu de paiement',
    ],
    7 => [
        'Copie du diplôme original',
        'Relevé de notes définitif',
        'Photo d\'identité',
        'Reçu de paiement',
        'Demande manuscrite timbrée',
    ],
    8 => [
        'Copie des documents originaux à certifier',
        'Photo d\'identité',
        'Reçu de paiement',
        'Demande manuscrite timbrée',
    ],
    9 => [
        'Copie du relevé de notes',
        'Attestation de scolarité',
        'Photo d\'identité',
        'Reçu de paiement',
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Mon Profil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
          theme: {
            extend: {
              fontFamily: { sans: ['Inter', 'sans-serif'] },
              colors: {
                ifri: {
                  blue: '#0056b3',
                  darkBlue: '#003d7a',
                  lightGreen: '#90f090',
                  bgGray: '#f8fafc',
                }
              }
            }
          }
        }
    </script>
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .sidebar-item-active { background-color: #90f090; color: #00730D; }
        .profile-banner-height { height: 160px; }
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background-color: rgba(0, 0, 0, 0.4);
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
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
<body class="flex h-screen overflow-hidden">

<aside class="w-64 bg-white border-r border-gray-200 flex flex-col flex-shrink-0">
    <div class="p-6">
        <div class="flex items-center gap-3 mb-1">
            <img src="./images/IFRI.png" alt="Logo IFRI" class="w-12 h-12 object-contain" />
        </div>
        <h1 class="text-xl font-bold text-ifri-darkBlue">IFRI Portail</h1>
        <p class="text-xs text-gray-500 font-medium">Gestion des documents</p>
    </div>
    <nav class="flex-1 px-4 space-y-2 mt-4">
        <a class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" href="dashboard.php">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            <span class="text-sm font-medium">Tableau de bord</span>
        </a>
        <a class="flex items-center gap-3 px-3 py-2.5 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" href="mes_demandes.php">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            <span class="text-sm font-medium">Demandes</span>
        </a>
        <a class="flex items-center gap-3 px-3 py-2.5 text-gray-800 sidebar-item-active rounded-lg shadow-sm" href="profile.php">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            <span class="text-sm font-medium">Profile</span>
        </a>
    </nav>
    <div class="p-4 border-t border-gray-100 space-y-4">
        <button onclick="openModal('requestModal')" class="w-full bg-[#003d7a] text-white py-3 px-4 rounded-lg flex items-center justify-center gap-2 font-medium hover:bg-opacity-90 transition-colors text-center cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            Nouvelle demande
        </button>
        <div class="space-y-1 pt-4">
            <a class="flex items-center gap-3 px-3 py-2 text-gray-600 hover:text-gray-900 transition-colors" href="settings.php">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                <span class="text-sm font-medium">Paramètres</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-2 text-red-600 hover:text-red-700 transition-colors" href="index.php">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                <span class="text-sm font-medium">Déconnecter</span>
            </a>
        </div>
    </div>
</aside>

<div class="flex-1 flex flex-col overflow-hidden">
    <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 z-10">
        <div class="relative w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            </span>
            <input class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-full leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-ifri-blue focus:border-ifri-blue sm:text-sm" placeholder="Rechercher..." type="text"/>
        </div>
        <div class="flex items-center gap-4">
            <!-- Notification bell -->
            <a href="notifications.php" class="relative inline-flex items-center justify-center w-8 h-8 rounded-full <?= $non_lues > 0 ? 'bg-amber-100' : 'hover:bg-gray-100'; ?> transition-all" title="Notifications">
                <span class="material-symbols-outlined <?= $non_lues > 0 ? 'text-amber-600' : 'text-gray-500'; ?>" style="font-size:18px;">notifications</span>
                <?php if ($non_lues > 0): ?>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow-lg"><?= min($non_lues, 99); ?></span>
                <?php endif; ?>
            </a>
            <!-- Help button -->
            <a href="faq.php" class="inline-flex items-center justify-center w-9 h-9 rounded-full hover:bg-gray-100 transition-all text-gray-500" title="Aide">
                <span class="material-symbols-outlined" style="font-size:20px;">help</span>
            </a>
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-ifri-darkBlue"><?= htmlspecialchars($nom_complet) ?></span>
                <?php if (!empty($photo_path) && is_file(__DIR__ . '/' . $photo_path)): ?>
                    <img src="<?= htmlspecialchars($photo_path) ?>" alt="Photo de profil" class="w-10 h-10 rounded-full object-cover border border-gray-200" />
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-[#003d7a] text-white flex items-center justify-center font-bold text-sm border border-gray-200">
                        <?= htmlspecialchars($initiales) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-1 overflow-y-auto p-8 bg-[#f0f4f8]">
        <div class="max-w-6xl mx-auto">

            <?php if (!empty($upload_error)): ?>
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm font-medium">
                    <?= htmlspecialchars($upload_error) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($upload_success)): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-2xl text-sm font-medium">
                    <?= htmlspecialchars($upload_success) ?>
                </div>
            <?php endif; ?>

            <section class="relative mb-12">
                <div class="w-full profile-banner-height bg-ifri-darkBlue rounded-2xl"></div>
                <div class="px-8 flex items-end -mt-10 gap-6">
                    <div class="relative">
                        <?php if (!empty($photo_path) && is_file(__DIR__ . '/' . $photo_path)): ?>
                            <img src="<?= htmlspecialchars($photo_path) ?>" alt="Photo de profil" class="h-32 w-32 rounded-2xl border-4 border-white object-cover shadow-lg" />
                        <?php else: ?>
                            <div class="h-32 w-32 rounded-2xl border-4 border-white flex items-center justify-center shadow-lg bg-[#003d7a] text-white text-4xl font-black">
                                <?= htmlspecialchars($initiales) ?>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-1 right-1 h-4 w-4 bg-green-500 border-2 border-white rounded-full"></div>
                    </div>
                    <div class="flex-1 pb-2">
                        <h2 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($nom_complet) ?></h2>
                        <p class="text-gray-500 font-medium">Étudiant inscrit à l'IFRI</p>
                    </div>
                    <div class="pb-2">
                        <button onclick="openModal('requestModal')" class="text-white px-6 py-3 rounded-lg text-sm font-semibold flex items-center gap-2 hover:bg-opacity-90 shadow-sm transition-colors cursor-pointer" style="background-color: #006E0C;">
                            <span class="text-lg">+</span> Nouvelle Demande
                        </button>
                    </div>
                </div>

                <div class="mt-6 px-8">
                    <div class="grid gap-4 sm:grid-cols-2 items-end">
                        <form method="post" enctype="multipart/form-data" action="profile.php">
                            <label class="flex flex-col gap-2 rounded-2xl border border-gray-200 bg-white p-4 text-gray-700 shadow-sm hover:border-ifri-blue transition-colors cursor-pointer">
                                <span class="text-sm font-medium">Photo de profil</span>
                                <span class="text-xs text-gray-500">Sélectionnez une image JPEG, PNG ou WEBP.</span>
                                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" required>
                                <span class="mt-2 inline-flex items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-4 text-sm text-gray-500">Cliquez pour choisir un fichier</span>
                            </label>
                            <button type="submit" name="upload_profile_photo" class="mt-3 w-full rounded-2xl bg-ifri-darkBlue px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 transition-colors">Mettre à jour la photo</button>
                        </form>

                        <div class="space-y-2">
                            <?php if (!empty($photo_path) && is_file(__DIR__ . '/' . $photo_path)): ?>
                                <form method="post" action="profile.php" id="deleteProfilePhotoForm">
                                    <button type="submit" name="delete_profile_photo" class="w-full rounded-2xl border border-red-200 bg-red-50 px-5 py-3 text-sm font-semibold text-red-600 shadow-sm hover:bg-red-100 transition-colors flex items-center justify-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-16v1a3 3 0 003 3h4a3 3 0 003-3V3a3 3 0 00-3-3h-4a3 3 0 00-3 3zm-4 4h8" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                        Supprimer la photo actuelle
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="space-y-8">
                    <div class="bg-white rounded-2xl border border-gray-100 p-8 shadow-sm">
                        <div class="flex items-center gap-3 mb-8">
                            <svg class="w-6 h-6 text-ifri-blue" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                            <h3 class="text-xl font-bold text-ifri-darkBlue">Informations Personnelles</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-4">
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold mb-1">Nom Complet</p>
                                <p class="text-base font-bold text-gray-800 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100"><?= htmlspecialchars($nom_complet) ?></p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold mb-1">Matricule</p>
                                <p class="text-base font-bold text-gray-800 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100"><?= htmlspecialchars($matricule) ?></p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-[10px] uppercase tracking-wider text-gray-400 font-bold mb-1">Email Institutionnel</p>
                                <p class="text-base font-bold text-gray-800 bg-gray-50 px-3 py-2 rounded-lg border border-gray-100"><?= htmlspecialchars($email) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    <div class="bg-white rounded-2xl border border-gray-100 p-8 shadow-sm h-full">
                        <div class="flex items-center justify-between mb-8">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-ifri-blue" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                <h3 class="text-xl font-bold text-ifri-darkBlue">Activité Récente</h3>
                            </div>
                            <a class="text-ifri-blue text-sm font-semibold hover:underline" href="mes_demandes.php">Voir tout</a>
                        </div>
                        <div class="space-y-6 relative">
                            <div class="absolute left-[18px] top-4 bottom-4 w-0.5 bg-gray-100"></div>

                            <?php if(empty($activites)): ?>
                                <p class="text-sm text-gray-500 pl-8">Aucune activité récente trouvée sur votre compte.</p>
                            <?php else: ?>
                                <?php foreach($activites as $act): ?>
                                    <div class="flex gap-6 relative z-10">
                                        <?php if($act['statut_demande'] === 'Prêt'): ?>
                                            <div class="h-9 w-9 bg-green-100 rounded-lg flex items-center justify-center text-green-600 flex-shrink-0">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                            </div>
                                        <?php else: ?>
                                            <div class="h-9 w-9 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 flex-shrink-0">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex flex-col">
                                            <p class="font-bold text-gray-800">Demande : <?= htmlspecialchars($act['libelle'] ?? 'Document #' . $act['id_type_doc']) ?></p>
                                            <p class="text-sm text-gray-400">Statut : <span class="font-semibold text-ifri-darkBlue"><?= htmlspecialchars($act['statut_demande']) ?></span> • Soumis le <?= date('d M Y', strtotime($act['date_demande'])) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- ===== MODAL NOUVELLE DEMANDE ===== -->
<div class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4 modal-backdrop" id="requestModal">
    <div class="bg-white w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl shadow-2xl animate-in fade-in zoom-in duration-200">
        <!-- En-tête -->
        <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center bg-gradient-to-r from-[#003d7a] to-[#004d99] rounded-t-2xl">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                Nouvelle Demande
            </h3>
            <button class="text-white/80 hover:bg-white/20 p-2 rounded-full transition-colors" onclick="closeModal('requestModal')">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            </button>
        </div>

        <form class="p-6 space-y-6" method="POST" action="add_request.php" enctype="multipart/form-data">
            <!-- Type de document -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#003d7a]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                        Type de Document <span class="text-red-500">*</span>
                    </span>
                </label>
                <select id="type_doc" name="type_doc" onchange="afficherPieces(this.value)"
                    class="w-full p-3.5 rounded-xl border border-gray-200 bg-white text-sm font-medium focus:border-[#003d7a] focus:ring-2 focus:ring-[#003d7a]/20 transition-all">
                    <option value="" disabled selected>-- Sélectionnez un type de document --</option>
                    <?php foreach ($types_docs as $td): ?>
                        <option value="<?= (int)$td['id_type']; ?>">
                            <?= htmlspecialchars($td['libelle']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Pièces à uploader (affichage dynamique) -->
            <div id="pieces_section" class="hidden">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 space-y-4">
                    <div class="flex items-center gap-2 text-blue-800 font-bold text-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                        Upload des pièces requises
                    </div>
                    <p class="text-xs text-blue-700">Veuillez <strong>uploader chaque pièce</strong> au format PDF, JPG ou PNG.</p>
                    <div id="pieces_list" class="space-y-3">
                        <!-- Rempli dynamiquement par JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Boutons -->
            <div class="flex gap-4 pt-2">
                <button type="button" onclick="closeModal('requestModal')"
                    class="flex-1 py-3.5 border-2 border-gray-200 text-gray-600 rounded-xl font-bold text-sm hover:bg-gray-50 hover:border-gray-300 transition-all">
                    Annuler
                </button>
                <button type="submit" id="submitBtn" disabled
                    class="flex-1 py-3.5 bg-[#006E0C] text-white rounded-xl font-bold text-sm shadow-lg shadow-green-500/20 hover:bg-[#005a0a] hover:shadow-xl disabled:opacity-40 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    Envoyer la demande
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const PIECES_MAP = {
        <?php foreach ($pieces_map as $id => $pieces): ?>
        <?= $id; ?>: <?= json_encode($pieces); ?>,
        <?php endforeach; ?>
    };

    function openModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        document.getElementById('type_doc').value = '';
        document.getElementById('pieces_section').classList.add('hidden');
        document.getElementById('pieces_list').innerHTML = '';
        document.getElementById('submitBtn').disabled = true;
    }

    function closeModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    window.addEventListener('click', function(event) {
        if (event.target && event.target.classList && event.target.classList.contains('modal-backdrop')) {
            event.target.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    });

    function afficherPieces(typeId) {
        var section = document.getElementById('pieces_section');
        var list = document.getElementById('pieces_list');
        var pieces = PIECES_MAP[typeId];
        if (!pieces || pieces.length === 0) {
            section.classList.add('hidden');
            return;
        }
        list.innerHTML = '';
        pieces.forEach(function(p, index) {
            var div = document.createElement('div');
            div.className = 'flex flex-col sm:flex-row sm:items-center gap-3 p-3 bg-white rounded-lg border border-blue-100';
            div.innerHTML = '<div class="flex items-center gap-2 min-w-0 flex-1">' +
                '<span class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold shrink-0">' + (index + 1) + '</span>' +
                '<span class="text-sm font-medium text-gray-700 truncate">' + p + '</span>' +
            '</div>' +
            '<div class="flex items-center gap-2 shrink-0">' +
                '<input type="hidden" name="piece_nom[]" value="' + p.replace(/"/g, '&quot;') + '">' +
                '<label class="cursor-pointer flex items-center gap-2 px-4 py-2 bg-white border-2 border-dashed border-blue-300 rounded-lg text-xs font-semibold text-blue-600 hover:bg-blue-50 hover:border-blue-400 transition-all file-upload-label" id="label_' + index + '">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>' +
                    '<span id="file_text_' + index + '">Choisir un fichier</span>' +
                '</label>' +
                '<input type="file" name="piece_file[]" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp" class="hidden" id="file_' + index + '" onchange="onFileSelect(' + index + ')">' +
                '<span id="file_status_' + index + '" class="hidden text-green-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg></span>' +
            '</div>';
            list.appendChild(div);

            var label = div.querySelector('.file-upload-label');
            var fileInput = div.querySelector('input[type="file"]');
            label.addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.click();
            });
        });
        section.classList.remove('hidden');
        toggleSubmit();
    }

    function onFileSelect(index) {
        var input = document.getElementById('file_' + index);
        var text = document.getElementById('file_text_' + index);
        var status = document.getElementById('file_status_' + index);
        var label = document.getElementById('label_' + index);
        if (input.files && input.files[0]) {
            var name = input.files[0].name;
            text.textContent = name.length > 30 ? name.substring(0, 27) + '...' : name;
            label.className = 'cursor-pointer flex items-center gap-2 px-4 py-2 bg-green-50 border-2 border-solid border-green-300 rounded-lg text-xs font-semibold text-green-700 transition-all';
            status.classList.remove('hidden');
        } else {
            text.textContent = 'Choisir un fichier';
            label.className = 'cursor-pointer flex items-center gap-2 px-4 py-2 bg-white border-2 border-dashed border-blue-300 rounded-lg text-xs font-semibold text-blue-600 hover:bg-blue-50 hover:border-blue-400 transition-all';
            status.classList.add('hidden');
        }
        toggleSubmit();
    }

    function toggleSubmit() {
        var typeOk = document.getElementById('type_doc').value !== '';
        var allFilesOk = true;
        var pieces = document.querySelectorAll('input[name="piece_file[]"]');
        if (pieces.length === 0) { allFilesOk = false; }
        pieces.forEach(function(input) {
            if (!input.files || !input.files[0]) { allFilesOk = false; }
        });
        document.getElementById('submitBtn').disabled = !(typeOk && allFilesOk);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var sel = document.getElementById('type_doc');
        if (sel.value) afficherPieces(sel.value);
    });
</script>


<script src="assets/js/app.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const delForm = document.getElementById('deleteProfilePhotoForm');
        if (delForm) {
            delForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const ok = await confirmModal('Es-tu sûr de vouloir supprimer ta photo de profil ?', { type: 'danger' });
                if (ok) this.submit();
            });
        }
    });
</script>
<?php require_once 'contact_modal.php'; ?>
</body>
</html>
