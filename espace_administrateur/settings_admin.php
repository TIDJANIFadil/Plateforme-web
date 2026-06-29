<?php
session_start();

// 1. Sécurité : Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

// 2. Connexion à la base de données
require_once __DIR__ . '/../ifri_gestion_docs.php';

// Compteur notifications admin
$admin_notif_count = 0;
try {
    $admin_notif_count = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE lue = 0")->fetchColumn();
} catch (PDOException $e) {}

// 3. Messages flash
$success_msg = '';
$error_msg = '';

// 4. Récupération des infos de l'admin connecté depuis la BDD
$admin_nom = 'Administrateur';
$admin_email = $_SESSION['admin_email'] ?? 'admin@ifri.bj';
$admin_avatar = isset($_SESSION['admin_avatar']) ? $_SESSION['admin_avatar'] : null;

if (isset($_SESSION['admin_email'])) {
    try {
        $stmt = $pdo->prepare("SELECT nom, prenom, email FROM administrateurs WHERE email = ?");
        $stmt->execute([$_SESSION['admin_email']]);
        $current_admin = $stmt->fetch();
        if ($current_admin) {
            $admin_nom_db = $current_admin;
            $admin_nom = htmlspecialchars($current_admin['prenom'] . ' ' . $current_admin['nom']);
            $admin_email = htmlspecialchars($current_admin['email']);
        }
    } catch (PDOException $e) {}
}

// --- TRAITEMENT DES FORMULAIRES ---

// 5. Mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_profile'])) {
    $new_nom = trim($_POST['nom'] ?? '');
    $new_prenom = trim($_POST['prenom'] ?? '');
    $new_email = trim($_POST['email'] ?? '');

    if (empty($new_nom) || empty($new_prenom) || empty($new_email)) {
        $error_msg = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Adresse email invalide.';
    } else {
        try {
            // Vérifier si l'email est déjà pris par un autre admin
            $check = $pdo->prepare("SELECT id_admin FROM administrateurs WHERE email = ? AND email != ?");
            $check->execute([$new_email, $_SESSION['admin_email']]);
            if ($check->rowCount() > 0) {
                $error_msg = 'Cet email est déjà utilisé par un autre administrateur.';
            } else {
                $stmt = $pdo->prepare("UPDATE administrateurs SET nom = ?, prenom = ?, email = ? WHERE email = ?");
                $stmt->execute([strtoupper($new_nom), $new_prenom, $new_email, $_SESSION['admin_email']]);

                $_SESSION['admin_email'] = $new_email;
                $_SESSION['admin_nom'] = $new_prenom . ' ' . strtoupper($new_nom);
                $success_msg = 'Profil mis à jour avec succès.';
                $admin_email = htmlspecialchars($new_email);
                $admin_nom = htmlspecialchars($new_prenom . ' ' . strtoupper($new_nom));
            }
        } catch (PDOException $e) {
            $error_msg = 'Erreur lors de la mise à jour du profil.';
        }
    }
}

// 6. Changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = 'Tous les champs sont obligatoires.';
    } elseif ($new_password !== $confirm_password) {
        $error_msg = 'Les nouveaux mots de passe ne correspondent pas.';
    } elseif (strlen($new_password) < 6) {
        $error_msg = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT mot_de_passe FROM administrateurs WHERE email = ?");
            $stmt->execute([$_SESSION['admin_email']]);
            $admin_data = $stmt->fetch();

            if (!$admin_data) {
                // Fallback : l'admin n'est pas encore en BDD => vérifier avec le hash par défaut
                $default_hash = '$2y$12$lmw6.YOk6yAiyGC/MbWzoubFAnQzvFPMdXLpi/R0FQ/h2yvPsYGWe'; // admin123
                if (!password_verify($current_password, $default_hash)) {
                    $error_msg = 'Le mot de passe actuel est incorrect.';
                } else {
                    $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $check = $pdo->prepare("SELECT id_admin FROM administrateurs WHERE email = ?");
                    $check->execute([$_SESSION['admin_email']]);
                    if ($check->rowCount() === 0) {
                        $insert = $pdo->prepare("INSERT INTO administrateurs (nom, prenom, email, mot_de_passe, role) VALUES (?, ?, ?, ?, ?)");
                        $insert->execute(['Admin', 'Principal', $_SESSION['admin_email'], $new_hash, 'Administrateur Principal']);
                    } else {
                        $update = $pdo->prepare("UPDATE administrateurs SET mot_de_passe = ? WHERE email = ?");
                        $update->execute([$new_hash, $_SESSION['admin_email']]);
                    }
                    $success_msg = 'Mot de passe changé avec succès.';
                }
            } elseif (!password_verify($current_password, $admin_data['mot_de_passe'])) {
                $error_msg = 'Le mot de passe actuel est incorrect.';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE administrateurs SET mot_de_passe = ? WHERE email = ?");
                $update->execute([$new_hash, $_SESSION['admin_email']]);
                $success_msg = 'Mot de passe changé avec succès.';
            }
        } catch (PDOException $e) {
            $error_msg = 'Erreur lors du changement de mot de passe.';
        }
    }
}

