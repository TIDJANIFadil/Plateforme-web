<?php
session_start();

// 1. Sécurité : Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

// 2. Connexion à la base de données via db.php
require_once __DIR__ . '/../ifri_gestion_docs.php';

// Compteur notifications admin
$admin_notif_count = 0;
try {
    $admin_notif_count = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE lue = 0")->fetchColumn();
} catch (PDOException $e) {}

// --- TRAITEMENT DU FORMULAIRE DE MODIFICATION ---
$msg_success = "";
$msg_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Action : Modifier les informations
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $new_prenom = trim($_POST['prenom'] ?? '');
        $new_nom = trim($_POST['nom'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $new_role = trim($_POST['role'] ?? '');

        // Mise à jour en base de données
        if (!empty($new_nom) && !empty($new_prenom) && !empty($new_email) && isset($_SESSION['admin_email'])) {
            try {
                $stmt = $pdo->prepare("UPDATE administrateurs SET nom = ?, prenom = ?, email = ?, role = ? WHERE email = ?");
                $stmt->execute([strtoupper($new_nom), $new_prenom, $new_email, $new_role, $_SESSION['admin_email']]);

                $_SESSION['admin_email'] = $new_email;
            } catch (PDOException $e) {
                $msg_error = "Erreur lors de la mise à jour.";
            }
        }

        $_SESSION['admin_nom'] = $new_prenom . ' ' . strtoupper($new_nom);
        $_SESSION['admin_role'] = $new_role;

        // Gestion de l'upload de photo
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newFileName = 'admin_' . time() . '.' . $fileExtension;
                $dest_path = '../uploads/' . $newFileName;

                if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                    // Supprimer l'ancienne si elle existe
                    if (isset($_SESSION['admin_avatar']) && file_exists($_SESSION['admin_avatar'])) {
                        @unlink($_SESSION['admin_avatar']);
                    }
                    $_SESSION['admin_avatar'] = $dest_path;
                }
            } else {
                $msg_error = "Format d'image non valide (JPG, PNG, GIF acceptés).";
            }
        }
        $msg_success = "Profil mis à jour avec succès !";
    }

    // Action : Supprimer la photo
    if (isset($_POST['action']) && $_POST['action'] === 'delete_avatar') {
        if (isset($_SESSION['admin_avatar']) && file_exists($_SESSION['admin_avatar'])) {
            @unlink($_SESSION['admin_avatar']);
        }
        unset($_SESSION['admin_avatar']);
        $msg_success = "Photo de profil supprimée.";
    }
}

// 3. Récupération des infos de l'administrateur depuis la BDD
// On se base sur l'ID ou l'email stocké en session lors de la connexion (ex: $_SESSION['admin_email'])
$admin_nom = 'Admin'; 
$admin_email = 'admin@ifri.bj';
$admin_role = 'Administrateur Principal';

if (isset($_SESSION['admin_email'])) {
    try {
        $stmt_admin = $pdo->prepare("SELECT nom, prenom, email FROM administrateurs WHERE email = ?");
        $stmt_admin->execute([$_SESSION['admin_email']]);
        $current_admin = $stmt_admin->fetch();
        
        if ($current_admin) {
            // Associe le prénom et le nom de la base de données
            $admin_nom = htmlspecialchars($current_admin['prenom'] . ' ' . $current_admin['nom']);
            $admin_email = htmlspecialchars($current_admin['email']);
        }
    } catch (PDOException $e) {
        // En cas de problème, on garde les valeurs par défaut
    }
} elseif (isset($_SESSION['admin_nom'])) {
    // Si tu stockes déjà le nom directement en session lors du login
    $admin_nom = htmlspecialchars($_SESSION['admin_nom']);
    $admin_email = isset($_SESSION['admin_email']) ? htmlspecialchars($_SESSION['admin_email']) : $admin_email;
}

$admin_avatar = isset($_SESSION['admin_avatar']) ? $_SESSION['admin_avatar'] : null;

