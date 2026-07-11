<?php
session_start();

// 1. Sécurité : Vérifier si l'étudiant est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 2. Récupération des données de session
$nom = isset($_SESSION['nom']) ? trim($_SESSION['nom']) : '';
$prenom = isset($_SESSION['prenom']) ? trim($_SESSION['prenom']) : '';
$nom_complet = !empty($nom) ? trim($prenom . ' ' . $nom) : "Jean Dupont";

// Génération des initiales pour l'avatar
$initiale_prenom = (!empty($prenom)) ? mb_strtoupper(mb_substr($prenom, 0, 1, 'UTF-8'), 'UTF-8') : '';
$initiale_nom = (!empty($nom)) ? mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8') : '';
$initiales = (!empty($initiale_prenom) || !empty($initiale_nom)) ? $initiale_prenom . $initiale_nom : "JD";
$photo_path = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : null;

// 3. Traitement du changement de mot de passe
$message_success = "";
$message_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Connexion BDD
    require_once __DIR__ . '/ifri_gestion_docs.php';

    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (!empty($old_password) && !empty($new_password)) {
        // Vérifier l'ancien mot de passe
        $stmt = $pdo->prepare("SELECT mot_de_passe FROM etudiants WHERE id_etudiant = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($old_password, $user['mot_de_passe'])) {
            if (strlen($new_password) >= 6) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE etudiants SET mot_de_passe = ? WHERE id_etudiant = ?");
                $update->execute([$new_hash, $_SESSION['user_id']]);
                $message_success = "Mot de passe mis à jour avec succès !";
            } else {
                $message_error = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
            }
        } else {
            $message_error = "L'ancien mot de passe est incorrect.";
        }
    } else {
        $message_error = "Veuillez remplir tous les champs.";
    }
}

// Connexion BDD pour les types de documents
if (!isset($pdo)) {
    require_once __DIR__ . '/ifri_gestion_docs.php';
}

$stmt_types = $pdo->query("SELECT * FROM types_documents WHERE actif = 1 OR actif IS NULL ORDER BY id_type");
$types_docs = $stmt_types->fetchAll();

