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

// 3. Marquage comme lu (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $id = intval($_POST['mark_read']);
    $stmt = $pdo->prepare("UPDATE admin_notifications SET lue = 1 WHERE id_notification = ?");
    $stmt->execute([$id]);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// 4. Tout marquer comme lu
if (isset($_GET['mark_all_read'])) {
    $pdo->exec("UPDATE admin_notifications SET lue = 1 WHERE lue = 0");
    header('Location: notifications_admin.php');
    exit;
}

// 5. Informations admin pour le header
$admin_avatar = isset($_SESSION['admin_avatar']) ? $_SESSION['admin_avatar'] : null;
$admin_nom_header = 'Administrateur';
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

// 6. Filtres et pagination
$filter = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 7. Construction de la requête avec filtre
$where = '';
$count_where = '';
switch ($filter) {
    case 'nouvelle_demande':
        $where = "WHERE n.type_notif = 'nouvelle_demande'";
        break;
    case 'connexion':
        $where = "WHERE n.type_notif = 'connexion'";
        break;
    case 'statut':
        $where = "WHERE n.type_notif = 'statut'";
        break;
    case 'systeme':
        $where = "WHERE n.type_notif = 'systeme'";
        break;
    default:
        $where = '';
}

$count_where = $where;

// 8. Compteurs
try {
    $total_all = $pdo->query("SELECT COUNT(*) FROM admin_notifications")->fetchColumn();
    $total_nouvelles = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE type_notif = 'nouvelle_demande'")->fetchColumn();
    $total_connexions = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE type_notif = 'connexion'")->fetchColumn();
    $total_statuts = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE type_notif = 'statut'")->fetchColumn();
    $non_lues = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE lue = 0 $count_where")->fetchColumn();
    $total_non_lues_global = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE lue = 0")->fetchColumn();
} catch (PDOException $e) {
    $total_all = $total_nouvelles = $total_connexions = $total_statuts = $non_lues = $total_non_lues_global = 0;
}

// 9. Récupération des notifications
$notifications = [];
$total_pages = 1;
try {
    $count_result = $pdo->query("SELECT COUNT(*) FROM admin_notifications n $count_where")->fetchColumn();
    $total_pages = max(1, ceil($count_result / $per_page));

    $query = "SELECT n.*, e.prenom as etu_prenom, e.nom as etu_nom, e.matricule as etu_matricule
              FROM admin_notifications n
              LEFT JOIN etudiants e ON n.id_etudiant = e.id_etudiant
              $where
              ORDER BY n.cree_at DESC
              LIMIT $per_page OFFSET $offset";
    $notifications = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}
