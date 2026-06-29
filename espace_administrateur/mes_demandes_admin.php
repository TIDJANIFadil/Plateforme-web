<?php
session_start();

// 1. Sécurité : Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

// 2. Connexion à la base de données via db.php
require_once __DIR__ . '/../ifri_gestion_docs.php';

// 3. Récupération des filtres depuis l'URL (GET)
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : 'Tous';
$filter_statut = isset($_GET['statut']) ? trim($_GET['statut']) : 'Tous';
$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Compteur notifications admin
$admin_notif_count = 0;
try {
    $admin_notif_count = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE lue = 0")->fetchColumn();
} catch (PDOException $e) {}

// 4. Calcul des statistiques réelles pour les compteurs (StatsCards)
try {
    $total_demandes = $pdo->query("SELECT COUNT(*) FROM demandes")->fetchColumn();
    $en_attente = $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut_demande = 'En attente'")->fetchColumn();
    $traitees = $pdo->query("SELECT COUNT(*) FROM demandes WHERE statut_demande IN ('Prêt', 'Terminée', 'Délivré')")->fetchColumn();
} catch (PDOException $e) {
    $total_demandes = 0;
    $en_attente = 0;
    $traitees = 0;
}

// 5. Infos admin pour le header (avatar + nom)
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

// 6. Récupération des types de documents pour le select
try {
    $types_docs = $pdo->query("SELECT id_type, libelle FROM types_documents ORDER BY libelle ASC")->fetchAll();
} catch (PDOException $e) {
    $types_docs = [];
}

// 6. Construction de la requête principale filtrée (commune pour l'affichage et l'export)
$query_str = "SELECT d.*, e.nom, e.prenom, e.matricule, e.email, t.libelle
              FROM demandes d
              JOIN etudiants e ON d.id_etudiant = e.id_etudiant
              JOIN types_documents t ON d.id_type_doc = t.id_type WHERE 1=1";
$params = [];

if ($filter_type !== 'Tous') {
    $query_str .= " AND d.id_type_doc = :id_type";
    $params['id_type'] = $filter_type;
}

if ($filter_statut !== 'Tous') {
    $query_str .= " AND d.statut_demande = :statut_demande";
    $params['statut_demande'] = $filter_statut;
}

if (!empty($filter_date)) {
    $query_str .= " AND DATE(d.date_demande) = :date_demande";
    $params['date_demande'] = $filter_date;
}

if (!empty($search)) {
    $query_str .= " AND (e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule LIKE :search OR e.email LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

$query_str .= " ORDER BY d.date_demande DESC";

// 7. FONCTIONNALITÉ EN PLUS : Moteur d'exportation CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $stmt_export = $pdo->prepare($query_str);
        $stmt_export->execute($params);
        $demandes_export = $stmt_export->fetchAll();
        
        // Nom du fichier avec horodatage
        $filename = "export_demandes_" . date('Ymd_His') . ".csv";
        
        // En-têtes HTTP pour forcer le téléchargement du fichier CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Ouverture du flux de sortie PHP
        $output = fopen('php://output', 'w');
        
        // Ajout du BOM UTF-8 pour qu'Excel lise correctement les accents (é, è, etc.)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // En-têtes des colonnes du fichier CSV
        fputcsv($output, ['ID Demande', 'Matricule', 'Nom', 'Prénom', 'Email', 'ID Type Doc', 'Date de Demande', 'Statut']);
        
        // Injection des lignes de données
        foreach ($demandes_export as $row) {
            fputcsv($output, [
                $row['id_demande'],
                $row['matricule'] ?? 'N/A',
                $row['nom'],
                $row['prenom'],
                $row['email'],
                $row['id_type_doc'],
                $row['date_demande'],
                $row['statut_demande']
            ]);
        }
        
        fclose($output);
        exit; // On stoppe l'exécution ici pour ne pas charger le HTML dans le fichier
    } catch (PDOException $e) {
        die("Erreur lors de l'exportation CSV : " . $e->getMessage());
    }
}

// Execution normale pour l'affichage du tableau HTML
try {
    $stmt = $pdo->prepare($query_str);
    $stmt->execute($params);
    $demandes = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur SQL lors du chargement des demandes : " . $e->getMessage());
}

