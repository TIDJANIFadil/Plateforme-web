<?php
session_start();

// 1. Sécurité : Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

// 2. Connexion à la base de données via db.php
require_once __DIR__ . '/../ifri_gestion_docs.php';

// Récupération des messages flash depuis la session
$msg_success = $_SESSION['inscription_success'] ?? null;
$msg_error   = $_SESSION['inscription_error']   ?? null;
unset($_SESSION['inscription_success'], $_SESSION['inscription_error']);

// 3. Récupération des statistiques réelles pour les cartes de score
$total_demandes = $pdo->query("SELECT COUNT(*) FROM demandes")->fetchColumn();
$en_attente = $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut_demande = 'En attente'")->fetchColumn();
$traitees = $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut_demande = 'Prêt' OR statut_demande = 'Terminée'")->fetchColumn();

// Compteur notifications admin
$admin_notif_count = 0;
try {
    $admin_notif_count = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE lue = 0")->fetchColumn();
} catch (PDOException $e) {}

// 4. Infos admin pour le header (avatar + nom)
$admin_avatar = isset($_SESSION['admin_avatar']) ? $_SESSION['admin_avatar'] : null;
$admin_nom_header = 'Secrétariat IFRI';
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

// 5. Récupération de toutes les demandes avec les informations des étudiants correspondants
$query = "SELECT d.*, e.nom, e.prenom, e.email, t.libelle
          FROM demandes d
          JOIN etudiants e ON d.id_etudiant = e.id_etudiant
          JOIN types_documents t ON d.id_type_doc = t.id_type
          ORDER BY d.date_demande DESC";
