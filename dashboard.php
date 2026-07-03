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

// Charger les statistiques et demandes récentes
require_once __DIR__ . '/ifri_gestion_docs.php';
$id_etudiant = $_SESSION['user_id'];

$stats_total = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE id_etudiant = ?");
$stats_total->execute([$id_etudiant]);
$total_demandes = $stats_total->fetchColumn();

$stats_attente = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE id_etudiant = ? AND statut_demande = 'En attente'");
$stats_attente->execute([$id_etudiant]);
$en_attente = $stats_attente->fetchColumn();

$stats_cours = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE id_etudiant = ? AND statut_demande IN ('En cours','En traitement')");
$stats_cours->execute([$id_etudiant]);
$en_cours = $stats_cours->fetchColumn();

$stats_pret = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE id_etudiant = ? AND statut_demande = 'Prêt'");
$stats_pret->execute([$id_etudiant]);
$pret = $stats_pret->fetchColumn();

// Récupération des 5 dernières demandes
$recent = $pdo->prepare("SELECT d.*, t.libelle FROM demandes d JOIN types_documents t ON d.id_type_doc = t.id_type WHERE d.id_etudiant = ? ORDER BY d.date_demande DESC LIMIT 5");
$recent->execute([$id_etudiant]);
$demandes_recentes = $recent->fetchAll();

// Compter les notifications non lues
$non_lues = 0;
try {
    $nn = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_etudiant = ? AND lue = 0");
    $nn->execute([$id_etudiant]);
    $non_lues = $nn->fetchColumn();
} catch (PDOException $e) {
    $non_lues = 0;
}

// Types de documents pour le formulaire
$stmt_types = $pdo->query("SELECT * FROM types_documents WHERE actif = 1 OR actif IS NULL ORDER BY id_type");
$types_docs = $stmt_types->fetchAll();