// Notifications non lues
$non_lues = 0;
try {
    $nn = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_etudiant = ? AND lue = 0");
    $nn->execute([$_SESSION['user_id']]);
    $non_lues = $nn->fetchColumn();
} catch (PDOException $e) {
    $non_lues = 0;
}

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
<html class="light" lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Docs - Paramètres du Compte</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
          theme: {
            extend: {
              "colors": {
                      "on-tertiary-container": "#ffc395",
                      "secondary-container": "#8ff780",
                      "on-tertiary-fixed-variant": "#6e3900",
                      "background": "#f8f9fa",
                      "surface-container-low": "#f3f4f5",
                      "tertiary-fixed-dim": "#ffb77d",
                      "inverse-on-surface": "#f0f1f2",
                      "surface": "#f8f9fa",
                      "error-container": "#ffdad6",
                      "surface-tint": "#115cb9",
                      "surface-variant": "#e1e3e4",
                      "inverse-surface": "#2e3132",
                      "secondary": "#006e0c",
                      "outline-variant": "#c2c6d4",
                      "error": "#ba1a1a",
                      "on-surface": "#191c1d",
                      "on-error-container": "#93000a",
                      "on-secondary-fixed": "#002201",
                      "surface-bright": "#f8f9fa",
                      "primary-fixed-dim": "#acc7ff",
                      "on-primary-container": "#bbd0ff",
                      "on-error": "#ffffff",
                      "surface-container-lowest": "#ffffff",
                      "on-tertiary-fixed": "#2f1500",
                      "on-primary-fixed-variant": "#004491",
                      "tertiary": "#663400",
                      "on-tertiary": "#ffffff",
                      "surface-container-high": "#e7e8e9",
                      "on-surface-variant": "#424752",
                      "surface-dim": "#d9dadb",
                      "on-primary": "#ffffff",
                      "on-background": "#191c1d",
                      "on-primary-fixed": "#001a40",
                      "on-secondary": "#ffffff",
                      "surface-container": "#edeeef",
                      "primary-container": "#90f090", /* Teinte vert clair pour l'onglet actif */
                      "on-primary-container-text": "#000000",
                      "primary": "#003d7a", /* Couleur bleue IFRI */
                      "secondary-fixed-dim": "#77dd6a",
                      "outline": "#727784",
                      "inverse-primary": "#acc7ff",
                      "tertiary-fixed": "#ffdcc3",
                      "on-secondary-container": "#00730d",
                      "primary-fixed": "#d7e2ff",
                      "on-secondary-fixed-variant": "#005307",
                      "tertiary-container": "#884800",
                      "surface-container-highest": "#e1e3e4",
                      "secondary-fixed": "#92fa83"
              },
              "borderRadius": {
                      "DEFAULT": "0.25rem",
                      "lg": "0.5rem",
                      "xl": "0.75rem",
                      "full": "9999px"
              },
              "spacing": {
                      "xl": "64px",
                      "gutter": "24px",
                      "sm": "12px",
                      "md": "24px",
                      "container-max": "1280px",
                      "base": "8px",
                      "lg": "40px",
                      "xs": "4px"
              },
              "fontFamily": {
                      "display-lg": ["Inter"],
                      "headline-lg-mobile": ["Inter"],
                      "body-md": ["Inter"],
                      "label-sm": ["Inter"],
                      "headline-lg": ["Inter"],
                      "body-lg": ["Inter"],
                      "headline-md": ["Inter"],
                      "label-md": ["Inter"]
              },
              "fontSize": {
                      "display-lg": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                      "headline-lg-mobile": ["28px", {"lineHeight": "36px", "fontWeight": "600"}],
                      "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                      "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}],
                      "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                      "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                      "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                      "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.01em", "fontWeight": "500"}]
              }
            },
          },
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
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
<body class="flex min-h-screen">

<aside class="fixed left-0 top-0 h-full w-64 bg-surface-container-lowest border-r border-outline-variant flex flex-col py-6 px-md gap-md overflow-y-auto z-40 hidden md:flex">
    <div class="px-sm">
        <div class="flex items-center gap-3 mb-1">
            <img src="./images/IFRI.png" alt="Logo IFRI" class="w-12 h-12 object-contain" />
        </div>
        <h1 class="text-xl font-bold text-[#003d7a]">IFRI Portail</h1>
        <p class="text-xs text-gray-500 font-medium">Gestion des documents</p>
    </div>

    <nav class="flex flex-col gap-xs mt-6 flex-grow">
        <a class="flex items-center gap-md p-sm text-on-surface-variant hover:text-primary transition-all duration-200 active:scale-95 rounded-lg" href="dashboard.php">
            <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
            <span class="text-label-md font-medium">Tableau de bord</span>
        </a>
        <a class="flex items-center gap-md p-sm text-on-surface-variant hover:text-primary transition-all duration-200 active:scale-95 rounded-lg" href="mes_demandes.php">
            <span class="material-symbols-outlined" data-icon="description">description</span>
            <span class="text-label-md font-medium">Mes Demandes</span>
        </a>
        <a class="flex items-center gap-md p-sm text-on-surface-variant hover:text-primary transition-all duration-200 active:scale-95 rounded-lg" href="profile.php">
            <span class="material-symbols-outlined" data-icon="person">person</span>
            <span class="text-label-md font-medium">Profile</span>
        </a>
    </nav>

    <button class="mt-lg w-full py-md bg-primary text-on-primary rounded-xl font-bold flex items-center justify-center gap-sm shadow-sm hover:scale-[1.02] transition-transform" onclick="openModal('requestModal')">
            <span class="material-symbols-outlined" data-icon="add">add</span>
            <span>Nouvelle demande</span>
        </button>

    <div class="flex flex-col gap-xs pt-lg border-t border-outline-variant space-y-3">
        <a class="flex items-center gap-md p-sm bg-secondary-container text-on-secondary-container rounded-lg font-bold transition-all duration-200 active:scale-95" href="settings.php">
            <span class="material-symbols-outlined" data-icon="settings" style="font-variation-settings: 'FILL' 1;">settings</span>
            <span class="text-label-md font-label-md">Paramètres</span>
        </a>
        <a class="flex items-center gap-md p-sm text-red-600 hover:text-red-700 transition-all duration-200 active:scale-95 rounded-lg" href="index.php">
            <span class="material-symbols-outlined" data-icon="logout">logout</span>
            <span class="text-label-md font-medium">Déconnecter</span>
        </a>
    </div>