// 8. Récupération d'une demande urgente
try {
    $stmt_urgent = $pdo->query("SELECT d.*, e.nom, e.prenom 
                                FROM demandes d 
                                JOIN etudiants e ON d.id_etudiant = e.id_etudiant 
                                WHERE d.statut_demande = 'En attente' 
                                AND d.date_demande <= NOW() - INTERVAL 3 DAY 
                                ORDER BY d.date_demande ASC LIMIT 1");
    $demande_urgente = $stmt_urgent->fetch();
} catch (PDOException $e) {
    $demande_urgente = null;
}

// 9. Récupération des 3 activités les plus récentes
try {
    $stmt_activites = $pdo->query("SELECT d.date_demande, d.statut_demande, d.id_type_doc, e.nom, e.prenom, t.libelle
                                    JOIN types_documents t ON d.id_type_doc = t.id_type
                                    ORDER BY d.date_demande DESC LIMIT 3");
    $activites_recentes = $stmt_activites->fetchAll();
} catch (PDOException $e) {
    $activites_recentes = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Gestion des Demandes</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style data-purpose="typography">
        body { font-family: 'Inter', sans-serif; }
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
        .status-rejected { background-color: #FEE2E2; color: #991B1B; }
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
<body class="bg-main-content text-slate-800 antialiased min-h-screen flex">

    <aside class="w-64 bg-sidebar border-r border-slate-200 flex flex-col h-screen sticky top-0" data-purpose="sidebar">
        <div class="p-6">
            <div class="h-12 w-12 bg-primary text-white font-extrabold flex items-center justify-center rounded-xl mb-xs text-xl">
                <img src="../images/IFRI.png" alt="Logo IFRI" />
            </div>
            <h1 class="text-xl font-bold text-ifri-blue leading-tight">IFRI Portail</h1>
            <p class="text-xs text-slate-500">Espace Administrateur</p>
        </div>
        
        <nav class="flex-1 px-3 space-y-1 mt-4">
            <a class="flex items-center px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl font-medium transition-colors" href="dashboard_admin.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                Tableau de bord
            </a>
            <a class="flex items-center px-4 py-3 bg-active-green text-active-dark rounded-xl font-medium transition-colors" data-purpose="nav-item-active" href="mes_demandes_admin.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                Demandes
            </a>
            <a class="flex items-center px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl font-medium transition-colors" href="profile_admin.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                Profile
            </a>
        </nav>
        
        <div class="px-3 pb-6 space-y-1">
            <a class="flex items-center px-4 py-3 text-slate-600 hover:bg-slate-100 rounded-xl font-medium transition-colors" href="settings_admin.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                Paramètres
            </a>
            <a class="flex items-center px-4 py-3 text-red-500 hover:bg-red-50 rounded-xl font-medium transition-colors" href="../index.php">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                Déconnecter
            </a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 bg-white" data-purpose="main-content">
        <header class="h-16 border-b border-slate-200 flex items-center justify-between px-8 bg-white z-10">
            <div class="relative w-96">
                <form method="GET" action="mes_demandes_admin.php">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    </span>
                    <input name="search" value="<?= htmlspecialchars($search); ?>" class="block w-full pl-10 pr-3 py-2 border border-slate-200 rounded-full bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-ifri-blue transition-all" placeholder="Rechercher nom, prénom, email ou matricule..." type="text"/>
                </form>
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

            <button class="text-on-surface-variant hover:bg-surface-container transition-colors p-base rounded-full">
                <span class="material-symbols-outlined" data-icon="help">help</span>
            </button>     
            <a class="flex items-center space-x-3" href="profile_admin.php">
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
            
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-3xl font-bold text-slate-900">Gestion des Demandes</h2>
                    <p class="text-slate-500 mt-1">Consultez, filtrez et traitez les demandes de documents de l'IFRI.</p>
                </div>
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
                <div class="p-4 border border-b-0 border-slate-200 rounded-t-2xl bg-slate-50/70 flex flex-wrap items-center justify-between gap-4">
                    <form method="GET" action="mes_demandes_admin.php" class="flex flex-wrap gap-4 items-center">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-slate-600 whitespace-nowrap">Type:</span>
                            <select name="type" onchange="this.form.submit()" class="form-select text-sm border-slate-200 rounded-xl py-1 pr-8 pl-3 focus:ring-ifri-blue">
                                <option value="Tous" <?= $filter_type === 'Tous' ? 'selected' : ''; ?>>Tous les documents</option>
                                <?php foreach ($types_docs as $td): ?>
                                    <option value="<?= $td['id_type']; ?>" <?= $filter_type == $td['id_type'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($td['libelle'] ?? 'Type #' . $td['id_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-slate-600 whitespace-nowrap">Statut:</span>
                            <select name="statut" onchange="this.form.submit()" class="form-select text-sm border-slate-200 rounded-xl py-1 pr-8 pl-3 focus:ring-ifri-blue">
                                <option value="Tous" <?= $filter_statut === 'Tous' ? 'selected' : ''; ?>>Tous les statuts</option>
                                <option value="En attente" <?= $filter_statut === 'En attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="En cours" <?= $filter_statut === 'En cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="Prêt" <?= $filter_statut === 'Prêt' ? 'selected' : ''; ?>>Prêt</option>
                                <option value="Rejeté" <?= $filter_statut === 'Rejeté' ? 'selected' : ''; ?>>Rejeté</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-slate-600 whitespace-nowrap">Date:</span>
                            <input type="date" name="date" value="<?= htmlspecialchars($filter_date); ?>" onchange="this.form.submit()" class="form-input text-sm border-slate-200 rounded-xl py-1 px-3 focus:ring-ifri-blue w-40"/>
                        </div>
                        <?php if ($filter_type !== 'Tous' || $filter_statut !== 'Tous' || !empty($filter_date) || !empty($search)): ?>
                            <a href="mes_demandes_admin.php" class="text-xs text-red-500 hover:underline">Réinitialiser</a>
                        <?php endif; ?>
                    </form>
                    <span class="text-xs font-semibold text-slate-500 bg-white border border-slate-200 px-3 py-1 rounded-full">
                        Résultat : <?= count($demandes); ?> dossier(s)
                    </span>
                </div>

                <div class="border border-slate-200 rounded-b-2xl overflow-hidden bg-white">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-semibold">
                            <tr>
                                <th class="px-6 py-4">Étudiant</th>
                                <th class="px-6 py-4">Type Doc</th>
                                <th class="px-6 py-4">Date de Demande</th>
                                <th class="px-6 py-4">Statut</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($demandes)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-slate-400 italic text-sm">Aucune demande trouvée avec les filtres sélectionnés.</td>
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
                                    } elseif ($status_text === 'Rejeté') {
                                        $status_class = "status-rejected";
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
                                                <div class="text-xs text-slate-500">Matricule: <?= htmlspecialchars($demande['matricule'] ?? 'N/A'); ?> | <?= htmlspecialchars($demande['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-600 font-medium">
                                        <?= htmlspecialchars($demande['libelle'] ?? 'Document #' . $demande['id_type_doc']); ?>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-600">
                                        <?= date('d M Y à H:i', strtotime($demande['date_demande'])); ?>
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
                <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl p-8">
                    <h3 class="text-xl font-bold text-slate-900 mb-4">Demandes nécessitant attention</h3>
                    <?php if ($demande_urgente): ?>
                        <div class="bg-red-50 border border-red-100 rounded-xl p-4 flex items-start space-x-4">
                            <div class="p-2 bg-red-100 text-red-700 rounded-lg flex-shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-red-900">En retard (+72 heures)</p>
                                <p class="text-xs text-red-700 mt-1">
                                    L'étudiant <strong><?= htmlspecialchars($demande_urgente['prenom'] . ' ' . $demande_urgente['nom']); ?></strong> attend depuis le <?= date('d/m/Y', strtotime($demande_urgente['date_demande'])); ?>.
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-slate-500 italic">Aucune demande en souffrance de plus de 72 heures. Excellent travail ! </p>
                    <?php endif; ?>
                </div>

                <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl p-6" data-purpose="activity-feed">
                    <h3 class="text-lg font-bold text-slate-900 mb-4">Activité Récente</h3>
                    <div class="space-y-4">
                        <?php if (empty($activites_recentes)): ?>
                            <p class="text-sm text-slate-400 italic py-4">Aucune activité enregistrée pour le moment.</p>
                        <?php else: ?>
                            <?php foreach ($activites_recentes as $act): 
                                $statut = $act['statut_demande'];
                                $bar_color = "bg-blue-500";
                                if ($statut === 'En attente') { $bar_color = "bg-amber-500"; }
                                elseif ($statut === 'Prêt' || $statut === 'Terminée') { $bar_color = "bg-green-500"; }
                                elseif ($statut === 'Rejeté') { $bar_color = "bg-red-500"; }
                            ?>
                                <div class="flex gap-4 relative">
                                    <div class="w-1 <?= $bar_color; ?> rounded-full h-10 self-center"></div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-800">
                                            Document <?= htmlspecialchars($act['id_type_doc']); ?> - <?= htmlspecialchars($act['prenom'] . ' ' . $act['nom']); ?>
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            Statut : "<?= htmlspecialchars($statut); ?>" • Reçu le <?= date('d/m à H:i', strtotime($act['date_demande'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>