try {
    // 4. Statistiques réelles pour l'Aperçu Hebdomadaire
    $traitees = $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut_demande = 'Prêt' OR statut_demande = 'Terminée'")->fetchColumn();
    $en_attente = $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut_demande = 'En attente'")->fetchColumn();
    $urgences = $pdo->query("SELECT COUNT(*) FROM demandes WHERE (statut_demande = 'En attente' OR statut_demande = 'En cours') AND date_demande < DATE_SUB(NOW(), INTERVAL 3 DAY)")->fetchColumn();

    // 5. Journal d'activité dynamique 
    $query_journal = "SELECT d.id_demande, d.statut_demande, d.date_demande, 
                             e.nom AS etudiant_nom, e.prenom AS etudiant_prenom, 
                             t.libelle AS nom_type 
                      FROM demandes d
                      LEFT JOIN etudiants e ON d.id_etudiant = e.id_etudiant
                      LEFT JOIN types_documents t ON d.id_type_doc = t.id_type
                      ORDER BY d.date_demande DESC LIMIT 4";
    $activities = $pdo->query($query_journal)->fetchAll();
} catch (PDOException $e) {
    $error_sql = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Mon Profil</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght=400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<style data-purpose="global-styles">
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            color: #1e293b;
        }
        .active-nav {
            background-color: #93F08D;
            color: #065f46;
        }
        .ifri-blue {
            color: #003d7a;
        }
        .ifri-bg-blue {
            background-color: #003d7a;
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
<body class="flex min-h-screen">
    
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full z-20">
        <div class="p-6">
            <div class="h-12 w-12 bg-primary text-white font-extrabold flex items-center justify-center rounded-xl mb-xs text-xl">
                <img src="../images/IFRI.png" alt="Logo IFRI" />
            </div>
            <h1 class="text-xl font-bold ifri-blue">IFRI Portail</h1>
            <p class="text-xs text-gray-500">Gestion des documents</p>
        </div>
        <nav class="flex-1 px-4 mt-4 space-y-2">
            <a class="flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors" href="dashboard_admin.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                <span class="font-medium">Tableau de bord</span>
            </a>
            <a class="flex items-center px-4 py-3 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors" href="mes_demandes_admin.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                <span class="font-medium">Demandes</span>
            </a>
            <a class="flex items-center px-4 py-3 rounded-lg active-nav transition-colors" href="profile_admin.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                <span class="font-medium">Profile</span>
            </a>
        </nav>
        <div class="space-y-4 px-4 mb-6">
            <a class="flex items-center px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl font-medium transition-colors" href="settings_admin.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                Paramètres
            </a>
            <a class="flex items-center text-red-500 hover:text-red-700 transition-colors" href="../index.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                <span class="font-medium text-sm">Déconnecter</span>
            </a>
        </div>
    </aside>

    <div class="flex-1 ml-64 flex flex-col">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10">
            <div class="relative w-96">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                </span>
                <input class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-full bg-gray-50 text-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Rechercher une demande..." type="text"/>
            </div>
            <div class="flex items-center space-x-6">
            
            <a href="notifications_admin.php" class="relative inline-flex items-center justify-center w-8 h-8 rounded-full <?= $admin_notif_count > 0 ? 'bg-amber-100' : 'hover:bg-gray-100'; ?> transition-all">
                <span class="material-symbols-outlined <?= $admin_notif_count > 0 ? 'text-amber-600' : 'text-gray-500'; ?>" style="font-size:18px;">notifications</span>
                <?php if ($admin_notif_count > 0): ?>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow-lg"><?= min($admin_notif_count, 99); ?></span>
                <?php endif; ?>
            </a>
                
            <a href="faq_admin.php" class="text-on-surface-variant hover:bg-surface-container transition-colors p-base rounded-full inline-flex items-center justify-center">
                <span class="material-symbols-outlined" data-icon="help">help</span>
            </a> 

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

        <main class="p-8 space-y-6 max-w-7xl mx-auto w-full">
            
            <?php if (!empty($msg_success)): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">
                    <?= $msg_success; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($msg_error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                    <?= $msg_error; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-3 gap-6">
                <div class="col-span-2 bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <div class="flex items-start justify-between mb-8">
                        <div class="flex items-center">
                            <div class="relative">
                                <?php if ($admin_avatar): ?>
                                    <img src="<?= $admin_avatar; ?>" class="w-24 h-24 rounded-2xl object-cover border-2 border-white shadow-lg shrink-0" alt="Avatar Admin"/>
                                <?php else: ?>
                                    <div class="w-24 h-24 rounded-2xl bg-blue-50 text-[#003d7a] flex items-center justify-center font-bold text-3xl border-2 border-white shadow-lg shrink-0">
                                        <?= strtoupper(substr($admin_nom, 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-green-500 border-4 border-white rounded-full flex items-center justify-center">
                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewbox="0 0 20 20"><path clip-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" fill-rule="evenodd"></path></svg>
                                </div>
                            </div>
                            <div class="ml-6">
                                <h2 class="text-3xl font-bold text-gray-900"><?= $admin_nom; ?></h2>
                                <span class="mt-1 inline-block px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full uppercase tracking-wide">
                                    <?= $admin_role; ?>
                                </span>
                            </div>
                        </div>
                        
                        <button onclick="openModal()" class="flex items-center px-6 py-3 ifri-bg-blue text-white rounded-xl font-medium hover:bg-blue-800 transition-shadow shadow hover:shadow-md">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                            Modifier le Profil
                        </button>
                    </div>
                    <hr class="border-gray-100 my-8"/>
                    <div class="grid grid-cols-2 gap-y-8 gap-x-12">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Email Professionnel</p>
                            <p class="text-gray-800 font-medium"><?= $admin_email; ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Téléphone</p>
                            <p class="text-gray-800 font-medium">+229 01 40 40 50 50</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Département</p>
                            <p class="text-gray-800 font-medium">Services Administratifs</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Institution</p>
                            <p class="text-gray-800 font-medium">IFRI - UAC</p>
                        </div>
                    </div>
                </div>

                <div class="col-span-1 bg-white rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden">
                    <!-- Barre d'accent élégante -->
                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-[#003d7a] via-[#0055a0] to-[#006e0c]"></div>

                    <div class="p-7">
                        <div class="flex items-center mb-7">
                            <div class="w-8 h-8 bg-[#003d7a]/5 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 text-[#003d7a]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900 tracking-tight">Aperçu Hebdomadaire</h3>
                                <p class="text-[11px] text-gray-400 tracking-wide">Semaine en cours</p>
                            </div>
                        </div>

                        <div class="space-y-0.5">
                            <!-- Traitées -->
                            <div class="flex items-center justify-between py-3.5 px-4 rounded-xl hover:bg-gray-50/80 transition-all duration-200 -mx-4">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 rounded-full bg-[#006e0c] mr-3.5 flex-shrink-0 shadow-sm shadow-[#006e0c]/30"></span>
                                    <div>
                                        <span class="text-sm font-semibold text-gray-800">Traitées</span>
                                        <p class="text-[11px] text-gray-400 leading-none mt-1">Documents finalisés</p>
                                    </div>
                                </div>
                                <span class="text-xl font-bold text-[#006e0c] tracking-tight" style="font-variant-numeric: tabular-nums;"><?= sprintf("%02d", $traitees); ?></span>
                            </div>

                            <!-- En attente -->
                            <div class="flex items-center justify-between py-3.5 px-4 rounded-xl hover:bg-gray-50/80 transition-all duration-200 -mx-4">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 rounded-full bg-[#003d7a] mr-3.5 flex-shrink-0 shadow-sm shadow-[#003d7a]/30"></span>
                                    <div>
                                        <span class="text-sm font-semibold text-gray-800">En attente</span>
                                        <p class="text-[11px] text-gray-400 leading-none mt-1">Traitement en cours</p>
                                    </div>
                                </div>
                                <span class="text-xl font-bold text-[#003d7a] tracking-tight" style="font-variant-numeric: tabular-nums;"><?= sprintf("%02d", $en_attente); ?></span>
                            </div>

                            <!-- Urgences -->
                            <div class="flex items-center justify-between py-3.5 px-4 rounded-xl hover:bg-gray-50/80 transition-all duration-200 -mx-4">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 rounded-full bg-red-500 mr-3.5 flex-shrink-0 shadow-sm shadow-red-500/30"></span>
                                    <div>
                                        <span class="text-sm font-semibold text-gray-800">Urgences</span>
                                        <p class="text-[11px] text-gray-400 leading-none mt-1">En retard (+3 jours)</p>
                                    </div>
                                </div>
                                <span class="text-xl font-bold text-red-500 tracking-tight" style="font-variant-numeric: tabular-nums;"><?= sprintf("%02d", $urgences); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                <h3 class="text-2xl font-bold text-gray-900 mb-8">Journal d'Activité</h3>
                <div class="space-y-8">
                    <?php if (empty($activities)): ?>
                        <p class="text-sm text-gray-400 italic text-center py-6">Aucune activité récente enregistrée.</p>
                    <?php else: ?>
                        <?php foreach ($activities as $act): ?>
                        <?php 
                            $statut = $act['statut_demande'];
                            $etudiant_fullname = htmlspecialchars(($act['etudiant_prenom'] ?? '') . ' ' . ($act['etudiant_nom'] ?? 'Étudiant inconnu'));
                            $doc_type = htmlspecialchars($act['nom_type'] ?? 'Document indéfini');

                            if ($statut === 'En attente') {
                                $bg_icon = "bg-orange-100 text-orange-600";
                                $icon_svg = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>';
                                $action_text = "Nouvelle demande reçue";
                            } elseif ($statut === 'Prêt' || $statut === 'Terminée') {
                                $bg_icon = "bg-green-100 text-green-600";
                                $icon_svg = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>';
                                $action_text = "Demande approuvée";
                            } elseif ($statut === 'Rejeté') {
                                $bg_icon = "bg-red-100 text-red-600";
                                $icon_svg = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>';
                                $action_text = "Demande rejetée";
                            } else {
                                $bg_icon = "bg-blue-100 text-blue-600";
                                $icon_svg = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>';
                                $action_text = "Demande en traitement";
                            }
                        ?>
                        <div class="flex items-start">
                            <div class="w-12 h-12 <?= $bg_icon; ?> rounded-full flex items-center justify-center mr-6 flex-shrink-0">
                                <?= $icon_svg; ?>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800"><?= $action_text; ?> #<?= $act['id_demande']; ?></h4>
                                <p class="text-sm text-gray-500"><?= $doc_type; ?> — <?= $etudiant_fullname; ?></p>
                                <span class="text-xs text-gray-400 mt-1 block">Date : <?= date('d/m/Y à H:i', strtotime($act['date_demande'])); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <div id="profileModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl mx-4 relative">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">Modifier le profil</h3>
            
            <form action="profile_admin.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="action" value="update_profile">

                <?php
                $prenom_actuel = '';
                $nom_actuel = '';
                if (isset($current_admin) && !empty($current_admin['prenom'])) {
                    $prenom_actuel = htmlspecialchars($current_admin['prenom']);
                    $nom_actuel = htmlspecialchars($current_admin['nom']);
                } else {
                    // Fallback: extraire depuis $admin_nom qui est "Prénom Nom"
                    $parts = explode(' ', $admin_nom, 2);
                    $prenom_actuel = $parts[0];
                    $nom_actuel = isset($parts[1]) ? $parts[1] : '';
                }
                ?>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Prénom</label>
                        <input type="text" name="prenom" value="<?= $prenom_actuel; ?>" required class="w-full rounded-xl border-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Nom</label>
                        <input type="text" name="nom" value="<?= $nom_actuel; ?>" required class="w-full rounded-xl border-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="<?= $admin_email; ?>" required class="w-full rounded-xl border-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Rôle / Fonction</label>
                    <input type="text" name="role" value="<?= $admin_role; ?>" required class="w-full rounded-xl border-gray-200 text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Photo de profil</label>
                    <input type="file" name="avatar" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-100">
                    <button type="button" onclick="closeModal()" class="px-5 py-2.5 text-sm font-medium text-gray-500 hover:bg-gray-100 rounded-xl transition-colors">Annuler</button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-medium ifri-bg-blue text-white rounded-xl hover:bg-blue-800 transition-colors">Enregistrer</button>
                </div>
            </form>

            <?php if ($admin_avatar): ?>
                <form action="profile_admin.php" method="POST" class="absolute bottom-8 left-8">
                    <input type="hidden" name="action" value="delete_avatar">
                    <button type="submit" class="text-xs font-bold text-red-500 hover:text-red-700 transition-colors flex items-center">
                        <span class="material-symbols-outlined text-sm mr-1">delete</span> Supprimer la photo
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('profileModal').classList.remove('hidden');
        }
        function closeModal() {
            document.getElementById('profileModal').classList.add('hidden');
        }
    </script>
<script src="../assets/js/app.js"></script>
</body>
</html>