</aside>

<main class="flex-1 md:ml-64 flex flex-col bg-[#f0f4f8]">
    <header class="sticky top-0 z-50 flex justify-between items-center w-full px-md py-sm bg-white border-b border-outline-variant shadow-sm backdrop-blur-md bg-opacity-90">
        <div class="flex items-center gap-md">
            <button class="md:hidden text-primary">
                <span class="material-symbols-outlined" data-icon="menu">menu</span>
            </button>
            <div class="hidden md:flex bg-surface-container-high rounded-full px-md py-xs items-center gap-sm min-w-[300px]">
                <span class="material-symbols-outlined text-outline" data-icon="search">search</span>
                <input class="bg-transparent border-none focus:ring-0 text-body-md w-full text-sm" placeholder="Rechercher..." type="text"/>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <a href="notifications.php" class="relative inline-flex items-center justify-center w-8 h-8 rounded-full <?= $non_lues > 0 ? 'bg-amber-100' : 'hover:bg-gray-100'; ?> transition-all" title="Notifications">
                <span class="material-symbols-outlined <?= $non_lues > 0 ? 'text-amber-600' : 'text-gray-500'; ?>" style="font-size:18px;">notifications</span>
                <?php if ($non_lues > 0): ?>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow-lg"><?= min($non_lues, 99); ?></span>
                <?php endif; ?>
            </a>
            <a href="faq.php" class="text-gray-500 hover:text-primary p-2 rounded-full transition-colors" title="FAQ">
                <span class="material-symbols-outlined">help</span>
            </a>
            <a href="profile.php" class="flex items-center gap-3">
                <span class="text-sm font-semibold text-[#003d7a]"><?= htmlspecialchars($nom_complet) ?></span>
                <?php if (!empty($photo_path) && is_file(__DIR__ . '/' . $photo_path)): ?>
                    <img src="<?= htmlspecialchars($photo_path) ?>" alt="Photo" class="w-10 h-10 rounded-full object-cover border border-gray-200" />
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-[#003d7a] text-white flex items-center justify-center font-bold text-sm border border-gray-200">
                        <?= htmlspecialchars($initiales) ?>
                    </div>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <div class="w-full max-w-container-max mx-auto p-md md:p-lg space-y-lg">
        <div class="flex flex-col gap-xs mb-lg">
            <h2 class="text-3xl font-bold text-[#003d7a]">Paramètres du Compte</h2>
            <p class="text-gray-500 mt-1">Gérez votre sécurité, vos notifications et vos préférences de plateforme.</p>
        </div>

        <?php if(!empty($message_success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg text-sm font-medium">
                <?= htmlspecialchars($message_success) ?>
            </div>
        <?php endif; ?>
        <?php if(!empty($message_error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm font-medium">
                <?= htmlspecialchars($message_error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
            <form method="POST" action="settings.php" class="lg:col-span-8 bg-surface-container-lowest rounded-xl p-md md:p-lg border border-[#E9ECEF] shadow-sm hover:shadow-md transition-shadow duration-300">
                <div class="flex items-center gap-sm mb-lg border-b border-outline-variant pb-sm">
                    <span class="material-symbols-outlined text-[#003d7a]" data-icon="shield">shield</span>
                    <h3 class="text-xl font-bold text-gray-800">Sécurité &amp; Mot de passe</h3>
                </div>
                <div class="space-y-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                        <div class="flex flex-col gap-xs">
                            <label class="text-sm font-medium text-gray-600 mb-1">Ancien Mot de Passe</label>
                            <input name="old_password" required class="w-full p-sm bg-background border border-outline-variant rounded-lg focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm" type="password"/>
                        </div>
                        <div class="flex flex-col gap-xs">
                            <label class="text-sm font-medium text-gray-600 mb-1">Nouveau Mot de Passe</label>
                            <input name="new_password" required class="w-full p-sm bg-background border border-outline-variant rounded-lg focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all text-sm" type="password"/>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-md bg-surface-container-low rounded-lg border border-outline-variant">
                        <div class="flex items-center gap-md">
                            <div class="bg-[#d7e2ff] text-[#004491] p-sm rounded-lg flex items-center justify-center">
                                <span class="material-symbols-outlined" data-icon="vibration">vibration</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">Double Authentification (2FA)</p>
                                <p class="text-xs text-gray-500">Sécurisez votre compte avec un code de vérification.</p>
                            </div>
                        </div>
                        <button type="button" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out bg-[#003d7a]" onclick="this.classList.toggle('bg-[#003d7a]'); this.classList.toggle('bg-gray-300')">
                            <span class="translate-x-5 pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                        </button>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="text-white px-6 py-2.5 rounded-lg font-semibold hover:shadow-lg transition-all active:scale-95 text-sm" style="background-color: #006E0C;">
                            Mettre à jour la sécurité
                        </button>
                    </div>
                </div>
            </form>

            <div class="lg:col-span-4 space-y-gutter">
                <div class="bg-[#003d7a] text-white rounded-xl p-md overflow-hidden relative group shadow-sm">
                    <div class="relative z-10">
                        <h4 class="text-lg font-bold mb-xs">Besoin d'aide ?</h4>
                        <p class="text-xs opacity-90 mb-md leading-relaxed">Consultez notre guide de sécurité pour protéger au mieux vos données académiques et personnelles.</p>
                        <a class="inline-flex items-center gap-xs font-bold border-b-2 border-white pb-xs hover:gap-md transition-all text-xs" href="#">
                            Voir le guide <span class="material-symbols-outlined text-sm" data-icon="arrow_forward">arrow_forward</span>
                        </a>
                    </div>
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-700">
                        <span class="material-symbols-outlined text-[160px]" data-icon="auto_awesome">auto_awesome</span>
                    </div>
                </div>
            </div>

            <section class="lg:col-span-6 bg-surface-container-lowest rounded-xl p-md md:p-lg border border-[#E9ECEF] shadow-sm">
                <div class="flex items-center gap-sm mb-lg border-b border-outline-variant pb-sm">
                    <span class="material-symbols-outlined text-[#003d7a]" data-icon="notifications_active">notifications_active</span>
                    <h3 class="text-xl font-bold text-gray-800">Notifications</h3>
                </div>
                <div class="space-y-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Email de mise à jour</p>
                            <p class="text-xs text-gray-500">Recevoir le statut des demandes par email.</p>
                        </div>
                        <button type="button" class="toggle-btn relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out bg-[#003d7a]">
                            <span class="translate-x-5 inline-block h-5 w-5 transform rounded-full bg-white transition duration-200"></span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between border-t border-outline-variant pt-md">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Notifications Push</p>
                            <p class="text-xs text-gray-500">Alertes en temps réel directement sur votre navigateur.</p>
                        </div>
                        <button type="button" class="toggle-btn relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out bg-gray-300">
                            <span class="translate-x-0 inline-block h-5 w-5 transform rounded-full bg-white transition duration-200"></span>
                        </button>
                    </div>
                </div>
            </section>

            <section class="lg:col-span-6 bg-surface-container-lowest rounded-xl p-md md:p-lg border border-[#E9ECEF] shadow-sm">
                <div class="flex items-center gap-sm mb-lg border-b border-outline-variant pb-sm">
                    <span class="material-symbols-outlined text-[#003d7a]" data-icon="language">language</span>
                    <h3 class="text-xl font-bold text-gray-800">Localisation</h3>
                </div>
                <div class="space-y-md">
                    <div class="flex flex-col gap-xs">
                        <label class="text-xs font-medium text-gray-600 mb-1">Langue d'affichage</label>
                        <select class="w-full p-sm bg-background border border-outline-variant rounded-lg focus:border-primary outline-none transition-all text-sm">
                            <option>Français (FR)</option>
                            <option>English (US)</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-xs">
                        <label class="text-xs font-medium text-gray-600 mb-1">Fuseau Horaire</label>
                        <select class="w-full p-sm bg-background border border-outline-variant rounded-lg focus:border-primary outline-none transition-all text-sm">
                            <option>(GMT+01:00) West Central Africa (Cotonou)</option>
                            <option>(GMT+00:00) London</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="lg:col-span-12 bg-red-50 rounded-xl p-md md:p-lg border border-red-200 shadow-sm border-dashed border-2">
                <div class="flex items-center gap-sm mb-md text-red-600">
                    <span class="material-symbols-outlined" data-icon="warning">warning</span>
                    <h3 class="text-xl font-bold">Zone de Danger</h3>
                </div>
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-md">
                    <div>
                        <p class="text-sm font-bold text-gray-800">Désactiver le compte</p>
                        <p class="text-xs text-gray-500 mt-0.5">La désactivation de votre compte est temporaire. Vous perdrez l'accès immédiat à vos demandes en cours.</p>
                    </div>
                    <a href="logout.php" class="bg-red-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:shadow-md transition-all hover:bg-red-700 active:scale-95 text-sm whitespace-nowrap inline-flex items-center justify-center">
                        Désactiver mon compte
                    </a>
                </div>
            </section>
        </div>
    </div>
</main>

<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white px-md py-sm flex justify-around items-center border-t border-outline-variant z-50">
    <a class="flex flex-col items-center text-gray-500 text-xs" href="dashboard.php">
        <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
        <span>Tableau de bord</span>
    </a>
    <a class="flex flex-col items-center text-gray-500 text-xs" href="mes_demandes.php">
        <span class="material-symbols-outlined" data-icon="description">description</span>
        <span>Demandes</span>
    </a>
    <a class="flex flex-col items-center text-[#003d7a] font-bold text-xs" href="settings.php">
        <span class="material-symbols-outlined" data-icon="settings" style="font-variation-settings: 'FILL' 1;">settings</span>
        <span>Paramètres</span>
    </a>
    <a class="flex flex-col items-center text-gray-500 text-xs" href="profile.php">
        <span class="material-symbols-outlined" data-icon="person">person</span>
        <span>Profil</span>
    </a>
</nav>

<script>
    // Micro-interactions au focus
    document.querySelectorAll('input, select').forEach(el => {
        el.addEventListener('focus', () => {
            el.parentElement.classList.add('scale-[1.01]');
        });
        el.addEventListener('blur', () => {
            el.parentElement.classList.remove('scale-[1.01]');
        });
    });

    // Toggle Switch Interaction Logic pour les boutons de notification
    const toggles = document.querySelectorAll('.toggle-btn');
    toggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const dot = this.querySelector('span');
            if (dot.classList.contains('translate-x-5')) {
                dot.classList.replace('translate-x-5', 'translate-x-0');
                this.classList.replace('bg-[#003d7a]', 'bg-gray-300');
            } else {
                dot.classList.replace('translate-x-0', 'translate-x-5');
                this.classList.replace('bg-gray-300', 'bg-[#003d7a]');
            }
        });
    });
</script>

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
<?php require_once 'contact_modal.php'; ?>
</body>
</html>