// 7. Ajout d'un type de document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_doc'])) {
    $libelle = trim($_POST['libelle'] ?? '');
    if (!empty($libelle)) {
        try {
            // Vérifier si le type existe déjà
            $check = $pdo->prepare("SELECT id_type FROM types_documents WHERE libelle = ?");
            $check->execute([$libelle]);
            if ($check->rowCount() > 0) {
                $error_msg = 'Ce type de document existe déjà.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO types_documents (libelle) VALUES (?)");
                $stmt->execute([$libelle]);
                $success_msg = 'Type de document ajouté avec succès.';
            }
        } catch (PDOException $e) {
            $error_msg = 'Erreur lors de l\'ajout du type de document.';
        }
    } else {
        $error_msg = 'Veuillez saisir un libellé.';
    }
}

// 8. Activation/Désactivation d'un type de document
if (isset($_GET['toggle_doc']) && is_numeric($_GET['toggle_doc'])) {
    $id_type = (int)$_GET['toggle_doc'];
    try {
        $stmt = $pdo->prepare("SELECT actif FROM types_documents WHERE id_type = ?");
        $stmt->execute([$id_type]);
        $doc = $stmt->fetch();
        if ($doc) {
            $new_status = $doc['actif'] ? 0 : 1;
            $update = $pdo->prepare("UPDATE types_documents SET actif = ? WHERE id_type = ?");
            $update->execute([$new_status, $id_type]);
            $success_msg = $new_status ? 'Type de document activé.' : 'Type de document désactivé.';
        }
    } catch (PDOException $e) {
        // La colonne actif n'existe peut-être pas encore
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $error_msg = 'La colonne "actif" n\'existe pas encore dans la table. Exécutez : ALTER TABLE types_documents ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1 AFTER libelle;';
        } else {
            $error_msg = 'Erreur lors de la modification du statut.';
        }
    }
}

// 9. Récupération des types de documents
$types_documents = [];
try {
    $types_documents = $pdo->query("SELECT * FROM types_documents ORDER BY libelle ASC")->fetchAll();
} catch (PDOException $e) {
    $error_docs = $e->getMessage();
}

