<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// On récupère proprement les valeurs
$nom = isset($_SESSION['nom']) ? trim($_SESSION['nom']) : '';
$prenom = isset($_SESSION['prenom']) ? trim($_SESSION['prenom']) : '';

// Si les sessions sont vides, on met un affichage générique propre
if (empty($nom) && empty($prenom)) {
    $nom_complet = "Étudiant Connecté";
    $initiales = "ET";
} else {
    $nom_complet = trim($prenom . ' ' . $nom);

    // Récupération sécurisée de la première lettre en UTF-8
    $initiale_prenom = (!empty($prenom)) ? mb_strtoupper(mb_substr($prenom, 0, 1, 'UTF-8'), 'UTF-8') : '';
    $initiale_nom = (!empty($nom)) ? mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8') : '';
    $initiales = $initiale_prenom . $initiale_nom;
}
$photo_path = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : null;

// Chargement des notifications
require_once __DIR__ . '/ifri_gestion_docs.php';
$id_etudiant = $_SESSION['user_id'];

$notifications = [];
$non_lues = 0;
try {
    $notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE id_etudiant = ? ORDER BY cree_at DESC LIMIT 20");
    $notif_stmt->execute([$id_etudiant]);
    $notifications = $notif_stmt->fetchAll();

    $non_lues_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_etudiant = ? AND lue = 0");
    $non_lues_stmt->execute([$id_etudiant]);
    $non_lues = $non_lues_stmt->fetchColumn();
} catch (PDOException $e) {
    // Table notifications pas encore créée
}

// Tout marquer comme lu (doit être AVANT tout affichage HTML)
if (isset($_GET['mark_all_read'])) {
    try {
        $upd = $pdo->prepare("UPDATE notifications SET lue = 1 WHERE id_etudiant = ? AND lue = 0");
        $upd->execute([$id_etudiant]);
    } catch (PDOException $e) {}
    header('Location: notifications.php');
    exit;
}

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
<html class="light" lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Notifications</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .modal-backdrop {
            backdrop-filter: blur(8px);
            background-color: rgba(0, 0, 0, 0.4);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(4px);
        }
        .glow-dot {
            animation: dotPulse 2s ease-in-out infinite;
        }
        @keyframes dotPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "inverse-on-surface": "#f0f1f2",
                        "surface": "#f8f9fa",
                        "on-tertiary-fixed-variant": "#6e3900",
                        "background": "#f8f9fa",
                        "secondary-container": "#8ff780",
                        "on-tertiary-container": "#ffc395",
                        "tertiary-fixed-dim": "#ffb77d",
                        "surface-container-low": "#f3f4f5",
                        "secondary": "#006e0c",
                        "outline-variant": "#c2c6d4",
                        "error": "#ba1a1a",
                        "inverse-surface": "#2e3132",
                        "surface-tint": "#115cb9",
                        "error-container": "#ffdad6",
                        "surface-variant": "#e1e3e4",
                        "on-secondary-fixed": "#002201",
                        "on-error-container": "#93000a",
                        "on-surface": "#191c1d",
                        "on-error": "#ffffff",
                        "on-primary-container": "#bbd0ff",
                        "primary-fixed-dim": "#acc7ff",
                        "surface-bright": "#f8f9fa",
                        "surface-container-lowest": "#ffffff",
                        "on-primary-fixed-variant": "#004491",
                        "on-tertiary-fixed": "#2f1500",
                        "on-primary": "#ffffff",
                        "surface-dim": "#d9dadb",
                        "on-background": "#191c1d",
                        "on-primary-fixed": "#001a40",
                        "tertiary": "#663400",
                        "on-tertiary": "#ffffff",
                        "on-surface-variant": "#424752",
                        "surface-container-high": "#e7e8e9",
                        "inverse-primary": "#acc7ff",
                        "outline": "#727784",
                        "secondary-fixed-dim": "#77dd6a",
                        "primary-container": "#0056b3",
                        "primary": "#003f87",
                        "surface-container": "#edeeef",
                        "on-secondary": "#ffffff",
                        "on-secondary-fixed-variant": "#005307",
                        "primary-fixed": "#d7e2ff",
                        "secondary-fixed": "#92fa83",
                        "surface-container-highest": "#e1e3e4",
                        "tertiary-container": "#884800",
                        "tertiary-fixed": "#ffdcc3",
                        "on-secondary-container": "#00730d"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "gutter": "24px",
                        "xl": "64px",
                        "xs": "4px",
                        "lg": "40px",
                        "base": "8px",
                        "container-max": "1280px",
                        "md": "24px",
                        "sm": "12px"
                    },
                    "fontFamily": {
                        "label-md": ["Inter"],
                        "headline-md": ["Inter"],
                        "body-lg": ["Inter"],
                        "headline-lg": ["Inter"],
                        "label-sm": ["Inter"],
                        "body-md": ["Inter"],
                        "display-lg": ["Inter"]
                    },
                    "fontSize": {
                        "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.01em", "fontWeight": "500"}],
                        "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                        "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}],
                        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "display-lg": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}]
                    }
                },
            }
        }
    </script>