$pieces_map = [
    1 => [
        'Copie de fiche de pré-inscription validée de l\'année académique concernée',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    2 => [
        'Copie de fiche de pré-inscription validée de l\'année académique concernée',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    3 => [
        'Copie de fiche de pré-inscription validée de la dernière année académique',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie des relevés de notes requis',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    4 => [
        'Demande adressée au directeur de l\'IFRI',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie légalisée du certificat de perte',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    5 => [
        'Copie de fiche de pré-inscription validée de l\'année académique',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    6 => [
        'Copie de fiche de pré-inscription validée de la dernière année académique',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)'
    ],
    7 => [
        'Copie de l\'attestation du diplôme',
        'Copie des relevés de notes du 1er au 6ème semestre (Licence) ou 1er au 4ème semestre (Master)',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    8 => [
        'Demande adressée au directeur de l\'IFRI',
        'Copie de l\'acte ou des actes à certifier',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    9 => [
        'Copie de fiche de pré-inscription validée de la dernière année académique',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie des relevés de notes requis',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ]
];
?>
<!DOCTYPE html>
<html class="light" lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Tableau de Bord</title>
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

    <aside class="fixed left-0 top-0 h-screen w-64 bg-surface-container-low border-r border-outline-variant flex flex-col py-base px-sm overflow-y-auto z-40">
        <div class="mb-lg px-base">
            <div class="h-12 w-12 bg-primary text-white font-extrabold flex items-center justify-center rounded-xl mb-xs text-xl">
                <img src="./images/IFRI.png" alt="Logo IFRI" />
            </div>
            <h1 class="font-headline-md text-headline-md font-extrabold text-primary">IFRI Portail</h1>
            <p class="font-label-md text-label-md text-on-surface-variant">Gestion des documents</p>
        </div>

        <nav class="flex-1 flex flex-col gap-xs">
            <a class="flex items-center gap-sm px-sm py-md bg-secondary-container text-on-secondary-container rounded-lg font-bold transition-all" href="dashboard.php">
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

    <main class="flex-1 ml-64 overflow-y-auto">

        <header class="flex justify-between items-center w-full px-gutter h-16 sticky top-0 z-50 bg-surface border-b border-outline-variant shadow-sm">
            <div class="flex items-center gap-sm">
                <div class="flex items-center bg-surface-container-low px-sm py-xs rounded-full border border-outline-variant">
                    <span class="material-symbols-outlined text-on-surface-variant" data-icon="search">search</span>
                    <input class="bg-transparent border-none focus:ring-0 text-label-md w-64" placeholder="Rechercher une demande..." type="text"/>
                </div>
            </div>

            <div class="flex items-center gap-md">
                <a href="notifications.php" class="relative inline-flex items-center justify-center w-9 h-9 rounded-full <?= $non_lues > 0 ? 'bg-amber-100 bell-ring' : 'hover:bg-surface-container'; ?> transition-all">
                    <span class="material-symbols-outlined <?= $non_lues > 0 ? 'text-amber-600' : 'text-on-surface-variant'; ?>" style="font-size:20px;" data-icon="notifications">notifications</span>
                    <?php if ($non_lues > 0): ?>
                    <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center shadow-lg px-1 blink-badge"><?= min($non_lues, 99); ?></span>
                    <?php else: ?>
                    <span class="absolute top-1 right-1 h-2 w-2 bg-red-400 rounded-full"></span>
                    <?php endif; ?>
                </a>

                <a href="faq.php" class="text-on-surface-variant hover:bg-surface-container transition-colors p-base rounded-full inline-flex items-center justify-center">
                    <span class="material-symbols-outlined" data-icon="help">help</span>
                </a>

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

        <div class="max-w-container-max mx-auto p-lg">
            <div class="flex items-center justify-between mb-xl">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-primary">Tableau de Bord</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Gérez vos demandes de documents académiques</p>
                </div>
                <div class="pb-2">
                    <button onclick="openModal('requestModal')" class="text-white px-6 py-3 rounded-lg text-sm font-semibold flex items-center gap-2 hover:bg-opacity-90 shadow-sm transition-colors cursor-pointer" style="background-color: #006E0C;">
                        <span class="text-lg">+</span> Nouvelle Demande
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-xl">
                <div class="bg-white p-lg rounded-xl border border-outline-variant shadow-sm flex items-center gap-md">
                    <div class="p-md bg-surface-container text-primary rounded-xl">
                        <span class="material-symbols-outlined" data-icon="history">history</span>
                    </div>
                    <div>
                        <p class="text-label-sm text-on-surface-variant">Total</p>
                        <p class="text-headline-md font-bold"><?= $total_demandes; ?></p>
                    </div>
                </div>
                <div class="bg-white p-lg rounded-xl border border-outline-variant shadow-sm flex items-center gap-md">
                    <div class="p-md bg-amber-50 text-amber-600 rounded-xl">
                        <span class="material-symbols-outlined" data-icon="pending">pending</span>
                    </div>
                    <div>
                        <p class="text-label-sm text-on-surface-variant">En attente</p>
                        <p class="text-headline-md font-bold"><?= $en_attente; ?></p>
                    </div>
                </div>
                <div class="bg-white p-lg rounded-xl border border-outline-variant shadow-sm flex items-center gap-md">
                    <div class="p-md bg-orange-50 text-orange-600 rounded-xl">
                        <span class="material-symbols-outlined" data-icon="sync">sync</span>
                    </div>
                    <div>
                        <p class="text-label-sm text-on-surface-variant">En traitement</p>
                        <p class="text-headline-md font-bold"><?= $en_cours; ?></p>
                    </div>
                </div>
                <div class="bg-white p-lg rounded-xl border border-outline-variant shadow-sm flex items-center gap-md">
                    <div class="p-md bg-emerald-50 text-emerald-600 rounded-xl">
                        <span class="material-symbols-outlined" data-icon="check_circle">check_circle</span>
                    </div>
                    <div>
                        <p class="text-label-sm text-on-surface-variant">Prêt</p>
                        <p class="text-headline-md font-bold"><?= $pret; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-outline-variant shadow-sm overflow-hidden">
                <div class="px-lg py-md border-b border-outline-variant bg-surface-container-low flex justify-between items-center">
                    <h3 class="font-headline-md text-headline-md text-on-surface">Vos Demandes Récentes</h3>
                    <button class="text-label-md text-primary flex items-center gap-xs">
                        <span class="material-symbols-outlined text-sm" data-icon="filter_list">filter_list</span>
                        Filtrer
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-surface-container-lowest text-on-surface-variant text-label-sm">
                                <th class="px-lg py-md font-bold">TYPE DE DOCUMENT</th>
                                <th class="px-lg py-md font-bold">DATE</th>
                                <th class="px-lg py-md font-bold">STATUT</th>
                                <th class="px-lg py-md font-bold text-right">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <?php if (empty($demandes_recentes)): ?>
                            <tr>
                                <td colspan="4" class="px-lg py-md text-center text-on-surface-variant italic">
                                    Aucune demande effectuée pour le moment.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($demandes_recentes as $d): ?>
                                <?php
                                    $s = $d['statut_demande'];
                                    $badge = match($s) {
                                        'Prêt' => 'bg-emerald-100 text-emerald-700',
                                        'En cours','En traitement' => 'bg-orange-100 text-orange-700',
                                        'Terminée' => 'bg-slate-200 text-slate-600',
                                        'Rejeté' => 'bg-red-100 text-red-700',
                                        default => 'bg-slate-200 text-slate-600'
                                    };
                                    $dot = match($s) {
                                        'Prêt' => 'bg-emerald-500',
                                        'En cours','En traitement' => 'bg-orange-500',
                                        'Rejeté' => 'bg-red-500',
                                        default => 'bg-slate-400'
                                    };
                                ?>
                                <tr class="hover:bg-surface-container-low transition-colors">
                                    <td class="px-lg py-md">
                                        <span class="font-semibold text-on-surface"><?= htmlspecialchars($d['libelle']); ?></span>
                                    </td>
                                    <td class="px-lg py-md text-sm text-on-surface-variant"><?= date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                                    <td class="px-lg py-md">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?= $badge; ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $dot; ?>"></span>
                                            <?= $s === 'En cours' ? 'En traitement' : ($s === 'Prêt' ? 'Prêt' : ($s === 'Rejeté' ? 'Rejetée' : ($s === 'Terminée' ? 'Retirée' : 'En attente'))); ?>
                                        </span>
                                    </td>
                                    <td class="px-lg py-md text-right">
                                        <?php if ($s === 'Prêt'): ?>
                                            <a href="telechargement.php?id=<?= $d['id_demande']; ?>"
                                               class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-bold hover:bg-emerald-100 transition-all border border-emerald-200">
                                                <span class="material-symbols-outlined text-sm">download</span>
                                                Télécharger
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-xl grid grid-cols-1 md:grid-cols-2 gap-xl">
                <div class="rounded-xl overflow-hidden relative h-64 shadow-lg bg-primary/90 flex flex-col justify-end p-lg">
                    <div class="absolute inset-0 bg-gradient-to-t from-primary to-transparent opacity-60"></div>
                    <div class="relative z-10">
                        <h4 class="text-white font-headline-md text-headline-md">Guide des Demandes</h4>
                        <p class="text-primary-fixed font-body-md text-body-md">Apprenez-en plus sur les délais de traitement par type de document.</p>
                    </div>
                </div>
                <div class="bg-primary-container text-on-primary-container p-lg rounded-xl flex flex-col justify-center border border-primary">
                    <h4 class="font-headline-md text-headline-md mb-sm">Besoin d'aide ?</h4>
                    <p class="font-body-md mb-lg">Notre équipe administrative est disponible du lundi au vendredi pour répondre à vos questions concernant vos certificats et diplômes.</p>
                    <button class="bg-white text-primary w-fit px-lg py-md rounded-lg font-bold hover:bg-opacity-90 transition-all">
                        Contacter le Secrétariat
                    </button>
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
</body>
</html>