// 10. Compter les types actifs
$count_actifs = 0;
foreach ($types_documents as $doc) {
    if (isset($doc['actif']) && $doc['actif']) $count_actifs++;
}
$count_total = count($types_documents);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Paramètres</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
        .active-nav { background-color: #93F08D; color: #065f46; }
        .ifri-blue { color: #003d7a; }
        .ifri-bg-blue { background-color: #003d7a; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .modal-backdrop { backdrop-filter: blur(6px); background-color: rgba(0, 0, 0, 0.35); }
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

<!-- BARRE LATÉRALE -->
<aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full z-20">
    <div class="p-6">
        <div class="h-12 w-12 bg-primary text-white font-extrabold flex items-center justify-center rounded-xl mb-1">
            <img src="../images/IFRI.png" alt="Logo IFRI" />
        </div>
        <h1 class="text-xl font-bold ifri-blue">IFRI Portail</h1>
        <p class="text-xs text-gray-500">Gestion des documents</p>
    </div>
    <nav class="flex-1 px-4 mt-4 space-y-2">
        <a class="flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors" href="dashboard_admin.php">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            <span class="font-medium">Tableau de bord</span>
        </a>
        <a class="flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors" href="mes_demandes_admin.php">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            <span class="font-medium">Demandes</span>
        </a>
        <a class="flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors" href="profile_admin.php">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            <span class="font-medium">Profile</span>
        </a>
    </nav>
    <div class="space-y-4 px-4 mb-6">
        <a class="flex items-center px-4 py-3 bg-[#92fa83] text-[#002201] rounded-xl font-medium shadow-sm border border-[#77dd6a]/30" href="settings_admin.php">
            <svg class="w-5 h-5 mr-3 text-[#005307]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
            </svg>
            <span class="text-[#1B4D16]">Paramètres</span>
        </a>
        <a class="flex items-center text-red-500 hover:text-red-700 transition-colors" href="../index.php">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            <span class="font-medium text-sm">Déconnecter</span>
        </a>
    </div>
</aside>

<!-- CONTENU PRINCIPAL -->
<main class="flex-1 ml-64 overflow-y-auto h-screen">
    <!-- HEADER -->
    <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10">
        <div class="relative w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
            </span>
            <input class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-full bg-gray-50 text-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher une demande..." type="text"/>
        </div>
        <div class="flex items-center space-x-6">
            <a href="notifications_admin.php" class="relative inline-flex items-center justify-center w-9 h-9 rounded-full <?= $admin_notif_count > 0 ? 'bg-amber-100 bell-ring' : 'hover:bg-gray-100'; ?> transition-all">
                <span class="material-symbols-outlined <?= $admin_notif_count > 0 ? 'text-amber-600' : 'text-gray-500'; ?>" style="font-size:20px;">notifications</span>
                <?php if ($admin_notif_count > 0): ?>
                    <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center shadow-lg px-1 blink-badge"><?= min($admin_notif_count, 99); ?></span>
                <?php else: ?>
                    <span class="absolute top-1 right-1 h-2 w-2 bg-red-400 rounded-full"></span>
                <?php endif; ?>
            </a>
            <button class="text-gray-500 hover:bg-gray-100 p-2 rounded-full transition-colors">
                <span class="material-symbols-outlined">help</span>
            </button>
            <a href="profile_admin.php" class="flex items-center space-x-3">
                <span class="text-sm font-semibold text-slate-700"><?= $admin_nom; ?></span>
                <?php if ($admin_avatar): ?>
                    <img src="<?= $admin_avatar; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200" alt="Avatar"/>
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-[#003d7a] text-white flex items-center justify-center font-bold text-sm border border-gray-200">ADM</div>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <div class="max-w-7xl mx-auto p-8">
        <!-- Titre de la page -->
        <div class="mb-8">
            <h2 class="text-3xl font-extrabold text-[#003d7a] tracking-tight">Paramètres</h2>
            <p class="text-sm text-gray-500 mt-1">Gérez votre profil et les types de documents.</p>
        </div>

        <!-- Messages flash -->
        <?php if (!empty($success_msg)): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-5 py-3.5 rounded-xl text-sm flex items-center gap-3">
                <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                <?= htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-5 py-3.5 rounded-xl text-sm flex items-center gap-3">
                <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                <?= htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <!-- ===== COLONNE DE GAUCHE : PROFIL ===== -->
            <div class="lg:col-span-3 space-y-8">

                <!-- CARTE : INFORMATIONS PERSONNELLES -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-7 py-5 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-[#003d7a]/10 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-[#003d7a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Informations personnelles</h3>
                                <p class="text-xs text-gray-500">Modifiez votre nom, prénom et email</p>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="" class="p-7 space-y-5">
                        <input type="hidden" name="action_update_profile" value="1">
                        <?php
                        // Récupérer les valeurs actuelles depuis la BDD pour pré-remplir
                        $prenom_val = '';
                        $nom_val = '';
                        if (isset($current_admin)) {
                            $prenom_val = htmlspecialchars($current_admin['prenom'] ?? '');
                            $nom_val = htmlspecialchars($current_admin['nom'] ?? '');
                        }
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Prénom</label>
                                <input type="text" name="prenom" value="<?= $prenom_val; ?>"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-[#003d7a] focus:ring-1 focus:ring-[#003d7a] transition-all" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nom</label>
                                <input type="text" name="nom" value="<?= $nom_val; ?>"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-[#003d7a] focus:ring-1 focus:ring-[#003d7a] transition-all" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email professionnel</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($admin_email); ?>"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-[#003d7a] focus:ring-1 focus:ring-[#003d7a] transition-all" required>
                        </div>
                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="px-6 py-2.5 bg-[#003d7a] text-white rounded-xl text-sm font-semibold hover:bg-[#002d5a] transition-all shadow-sm active:scale-[0.98]">
                                Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>

                <!-- CARTE : CHANGER LE MOT DE PASSE -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-7 py-5 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-[#003d7a]/10 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-[#003d7a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Mot de passe</h3>
                                <p class="text-xs text-gray-500">Changez votre mot de passe de connexion</p>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="" class="p-7 space-y-5">
                        <input type="hidden" name="action_change_password" value="1">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mot de passe actuel</label>
                            <input type="password" name="current_password"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-[#003d7a] focus:ring-1 focus:ring-[#003d7a] transition-all" required>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nouveau mot de passe</label>
                                <input type="password" name="new_password"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-[#003d7a] focus:ring-1 focus:ring-[#003d7a] transition-all" required minlength="6">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password"
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-[#003d7a] focus:ring-1 focus:ring-[#003d7a] transition-all" required minlength="6">
                            </div>
                        </div>
                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="px-6 py-2.5 bg-[#003d7a] text-white rounded-xl text-sm font-semibold hover:bg-[#002d5a] transition-all shadow-sm active:scale-[0.98]">
                                Changer le mot de passe
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ===== COLONNE DE DROITE : TYPES DE DOCUMENTS ===== -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden sticky top-24">
                    <div class="px-7 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-[#003d7a]/10 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-[#003d7a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Types de documents</h3>
                                <p class="text-xs text-gray-500"><?= $count_actifs; ?> actifs sur <?= $count_total; ?></p>
                            </div>
                        </div>
                        <button onclick="openDocModal()" class="px-4 py-2 bg-[#003d7a] text-white rounded-xl text-sm font-semibold hover:bg-[#002d5a] transition-all shadow-sm flex items-center gap-1.5 active:scale-[0.98]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M12 4.5v15m7.5-7.5h-15" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Ajouter
                        </button>
                    </div>

                    <div class="p-2">
                        <?php if (!empty($types_documents)): ?>
                            <div class="space-y-0.5">
                                <?php foreach ($types_documents as $doc):
                                    $is_active = isset($doc['actif']) && $doc['actif'];
                                ?>
                                    <div class="flex items-center justify-between px-5 py-3 rounded-xl hover:bg-gray-50 transition-colors group">
                                        <div class="flex items-center gap-3">
                                            <div class="w-2 h-2 rounded-full <?= $is_active ? 'bg-green-500' : 'bg-gray-300'; ?> shadow-sm <?= $is_active ? 'shadow-green-500/30' : ''; ?>"></div>
                                            <div>
                                                <span class="text-sm font-medium <?= $is_active ? 'text-gray-800' : 'text-gray-400'; ?>">
                                                    <?= htmlspecialchars($doc['libelle']); ?>
                                                </span>
                                                <span class="text-[10px] text-gray-400 ml-2">DOC-<?= $doc['id_type']; ?></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a href="?toggle_doc=<?= $doc['id_type']; ?>"
                                               class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors duration-200 <?= $is_active ? 'bg-[#006e0c]' : 'bg-gray-200'; ?>">
                                                <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform duration-200 <?= $is_active ? 'translate-x-[18px]' : 'translate-x-[3px]'; ?>"></span>
                                            </a>
                                            <span class="text-[11px] font-medium <?= $is_active ? 'text-green-600' : 'text-gray-400'; ?> w-14">
                                                <?= $is_active ? 'Actif' : 'Inactif'; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-10 text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                    <path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <p class="text-sm font-medium">Aucun type de document</p>
                                <p class="text-xs mt-1">Cliquez sur "Ajouter" pour créer le premier.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pied de carte info -->
                    <div class="px-7 py-4 bg-gray-50/80 border-t border-gray-100">
                        <div class="flex items-center gap-2 text-[11px] text-gray-500">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Les types désactivés n'apparaîtront plus dans les formulaires de demande.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- MODAL : AJOUTER UN TYPE DE DOCUMENT -->
<div class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 modal-backdrop" id="docModal">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden animate-in fade-in zoom-in duration-150">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-[#003d7a]/10 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-[#003d7a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path d="M12 4.5v15m7.5-7.5h-15" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Nouveau type de document</h3>
            </div>
            <button onclick="closeDocModal()" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-1.5 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
        <form method="POST" action="" class="p-6 space-y-5">
            <input type="hidden" name="action_add_doc" value="1">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Libellé du document</label>
                <input type="text" name="libelle" placeholder="Ex: Certificat de scolarité"
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-[#003d7a] focus:ring-1 focus:ring-[#003d7a] transition-all" required autofocus>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeDocModal()"
                    class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-medium hover:bg-gray-50 transition-all">
                    Annuler
                </button>
                <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-[#003d7a] text-white rounded-xl text-sm font-semibold hover:bg-[#002d5a] transition-all shadow-sm">
                    Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDocModal() {
        document.getElementById('docModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeDocModal() {
        document.getElementById('docModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-backdrop')) {
            event.target.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
</script>
</body>
</html>