</head>
<body class="bg-surface text-on-surface font-body-md overflow-hidden">
<div class="flex h-screen overflow-hidden">

    <!-- BARRE LATERALE (ASIDE) -->
    <aside class="fixed left-0 top-0 h-screen w-64 bg-surface-container-low border-r border-outline-variant flex flex-col py-base px-sm overflow-y-auto z-40">
        <div class="mb-lg px-base">
            <div class="h-12 w-12 bg-primary text-white font-extrabold flex items-center justify-center rounded-xl mb-xs text-xl">
                <img src="./images/IFRI.png" alt="Logo IFRI" />
            </div>
            <h1 class="font-headline-md text-headline-md font-extrabold text-primary">IFRI Portail</h1>
            <p class="font-label-md text-label-md text-on-surface-variant">Gestion des documents</p>
        </div>

        <nav class="flex-1 flex flex-col gap-xs">
            <a class="flex items-center gap-sm px-sm py-md text-on-surface-variant hover:bg-surface-container-high rounded-lg transition-all" href="dashboard.php">
                <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>
                <span class="font-label-md text-label-md">Tableau de bord</span>
            </a>
            <a class="flex items-center gap-sm px-sm py-md text-on-surface-variant hover:bg-surface-container-high rounded-lg transition-all" href="mes_demandes.php">
                <span class="material-symbols-outlined" data-icon="description">description</span>
                <span class="font-label-md text-label-md">Demandes</span>
            </a>
            <a class="flex items-center gap-sm px-sm py-md text-on-surface-variant hover:bg-surface-container-high rounded-lg transition-all" href="profile.php">
                <span class="material-symbols-outlined" data-icon="person">person</span>
                <span class="font-label-md text-label-md">Profile</span>
            </a>
        </nav>

        <button class="mt-lg w-full py-md bg-primary text-on-primary rounded-xl font-bold flex items-center justify-center gap-sm shadow-sm hover:scale-[1.02] transition-transform" onclick="openModal('requestModal')">
            <span class="material-symbols-outlined" data-icon="add">add</span>
            <span>Nouvelle demande</span>
        </button>

        <div class="mt-auto pt-lg border-t border-outline-variant flex flex-col gap-xs">
            <a class="flex items-center gap-sm px-sm py-sm text-on-surface-variant hover:bg-surface-container-high rounded-lg transition-all" href="settings.php">
                <span class="material-symbols-outlined" data-icon="settings">settings</span>
                <span class="font-label-md text-label-md">Paramètres</span>
            </a>
            <a class="flex items-center gap-sm px-sm py-sm text-error hover:bg-error-container rounded-lg transition-all" href="index.php">
                <span class="material-symbols-outlined" data-icon="logout">logout</span>
                <span class="font-label-md text-label-md">Déconnecter</span>
            </a>
        </div>
    </aside>

    <!-- EN-TETE ET CONTENU PRINCIPAL -->
    <main class="flex-1 ml-64 overflow-y-auto">

        <header class="flex justify-between items-center w-full px-gutter h-16 sticky top-0 z-50 bg-surface border-b border-outline-variant shadow-sm">
            <div class="flex items-center gap-sm">
                <div class="flex items-center bg-surface-container-low px-sm py-xs rounded-full border border-outline-variant">
                    <span class="material-symbols-outlined text-on-surface-variant" data-icon="search">search</span>
                    <input class="bg-transparent border-none focus:ring-0 text-label-md w-64" placeholder="Rechercher une notification..." type="text"/>
                </div>
            </div>

            <div class="flex items-center gap-md">
                <button class="text-on-surface-variant bg-surface-container transition-colors p-base rounded-full relative">
                    <span class="material-symbols-outlined" data-icon="notifications">notifications</span>
                    <?php if ($non_lues > 0): ?>
                        <span class="absolute top-1 right-1 h-2 w-2 bg-error rounded-full glow-dot"></span>
                    <?php endif; ?>
                </button>

                <button class="text-on-surface-variant hover:bg-surface-container transition-colors p-base rounded-full">
                    <span class="material-symbols-outlined" data-icon="help">help</span>
                </button>

                <a href="profile.php" class="flex items-center gap-sm">
                    <span class="text-primary font-bold text-sm hidden md:inline-block"><?php echo htmlspecialchars($nom_complet); ?></span>
                    <?php if (!empty($photo_path) && is_file(__DIR__ . '/' . $photo_path)): ?>
                        <img src="<?= htmlspecialchars($photo_path) ?>" alt="Photo" class="w-10 h-10 rounded-full object-cover border border-primary text-sm shadow-sm" />
                    <?php else: ?>
                        <div class="h-10 w-10 rounded-full bg-primary text-white font-bold flex items-center justify-center border border-primary text-sm shadow-sm">
                            <?php echo $initiales; ?>
                        </div>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <!-- ZONE DE CONTENU EDITABLE DES NOTIFICATIONS -->
        <div class="max-w-container-max mx-auto p-lg">
            <div class="flex items-center justify-between mb-xl">
                <div>
                    <div>
                        <h2 class="font-headline-lg text-headline-lg text-primary">
                            Notifications
                            <?php if ($non_lues > 0): ?>
                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-sm font-bold bg-[#004A99] text-white ml-2"><?= $non_lues; ?></span>
                            <?php endif; ?>
                        </h2>
                        <p class="font-body-md text-body-md text-on-surface-variant">Suivez l'évolution et le traitement de vos demandes de documents</p>
                    </div>
                </div>
                <?php if ($non_lues > 0): ?>
                <a href="?mark_all_read=1"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#004A99] text-white text-sm font-bold rounded-xl hover:bg-[#00387a] transition-all shadow-md shadow-[#004A99]/20 active:scale-95 shrink-0">
                    <span class="material-symbols-outlined" style="font-size:18px;">done_all</span>
                    Tout marquer comme lu
                </a>
                <?php endif; ?>
            </div>

            <!-- Liste des notifications -->
            <div class="space-y-3">
                <?php if (empty($notifications)): ?>
                    <div class="bg-white p-lg rounded-xl border border-outline-variant shadow-sm text-center text-on-surface-variant italic">
                        Aucune notification pour le moment.
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <?php
                            $niveau = $n['niveau'];
                            $icon = match($niveau) {
                                'urgent' => 'priority_high',
                                'systeme' => 'settings',
                                default => 'notifications'
                            };
                            $color = match($niveau) {
                                'urgent' => 'bg-red-50 border-red-200',
                                'systeme' => 'bg-purple-50 border-purple-200',
                                default => 'bg-blue-50 border-blue-200'
                            };
                            $icon_color = match($niveau) {
                                'urgent' => 'text-red-600 bg-red-100',
                                'systeme' => 'text-purple-600 bg-purple-100',
                                default => 'text-blue-600 bg-blue-100'
                            };
                        ?>
                        <div class="bg-white rounded-xl border border-outline-variant shadow-sm p-4 hover:shadow-md transition-all <?= !$n['lue'] ? 'border-l-4 border-l-[#004A99]' : ''; ?>">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-full <?= $icon_color; ?> flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined"><?= $icon; ?></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="font-bold text-sm text-on-surface <?= !$n['lue'] ? '' : ''; ?>">
                                            <?= htmlspecialchars($n['titre']); ?>
                                            <?php if (!$n['lue']): ?>
                                                <span class="inline-block w-2 h-2 rounded-full bg-[#004A99] ml-2"></span>
                                            <?php endif; ?>
                                        </h4>
                                        <span class="text-xs text-on-surface-variant shrink-0"><?= date('d/m H:i', strtotime($n['cree_at'])); ?></span>
                                    </div>
                                    <p class="text-sm text-on-surface-variant mt-1"><?= htmlspecialchars($n['message']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Marquage des notifications comme lues -->
            <?php if ($non_lues > 0): ?>
            <script>
                // Marquer comme lues après 2 secondes
                setTimeout(() => {
                    fetch('notifications.php?mark_read=1', { method: 'HEAD' });
                }, 2000);
            </script>
            <?php
                // Traitement silencieux du marquage
                if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD' && isset($_GET['mark_read'])) {
                    $upd = $pdo->prepare("UPDATE notifications SET lue = 1 WHERE id_etudiant = ? AND lue = 0");
                    $upd->execute([$id_etudiant]);
                    http_response_code(200);
                    exit;
                }
            ?>
            <?php endif; ?>

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
</body>
</html>