$demandes = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Espace Administrateur</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style data-purpose="typography">
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
        }
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background-color: rgba(0, 0, 0, 0.4);
        }
    </style>
    <style data-purpose="custom-colors">
        .bg-sidebar { background-color: #F8FAFC; }
        .bg-main-content { background-color: #F8FAFC; }
        .text-ifri-blue { color: #004A99; }
        .bg-ifri-blue { background-color: #004A99; }
        .bg-active-green { background-color: #93F08D; }
        .text-active-dark { color: #1B4D16; }
        .border-card { border-color: #E2E8F0; }
        .status-in-progress { background-color: #FFEDD5; color: #9A3412; }
        .status-completed { background-color: #DCFCE7; color: #166534; }
        .status-waiting { background-color: #F1F5F9; color: #475569; }
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
<body class="text-slate-800 antialiased min-h-screen flex">

<aside class="w-64 bg-sidebar border-r border-slate-200 flex flex-col h-screen sticky top-0" data-purpose="sidebar">
    <div class="p-6">
        <div class="h-12 w-12 bg-primary text-white font-extrabold flex items-center justify-center rounded-xl mb-xs text-xl">
            <img src="../images/IFRI.png" alt="Logo IFRI" />
        </div>
        <h1 class="text-xl font-bold text-ifri-blue leading-tight">IFRI Portail</h1>
        <p class="text-xs text-slate-500">Espace Administrateur</p>
    </div>

    <nav class="flex-1 px-3 space-y-1 mt-4">
        <!-- Bouton Actif : Tableau de bord -->
        <a class="flex items-center px-4 py-3 bg-active-green text-active-dark rounded-xl font-medium transition-colors" data-purpose="nav-item-active" href="dashboard_admin.php">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            Tableau de bord
        </a>
        <!-- Lien vers mes_demandes_admin.php -->
        <a class="flex items-center px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl font-medium transition-colors" href="mes_demandes_admin.php">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            Demandes
        </a>
        <a class="flex items-center px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl font-medium transition-colors" href="profile_admin.php">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            Profile
        </a>
    </nav>

    <div class="px-3 pb-6 space-y-1">
        <button onclick="openInscriptionModal()" class="flex items-center w-full px-4 py-3 text-white bg-ifri-blue hover:bg-blue-700 rounded-xl font-medium transition-colors cursor-pointer">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            Inscrire un étudiant
        </button>
        <a class="flex items-center px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl font-medium transition-colors" href="settings_admin.php">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            Paramètres
        </a>
        <a class="flex items-center px-4 py-3 text-red-500 hover:bg-red-50 rounded-xl font-medium transition-colors" href="../index.php">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" ></path></svg>
            Déconnecter
        </a>
    </div>
</aside>
<main class="flex-1 flex flex-col min-w-0" data-purpose="main-content">
    <header class="h-16 border-b border-slate-200 flex items-center justify-between px-8 bg-white z-10">
        <div class="relative w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            </span>
            <input class="block w-full pl-10 pr-3 py-2 border border-slate-200 rounded-full bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-ifri-blue transition-all" placeholder="Rechercher une demande..." type="text"/>
        </div>
        <div class="flex items-center space-x-6">
            <a href="notifications_admin.php" class="relative inline-flex items-center justify-center w-9 h-9 rounded-full <?= $admin_notif_count > 0 ? 'bg-amber-100 bell-ring' : 'hover:bg-surface-container'; ?> transition-all">
                <span class="material-symbols-outlined <?= $admin_notif_count > 0 ? 'text-amber-600' : 'text-on-surface-variant'; ?>" style="font-size:20px;" data-icon="notifications">notifications</span>
                <?php if ($admin_notif_count > 0): ?>
                    <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center shadow-lg px-1 blink-badge"><?= min($admin_notif_count, 99); ?></span>
                <?php else: ?>
                    <span class="absolute top-1 right-1 h-2 w-2 bg-red-400 rounded-full"></span>
                <?php endif; ?>
            </a>

            <a href="faq_admin.php" class="text-on-surface-variant hover:bg-surface-container transition-colors p-base rounded-full inline-flex items-center justify-center">
                <span class="material-symbols-outlined" data-icon="help">help</span>
            </a>
            <a href="profile_admin.php" class="flex items-center space-x-3">
                <span class="text-sm font-semibold text-slate-700"><?= $admin_nom_header; ?></span>
                <?php if ($admin_avatar): ?>
                    <img src="<?= $admin_avatar; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200" alt="Avatar"/>
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-ifri-blue text-white flex items-center justify-center font-bold text-sm">
                    ADM
                    </div>
                <?php endif; ?>
            </a>
        </div>
    </header>
    <div class="p-8 overflow-y-auto">

        <!-- Messages flash -->
        <?php if ($msg_success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm font-medium">
                <?= htmlspecialchars($msg_success) ?>
            </div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium">
                <?= htmlspecialchars($msg_error) ?>
            </div>
        <?php endif; ?>

        <div class="mb-8">
            <h2 class="text-3xl font-bold text-slate-900">Espace Administrateur</h2>
            <p class="text-slate-500 mt-1">Bienvenue, contrôlez les flux académiques et les accès étudiants de l'IFRI.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            <div class="bg-white border border-card rounded-2xl p-6 relative overflow-hidden">
                <div class="flex justify-between items-start mb-4">
                    <div class="bg-blue-50 p-3 rounded-xl text-ifri-blue">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    </div>
                </div>
                <p class="text-slate-500 font-medium">Total demandes</p>
                <p class="text-4xl font-bold text-slate-900 mt-1"><?= $total_demandes; ?></p>
            </div>
            <div class="bg-white border border-card rounded-2xl p-6 relative overflow-hidden">
                <div class="flex justify-between items-start mb-4">
                    <div class="bg-orange-50 p-3 rounded-xl text-orange-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    </div>
                    <span class="text-xs font-bold text-red-600">Prioritaire</span>
                </div>
                <p class="text-slate-500 font-medium">En attente</p>
                <p class="text-4xl font-bold text-slate-900 mt-1"><?= $en_attente; ?></p>
            </div>
            <div class="bg-white border border-card rounded-2xl p-6 relative overflow-hidden">
                <div class="flex justify-between items-start mb-4">
                    <div class="bg-green-50 p-3 rounded-xl text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    </div>
                </div>
                <p class="text-slate-500 font-medium">Traitées / Prêtes</p>
                <p class="text-4xl font-bold text-slate-900 mt-1"><?= $traitees; ?></p>
            </div>
        </div>
        <section class="mb-12">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-slate-900">Gestion des demandes</h3>
                <button onclick="openInscriptionModal()" class="flex items-center gap-2 px-5 py-2.5 bg-ifri-blue text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    Inscrire un étudiant
                </button>
            </div>
            <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold">
                        <tr>
                            <th class="px-6 py-4">Étudiant</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">Statut</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($demandes)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500 italic bg-slate-50">
                                    Aucune demande d'étudiant n'a été trouvée dans le système.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($demandes as $demande):
                                $status_text = htmlspecialchars($demande['statut_demande']);
                                $status_class = "status-waiting";

                                if ($status_text === 'En traitement' || $status_text === 'En cours') {
                                    $status_class = "status-in-progress";
                                    $status_text = "En cours";
                                } elseif ($status_text === 'Prêt' || $status_text === 'Terminée') {
                                    $status_class = "status-completed";
                                    $status_text = "Terminée";
                                } elseif ($status_text === 'En attente') {
                                    $status_class = "status-waiting";
                                }

                                $init_p = mb_strtoupper(mb_substr($demande['prenom'], 0, 1, 'UTF-8'), 'UTF-8');
                                $init_n = mb_strtoupper(mb_substr($demande['nom'], 0, 1, 'UTF-8'), 'UTF-8');
                                $initials = (!empty($init_p) || !empty($init_n)) ? $init_p . $init_n : "ET";
                            ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-sm mr-3">
                                                <?= $initials; ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-900"><?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']); ?></div>
                                                <div class="text-xs text-slate-500"><?= htmlspecialchars($demande['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-600 font-medium">
                                        <?= htmlspecialchars($demande['libelle'] ?? 'Document #' . $demande['id_type_doc']); ?>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-600">
                                        <?= date('d M Y', strtotime($demande['date_demande'])); ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold <?= $status_class; ?>">
                                            <?= $status_text; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <div class="flex justify-end space-x-2">
                                            <form action="demandes.php" method="POST" class="inline">
                                                <input type="hidden" name="id_demande" value="<?= $demande['id_demande']; ?>">
                                                <input type="hidden" name="action_type" value="traiter">
                                                <button type="submit" title="Mettre en traitement" class="p-2 border border-slate-200 rounded-lg text-slate-400 hover:text-ifri-blue hover:bg-blue-50">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                                </button>
                                            </form>

                                            <a href="traiter_demande.php?id=<?= $demande['id_demande']; ?>" title="Uploader le PDF et marquer Prêt" class="inline-flex p-2 border border-slate-200 rounded-lg text-slate-400 hover:text-ifri-blue hover:bg-blue-50">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-12">
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl p-8 flex justify-between">
                <div>
                    <h3 class="text-xl font-bold text-slate-900 mb-6">Rapport d'activité récent</h3>
                    <ul class="space-y-6">
                        <li class="flex items-start">
                            <div class="w-2 h-2 rounded-full bg-blue-600 mt-2 mr-3 flex-shrink-0"></div>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Système de suivi opérationnel</p>
                                <p class="text-xs text-slate-500">Flux de requêtes en temps réel</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <div class="w-2 h-2 rounded-full bg-green-500 mt-2 mr-3 flex-shrink-0"></div>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Mise à jour automatique des compteurs</p>
                                <p class="text-xs text-slate-500">Synchronisé avec la base de données</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="hidden sm:flex items-center">
                    <div class="bg-slate-50 rounded-xl p-4 w-32 h-32 flex items-end justify-between space-x-1">
                        <div class="w-4 h-12 bg-slate-200 rounded-sm"></div>
                        <div class="w-4 h-20 bg-slate-200 rounded-sm"></div>
                        <div class="w-4 h-16 bg-slate-300 rounded-sm"></div>
                        <div class="w-4 h-24 bg-slate-200 rounded-sm"></div>
                    </div>
                </div>
            </div>
            <div class="bg-ifri-blue rounded-2xl p-8 text-white flex flex-col justify-between relative overflow-hidden">
                <div>
                    <h3 class="text-xl font-bold mb-3">Aide & Support</h3>
                    <p class="text-blue-100 text-sm leading-relaxed">
                        Accédez à la documentation technique du portail admin.
                    </p>
                </div>
                <button class="mt-8 bg-white text-ifri-blue font-bold py-3 px-6 rounded-xl text-sm hover:bg-blue-50 transition-colors w-full sm:w-auto self-start">
                    Ouvrir le guide
                </button>
            </div>
        </div>
    </div>
</main>

<!-- Modale d'inscription d'étudiant -->
<div class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4 modal-backdrop" id="inscriptionModal">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-ifri-blue text-white flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">person_add</span>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Inscrire un étudiant</h3>
                    <p class="text-xs text-gray-500">Un email lui sera envoyé avec ses identifiants</p>
                </div>
            </div>
            <button onclick="closeInscriptionModal()" class="text-gray-500 hover:bg-gray-200 p-2 rounded-full transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
            </button>
        </div>
        <form method="POST" action="process_inscription.php" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Prénom</label>
                    <input type="text" name="prenom" placeholder="Jean" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-ifri-blue focus:border-ifri-blue" required />
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Nom</label>
                    <input type="text" name="nom" placeholder="DUPONT" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-ifri-blue focus:border-ifri-blue" required />
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Numéro Matricule</label>
                <input type="text" name="matricule" placeholder="Ex: 10235023" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-ifri-blue focus:border-ifri-blue" required />
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email Institutionnel</label>
                <input type="email" name="email" placeholder="jean.dupont@ifri.uac.bj" class="w-full p-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-ifri-blue focus:border-ifri-blue" required />
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeInscriptionModal()" class="flex-1 py-3 border border-gray-200 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 text-sm">
                    Annuler
                </button>
                <button type="submit" class="flex-1 py-3 bg-ifri-blue text-white rounded-xl font-semibold hover:bg-blue-700 text-sm flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">mail</span>
                    Inscrire & envoyer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openInscriptionModal() {
        document.getElementById('inscriptionModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeInscriptionModal() {
        document.getElementById('inscriptionModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    window.addEventListener('click', function(event) {
        var modal = document.getElementById('inscriptionModal');
        if (event.target === modal) {
            closeInscriptionModal();
        }
    });
</script>

</body>
</html>