?>
<!DOCTYPE html>
<html class="light" lang="fr">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>IFRI Admin - Centre de Notifications</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            color: #1e293b;
        }

        .glass-card {
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.05);
        }
        .glass-card-strong {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(226,232,240,0.6);
            border-radius: 24px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.06);
        }
        .glass-sidebar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(226,232,240,0.8);
        }

        .stat-card {
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.08);
        }

        .notif-item {
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .notif-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }
        .notif-item:hover::before {
            transform: translateX(100%);
        }
        .notif-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }
        .notif-item.unread {
            border-left: 4px solid #004A99;
            background: rgba(0,74,153,0.02);
        }
        .notif-item.read {
            opacity: 0.75;
        }
        .notif-item.read:hover {
            opacity: 1;
        }

        .badge-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            animation: dotPulse 2s ease-in-out infinite;
        }
        @keyframes dotPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        .type-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            transition: all 0.3s;
        }
        .notif-item:hover .type-icon {
            transform: scale(1.05);
        }

        .fade-in {
            opacity: 0;
            animation: fadeIn 0.5s ease-out forwards;
        }
        @keyframes fadeIn {
            to { opacity: 1; }
        }
        .fade-in-d1 { animation-delay: 0.05s; }
        .fade-in-d2 { animation-delay: 0.1s; }
        .fade-in-d3 { animation-delay: 0.15s; }
        .fade-in-d4 { animation-delay: 0.2s; }
        .fade-in-d5 { animation-delay: 0.25s; }
        .fade-in-d6 { animation-delay: 0.3s; }

        .slide-up {
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.5s cubic-bezier(0.16,1,0.3,1) forwards;
        }
        @keyframes slideUp {
            to { opacity: 1; transform: translateY(0); }
        }

        .time-text {
            font-size: 12px;
            color: #94a3b8;
            white-space: nowrap;
        }

        .empty-state-icon {
            font-size: 80px;
            opacity: 0.3;
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .active-tab {
            background: #004A99 !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(0,74,153,0.25);
        }

        .count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 7px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .toast-msg {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999;
            background: #1e293b;
            color: white;
            padding: 14px 24px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toast-msg.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Scrollbar */
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .ifri-blue { color: #003d7a; }
    </style>
</head>
<body class="flex min-h-screen overflow-hidden">

    <!-- ===== TOAST ===== -->
    <div id="toast" class="toast-msg">
        <span class="material-symbols-outlined" style="font-size:20px;">check_circle</span>
        <span id="toastText">Notification marquée comme lue</span>
    </div>

<!-- BARRE LATÉRALE -->
<aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full z-20">
    <div class="p-6">
        <div class="h-12 w-12 bg-primary text-white font-extrabold flex items-center justify-center rounded-xl mb-1">
            <img src="../images/IFRI.png" alt="Logo IFRI">
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

    <!-- ===== MAIN CONTENT ===== -->
    <div class="ml-64 flex-grow flex flex-col min-h-screen overflow-y-auto custom-scroll">

        <!-- ===== HEADER ===== -->
        <header class="bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-20 shrink-0">
            <div class="flex items-center justify-between px-6 md:px-8 h-14">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" style="font-size:18px;">search</span>
                        <input type="text" id="searchInput" placeholder="Rechercher dans les notifications..."
                            class="w-64 md:w-80 pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-[#004A99]/20 focus:border-[#004A99] transition-all placeholder:text-slate-400" />
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="h-8 w-8 rounded-full bg-amber-100 flex items-center justify-center relative">
                        <span class="material-symbols-outlined text-amber-600" style="font-size:18px;">notifications</span>
                        <?php if ($total_non_lues_global > 0): ?>
                            <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow-lg"><?= min($total_non_lues_global, 99); ?></span>
                        <?php endif; ?>
                    </div>
                    <button class="text-gray-500 hover:bg-gray-100 p-2 rounded-full transition-colors">
                        <span class="material-symbols-outlined">help</span>
                    </button>
                    <div class="flex items-center gap-2.5 pl-3 border-l border-slate-200">
                        <span class="text-sm font-semibold text-slate-700 hidden sm:block"><?= htmlspecialchars($admin_nom_header); ?></span>
                        <?php if ($admin_avatar): ?>
                            <img src="<?= htmlspecialchars($admin_avatar); ?>" class="w-9 h-9 rounded-full object-cover border-2 border-slate-200 shadow-sm" alt="Avatar" />
                        <?php else: ?>
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#004A99] to-[#00387a] text-white flex items-center justify-center font-bold text-xs shadow-md border-2 border-white">ADM</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 md:px-8 py-6 max-w-6xl mx-auto w-full">

            <!-- ===== TITLE ===== -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 fade-in fade-in-d1">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Notifications</h1>
                        <?php if ($total_non_lues_global > 0): ?>
                            <span class="count-badge bg-[#004A99] text-white text-sm px-3 py-1 font-bold"><?= $total_non_lues_global; ?> non lue<?= $total_non_lues_global > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-slate-500 mt-1">Centre de notification administrateur — suivez l'activité en temps réel</p>
                </div>
                <?php if ($total_non_lues_global > 0): ?>
                    <a href="?mark_all_read=1&type=<?= urlencode($filter); ?>"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#004A99] text-white text-sm font-bold rounded-xl hover:bg-[#00387a] transition-all shadow-md shadow-[#004A99]/20 active:scale-95 shrink-0">
                        <span class="material-symbols-outlined" style="font-size:18px;">done_all</span>
                        Tout marquer comme lu
                    </a>
                <?php endif; ?>
            </div>

            <!-- ===== STATS CARDS ===== -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6 fade-in fade-in-d2">
                <div class="glass-card p-4 stat-card" onclick="location.href='?type=all'">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total</span>
                        <span class="material-symbols-outlined text-slate-300" style="font-size:18px;">notifications</span>
                    </div>
                    <p class="text-2xl font-extrabold text-slate-900"><?= number_format($total_all, 0, ',', ' '); ?></p>
                    <p class="text-[10px] text-slate-400 mt-0.5">toutes les notifications</p>
                </div>
                <div class="glass-card p-4 stat-card" onclick="location.href='?type=nouvelle_demande'">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Demandes</span>
                        <span class="material-symbols-outlined text-blue-400" style="font-size:18px;">note_add</span>
                    </div>
                    <p class="text-2xl font-extrabold text-slate-900"><?= number_format($total_nouvelles, 0, ',', ' '); ?></p>
                    <p class="text-[10px] text-slate-400 mt-0.5">nouvelles soumissions</p>
                </div>
                <div class="glass-card p-4 stat-card" onclick="location.href='?type=connexion'">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Connexions</span>
                        <span class="material-symbols-outlined text-emerald-400" style="font-size:18px;">login</span>
                    </div>
                    <p class="text-2xl font-extrabold text-slate-900"><?= number_format($total_connexions, 0, ',', ' '); ?></p>
                    <p class="text-[10px] text-slate-400 mt-0.5">étudiants connectés</p>
                </div>
                <div class="glass-card p-4 stat-card" onclick="location.href='?type=statut'">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Statuts</span>
                        <span class="material-symbols-outlined text-amber-400" style="font-size:18px;">swap_horiz</span>
                    </div>
                    <p class="text-2xl font-extrabold text-slate-900"><?= number_format($total_statuts, 0, ',', ' '); ?></p>
                    <p class="text-[10px] text-slate-400 mt-0.5">mises à jour effectuées</p>
                </div>
            </div>

            <!-- ===== FILTER TABS ===== -->
            <div class="flex flex-wrap gap-2 mb-5 fade-in fade-in-d3">
                <a href="?type=all"
                    class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?= $filter === 'all' ? 'active-tab' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'; ?>">
                    Toutes
                    <?php if ($total_all > 0): ?>
                        <span class="ml-1.5 count-badge <?= $filter === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'; ?>"><?= $total_all; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?type=nouvelle_demande"
                    class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?= $filter === 'nouvelle_demande' ? 'active-tab' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'; ?>">
                    <span class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined" style="font-size:16px;">note_add</span>
                        Nouvelles demandes
                    </span>
                </a>
                <a href="?type=connexion"
                    class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?= $filter === 'connexion' ? 'active-tab' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'; ?>">
                    <span class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined" style="font-size:16px;">login</span>
                        Connexions
                    </span>
                </a>
                <a href="?type=statut"
                    class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?= $filter === 'statut' ? 'active-tab' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'; ?>">
                    <span class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined" style="font-size:16px;">swap_horiz</span>
                        Mises à jour
                    </span>
                </a>
                <a href="?type=systeme"
                    class="px-4 py-2 rounded-xl text-sm font-semibold transition-all <?= $filter === 'systeme' ? 'active-tab' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-slate-300'; ?>">
                    <span class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined" style="font-size:16px;">settings</span>
                        Système
                    </span>
                </a>
            </div>

            <!-- ===== NOTIFICATIONS LIST ===== -->
            <div class="space-y-2" id="notifList">
                <?php if (empty($notifications)): ?>
                    <!-- ===== EMPTY STATE ===== -->
                    <div class="glass-card-strong p-10 md:p-16 text-center fade-in">
                        <div class="empty-state-icon mb-4">
                            <?php if ($filter === 'nouvelle_demande'): ?>
                                <span class="material-symbols-outlined !text-6xl text-slate-200">note_add</span>
                            <?php elseif ($filter === 'connexion'): ?>
                                <span class="material-symbols-outlined !text-6xl text-slate-200">login</span>
                            <?php elseif ($filter === 'statut'): ?>
                                <span class="material-symbols-outlined !text-6xl text-slate-200">swap_horiz</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined !text-6xl text-slate-200">notifications_off</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-bold text-slate-700 mb-1">
                            <?php if ($filter === 'all' && $total_all == 0): ?>
                                Aucune notification pour le moment
                            <?php else: ?>
                                Aucune notification trouvée
                            <?php endif; ?>
                        </h3>
                        <p class="text-sm text-slate-400 mb-6 max-w-md mx-auto">
                            <?php if ($filter === 'all' && $total_all == 0): ?>
                                Les notifications apparaîtront ici lorsqu'un étudiant soumettra une demande, se connectera ou lorsqu'une action administrative sera effectuée.
                            <?php elseif ($filter === 'nouvelle_demande'): ?>
                                Aucune nouvelle demande soumise pour le moment. Les étudiants n'ont pas encore envoyé de requête.
                            <?php elseif ($filter === 'connexion'): ?>
                                Aucune connexion récente. Les notifications de connexion apparaîtront ici.
                            <?php elseif ($filter === 'statut'): ?>
                                Aucune mise à jour de statut récente.
                            <?php else: ?>
                                Aucune notification système pour le moment.
                            <?php endif; ?>
                        </p>
                        <?php if ($filter !== 'all'): ?>
                            <a href="?type=all" class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-[#004A99] text-white text-sm font-bold rounded-xl hover:bg-[#00387a] transition-all shadow-md">
                                <span class="material-symbols-outlined" style="font-size:16px;">notifications</span>
                                Voir toutes les notifications
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $i => $n):
                        // Déterminer l'icône et la couleur selon le type
                        $type = $n['type_notif'];
                        switch ($type) {
                            case 'nouvelle_demande':
                                $icon = 'note_add';
                                $bg_color = 'bg-blue-50';
                                $text_color = 'text-blue-600';
                                $border_color = 'border-l-blue-500';
                                break;
                            case 'connexion':
                                $icon = 'login';
                                $bg_color = 'bg-emerald-50';
                                $text_color = 'text-emerald-600';
                                $border_color = 'border-l-emerald-500';
                                break;
                            case 'statut':
                                $icon = 'swap_horiz';
                                $bg_color = 'bg-amber-50';
                                $text_color = 'text-amber-600';
                                $border_color = 'border-l-amber-500';
                                break;
                            default:
                                $icon = 'info';
                                $bg_color = 'bg-purple-50';
                                $text_color = 'text-purple-600';
                                $border_color = 'border-l-purple-500';
                        }

                        $est_lue = (bool)$n['lue'];
                        $prenom = $n['etu_prenom'] ?? '';
                        $nom = $n['etu_nom'] ?? '';
                        $initiale = mb_strtoupper(mb_substr($prenom, 0, 1, 'UTF-8'));
                        $initiale .= mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'));
                        if (empty(trim($initiale))) $initiale = '?';

                        // Temps relatif
                        $timestamp = strtotime($n['cree_at']);
                        $diff = time() - $timestamp;
                        if ($diff < 60) {
                            $time_str = "À l'instant";
                        } elseif ($diff < 3600) {
                            $mins = floor($diff / 60);
                            $time_str = "Il y a $mins min";
                        } elseif ($diff < 86400) {
                            $hours = floor($diff / 3600);
                            $time_str = "Il y a $hours h";
                        } elseif ($diff < 604800) {
                            $days = floor($diff / 86400);
                            $time_str = "Il y a $days jour" . ($days > 1 ? 's' : '');
                        } else {
                            $time_str = date('d/m/Y', $timestamp);
                        }
                    ?>
                        <div class="notif-item glass-card p-4 <?= $est_lue ? 'read' : 'unread'; ?> <?= $border_color; ?> slide-up" style="animation-delay: <?= min($i * 0.05, 0.5); ?>s" data-id="<?= $n['id_notification']; ?>" onclick="marquerLu(<?= $n['id_notification']; ?>, this)">
                            <div class="flex items-start gap-3.5">
                                <!-- Icône type -->
                                <div class="type-icon <?= $bg_color; ?> <?= $text_color; ?>">
                                    <span class="material-symbols-outlined"><?= $icon; ?></span>
                                </div>

                                <!-- Contenu -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-0.5">
                                                <h4 class="text-sm font-bold text-slate-900 truncate <?= !$est_lue ? '' : ''; ?>">
                                                    <?= htmlspecialchars($n['titre']); ?>
                                                </h4>
                                                <?php if (!$est_lue): ?>
                                                    <span class="badge-dot bg-[#004A99] shrink-0"></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-sm text-slate-500 leading-relaxed">
                                                <?= htmlspecialchars($n['message']); ?>
                                            </p>
                                        </div>
                                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                                            <span class="time-text"><?= $time_str; ?></span>
                                            <?php if (!empty($prenom) || !empty($nom)): ?>
                                                <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-[10px] font-bold" title="<?= htmlspecialchars($prenom . ' ' . $nom); ?>">
                                                    <?= htmlspecialchars($initiale); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($prenom)): ?>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <span class="text-[11px] font-medium text-slate-400">
                                                <?= htmlspecialchars($prenom . ' ' . $nom); ?>
                                                <?php if (!empty($n['etu_matricule'])): ?>
                                                    · <?= htmlspecialchars($n['etu_matricule']); ?>
                                                <?php endif; ?>
                                            </span>
                                            <?php if (!empty($n['id_demande'])): ?>
                                                <a href="traiter_demande.php?id=<?= $n['id_demande']; ?>" class="text-[11px] font-bold text-[#004A99] hover:underline" onclick="event.stopPropagation();">
                                                    Voir la demande →
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ===== PAGINATION ===== -->
            <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-center gap-2 mt-8 fade-in">
                    <?php if ($page > 1): ?>
                        <a href="?type=<?= urlencode($filter); ?>&page=<?= $page - 1; ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                            ← Précédent
                        </a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="?type=<?= urlencode($filter); ?>&page=<?= $p; ?>"
                            class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-semibold transition-all <?= $p === $page ? 'bg-[#004A99] text-white shadow-md' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'; ?>">
                            <?= $p; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?type=<?= urlencode($filter); ?>&page=<?= $page + 1; ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                            Suivant →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- ===== FOOTER ===== -->
            <div class="mt-10 pt-6 border-t border-slate-200 text-center">
                <p class="text-xs text-slate-400">
                    Centre de notifications administrateur · IFRI Portail ·
                    <a href="?mark_all_read=1" class="text-[#004A99] hover:underline font-medium">Tout marquer comme lu</a>
                </p>
            </div>
        </main>
    </div>

    <script>
        // ===== MARQUER COMME LU =====
        function marquerLu(id, element) {
            if (element.classList.contains('read')) return;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'notifications_admin.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    element.classList.remove('unread');
                    element.classList.add('read');

                    // Mise à jour du compteur
                    const badges = document.querySelectorAll('.count-badge');
                    badges.forEach(b => {
                        let count = parseInt(b.textContent);
                        if (!isNaN(count) && count > 0) {
                            b.textContent = count - 1;
                        }
                    });

                    showToast('Notification marquée comme lue');
                }
            };
            xhr.send('mark_read=' + id);
        }

        // ===== TOAST =====
        function showToast(message) {
            const toast = document.getElementById('toast');
            document.getElementById('toastText').textContent = message;
            toast.classList.add('show');
            clearTimeout(window.toastTimeout);
            window.toastTimeout = setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);
        }

        // ===== RECHERCHE EN TEMPS RÉEL =====
        document.getElementById('searchInput')?.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.notif-item');
            let count = 0;
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(query) || query === '') {
                    item.style.display = '';
                    count++;
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>