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

// 3. Récupération des infos de l'étudiant pour le Header
$nom = isset($_SESSION['nom']) ? trim($_SESSION['nom']) : '';
$prenom = isset($_SESSION['prenom']) ? trim($_SESSION['prenom']) : '';
$nom_complet = !empty($nom) ? trim($prenom . ' ' . $nom) : "Étudiant Connecté";

// Génération propre des initiales
$initiale_prenom = (!empty($prenom)) ? mb_strtoupper(mb_substr($prenom, 0, 1, 'UTF-8'), 'UTF-8') : '';
$initiale_nom = (!empty($nom)) ? mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8') : '';
$initiales = (!empty($initiale_prenom) || !empty($initiale_nom)) ? $initiale_prenom . $initiale_nom : "ET";
$photo_path = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : null;

// 4. Calcul des statistiques réelles de l'étudiant connecté
// Total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE id_etudiant = :id");
$stmt->execute(['id' => $id_etudiant]);
$total_demandes = $stmt->fetchColumn();

// En cours / En traitement
$stmt = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE id_etudiant = :id AND statut_demande IN ('En attente', 'En traitement', 'En cours')");
$stmt->execute(['id' => $id_etudiant]);
$total_en_cours = $stmt->fetchColumn();

// Prêts à retirer
$stmt = $pdo->prepare("SELECT COUNT(*) FROM demandes WHERE id_etudiant = :id AND statut_demande = 'Prêt'");
$stmt->execute(['id' => $id_etudiant]);
$total_prets = $stmt->fetchColumn();

// Notifications non lues
$non_lues = 0;
try {
    $nn = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_etudiant = ? AND lue = 0");
    $nn->execute([$id_etudiant]);
    $non_lues = $nn->fetchColumn();
} catch (PDOException $e) {
    $non_lues = 0;
}

// 5. Récupération de l'historique complet des demandes de l'étudiant
$stmt = $pdo->prepare("SELECT d.*, t.libelle FROM demandes d JOIN types_documents t ON d.id_type_doc = t.id_type WHERE d.id_etudiant = :id ORDER BY d.date_demande DESC");
$stmt->execute(['id' => $id_etudiant]);
$demandes = $stmt->fetchAll();

// 6. Types de documents disponibles pour le formulaire
$stmt_types = $pdo->query("SELECT * FROM types_documents WHERE actif = 1 OR actif IS NULL ORDER BY id_type");
$types_docs = $stmt_types->fetchAll();

// Pièces requises pour chaque type de document
$pieces_map = [
    1 => [ // Attestation d'inscription / Certificat de scolarité
        'Copie de fiche de pré-inscription validée de l\'année académique concernée',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    2 => [ // Relevé de notes
        'Copie de fiche de pré-inscription validée de l\'année académique concernée',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    3 => [ // Attestation de succès
        'Copie de fiche de pré-inscription validée de la dernière année académique',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie des relevés de notes requis',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    4 => [ // Duplicata de scolarité
        'Demande adressée au directeur de l\'IFRI',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie légalisée du certificat de perte',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    5 => [ // Réclamation
        'Copie de fiche de pré-inscription validée de l\'année académique',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    6 => [ // Attestation de main-levée
        'Copie de fiche de pré-inscription validée de la dernière année académique',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)'
    ],
    7 => [ // Supplément au diplôme
        'Copie de l\'attestation du diplôme',
        'Copie des relevés de notes du 1er au 6ème semestre (Licence) ou 1er au 4ème semestre (Master)',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    8 => [ // Certification de documents
        'Demande adressée au directeur de l\'IFRI',
        'Copie de l\'acte ou des actes à certifier',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ],
    9 => [ // Attestation d'admissibilité
        'Copie de fiche de pré-inscription validée de la dernière année académique',
        'Copie simple de l\'acte de naissance',
        'Copie simple du Certificat d\'Identification Personnelle (CIP)',
        'Copie des relevés de notes requis',
        'Copie et original de la quittance de paiement des frais pour l\'acte'
    ]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Mes Demandes</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <script>
        tailwind.config = {
          theme: {
            extend: {
              colors: {
                'ifri-blue': '#003d7a',
                'ifri-light-blue': '#e9f2ff',
                'ifri-green': '#90ee90',
                'surface-bg': '#f8f9fa',
              },
              fontFamily: {
                sans: ['Inter', 'sans-serif'],
              },
              borderRadius: {
                'ifri': '8px',
              }
            }
          }
        }
    </script>
    <style data-purpose="custom-styling">
        body {
          font-family: 'Inter', sans-serif;
          background-color: #f8f9fa;
        }
        .active-nav {
          background-color: #8ff780;
          color: #424752;
        }
        .sidebar-width {
          width: 260px;
        }
                /* Backdrop for modals: enable blur + semi-transparent overlay */
                .modal-backdrop {
                    backdrop-filter: blur(8px);
                    -webkit-backdrop-filter: blur(8px);
                    background-color: rgba(0, 0, 0, 0.35);
                }
                .fade-in {
                    animation: fadeIn 0.4s ease-out;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(-10px); }
                    to { opacity: 1; transform: translateY(0); }
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
<body class="flex min-h-screen text-slate-800">

<aside class="sidebar-width bg-white border-r border-gray-200 flex flex-col fixed h-full z-20" data-purpose="main-navigation">
    <div class="p-6">
        <div class="flex items-center gap-3 mb-1">
            <img src="./images/IFRI.png" alt="Logo IFRI" class="w-12 h-12 object-contain" />
        </div>
        <h1 class="text-xl font-bold text-ifri-blue">IFRI Portail</h1>
        <p class="text-xs text-gray-500">Gestion des documents</p>
    </div>
    <nav class="flex-1 px-4 space-y-1">
        <a class="flex items-center gap-3 px-3 py-3 text-gray-600 hover:bg-gray-50 rounded-ifri" href="dashboard.php">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
            </svg>
            <span class="font-medium">Tableau de bord</span>
        </a>
        <a class="flex items-center gap-3 px-3 py-3 active-nav rounded-ifri shadow-sm" href="mes_demandes.php" style="color: #00730D;">
            <svg class="h-5 w-5" fill="none" stroke="#00730D" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
            </svg>
            <span class="font-medium">Demandes</span>
        </a>
        <a class="flex items-center gap-3 px-3 py-3 text-gray-600 hover:bg-gray-50 rounded-ifri" href="profile.php">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
            </svg>
            <span class="font-medium">Profile</span>
        </a>
    </nav>
        <div class="p-4 space-y-4">
        <button type="button" onclick="openModal('requestModal')" class="w-full bg-[#003d7a] text-white py-3 px-4 rounded-ifri flex items-center justify-center gap-2 font-semibold hover:bg-opacity-90 transition-all text-center">
            <span class="text-xl">+</span> Nouvelle demande
        </button>
        <div class="pt-4 border-t border-gray-100">
            <a class="flex items-center gap-3 px-3 py-3 text-gray-600 hover:bg-gray-50 rounded-ifri" href="settings.php">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                    <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                </svg>
                <span class="font-medium">Paramètres</span>
            </a>
            <a class="flex items-center gap-3 px-3 py-3 text-red-600 hover:bg-red-50 rounded-ifri" href="index.php">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                </svg>
                <span class="font-medium">Déconnecter</span>
            </a>
        </div>
    </div>
</aside>
<div class="flex-1 ml-[260px] flex flex-col">
    <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 sticky top-0 z-10" data-purpose="top-utility-bar">
        <div class="relative w-96">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                    <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                </svg>
            </span>
            <input class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-full bg-gray-50 text-sm focus:outline-none focus:ring-1 focus:ring-ifri-blue focus:border-ifri-blue" placeholder="Rechercher une demande..." type="text"/>
        </div>
        <div class="flex items-center gap-4">
            <a href="notifications.php" class="relative inline-flex items-center justify-center w-9 h-9 rounded-full <?= $non_lues > 0 ? 'bg-amber-100 bell-ring' : 'hover:text-ifri-blue hover:bg-gray-100'; ?> transition-all" title="Notifications">
                <span class="material-symbols-outlined <?= $non_lues > 0 ? 'text-amber-600' : 'text-gray-500'; ?>" style="font-size:20px;">notifications</span>
                <?php if ($non_lues > 0): ?>
                    <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center shadow-lg px-1 blink-badge"><?= min($non_lues, 99); ?></span>
                <?php else: ?>
                    <span class="absolute top-1 right-1 h-2 w-2 bg-red-400 rounded-full"></span>
                <?php endif; ?>
            </a>
            <a href="faq.php" class="text-on-surface-variant hover:bg-surface-container transition-colors p-base rounded-full inline-flex items-center justify-center">
                    <span class="material-symbols-outlined" data-icon="help">help</span>
                </a>
            <a href="profile.php" class="flex items-center gap-3">
                <span class="text-sm font-semibold text-ifri-blue"><?= htmlspecialchars($nom_complet) ?></span>
                <?php if (!empty($photo_path) && is_file(__DIR__ . '/' . $photo_path)): ?>
                    <img src="<?= htmlspecialchars($photo_path) ?>" alt="Photo de profil" class="w-10 h-10 rounded-full object-cover border border-gray-200" />
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-[#003d7a] text-white flex items-center justify-center font-bold text-sm border border-gray-200">
                        <?= htmlspecialchars($initiales) ?>
                    </div>
                <?php endif; ?>
            </a>
        </div>
    </header>
    <main class="p-8 space-y-8 max-w-7xl mx-auto w-full">

        <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center gap-3 fade-in">
            <div class="flex-1">
                <p class="text-sm font-bold text-emerald-800">Demande soumise avec succès !</p>
                <p class="text-xs text-emerald-600 mt-0.5">Votre demande a été enregistrée et sera traitée par l'administration.</p>
            </div>
            <a href="mes_demandes.php" class="text-emerald-500 hover:text-emerald-700">
                <span class="material-symbols-outlined">close</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3 fade-in">
            <div class="flex-1">
                <p class="text-sm font-bold text-red-800">
                    <?php if ($_GET['error'] === 'no_files_uploaded'): ?>
                        Aucun fichier n'a pu être uploadé. Veuillez réessayer.
                    <?php elseif ($_GET['error'] === 'missing_files'): ?>
                        Vous devez sélectionner au moins un fichier.
                    <?php elseif ($_GET['error'] === 'missing_fields'): ?>
                        Veuillez remplir tous les champs obligatoires.
                    <?php else: ?>
                        Une erreur est survenue. Veuillez réessayer.
                    <?php endif; ?>
                </p>
            </div>
            <a href="mes_demandes.php" class="text-red-500 hover:text-red-700">
                <span class="material-symbols-outlined">close</span>
            </a>
        </div>
        <?php endif; ?>

        <section class="flex items-center justify-between" data-purpose="page-title-section">
            <div>
                <h2 class="text-3xl font-bold text-[#003d7a]">Mes Demandes</h2>
                <p class="text-gray-500 mt-1">Suivez et gérez vos demandes de documents académiques en temps réel.</p>
            </div>
            <button type="button" onclick="openModal('requestModal')" class="text-white px-6 py-3 rounded-ifri flex items-center gap-2 font-semibold hover:bg-opacity-90 transition-all" style="background-color: #006E0C;">
                <span class="text-xl">+</span> Nouvelle Demande
            </button>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-6" data-purpose="statistics-overview">
            <div class="bg-white p-6 rounded-ifri border border-gray-100 shadow-sm flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <span class="text-sm font-medium text-gray-500">Total Demandes</span>
                    <div class="text-ifri-blue">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                        </svg>
                    </div>
                </div>
                <span class="text-4xl font-bold"><?= sprintf("%02d", $total_demandes) ?></span>
            </div>
            <div class="bg-white p-6 rounded-ifri border border-gray-100 shadow-sm flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <span class="text-sm font-medium text-gray-500">En cours</span>
                    <div class="text-amber-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                        </svg>
                    </div>
                </div>
                <span class="text-4xl font-bold text-amber-600"><?= sprintf("%02d", $total_en_cours) ?></span>
            </div>
            <div class="bg-white p-6 rounded-ifri border border-gray-100 shadow-sm flex flex-col justify-between h-32">
                <div class="flex justify-between items-start">
                    <span class="text-sm font-medium text-gray-500">Prêts à retirer</span>
                    <div class="text-emerald-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                        </svg>
                    </div>
                </div>
                <span class="text-4xl font-bold text-emerald-500"><?= sprintf("%02d", $total_prets) ?></span>
            </div>
        </section>

        <section class="bg-white rounded-ifri border border-gray-200 shadow-sm overflow-hidden" data-purpose="documents-table-container">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-white">
                <h3 class="text-sm font-bold text-gray-700 tracking-wider uppercase">Historique des documents</h3>
            </div>

            <table class="w-full text-left border-collapse" id="requests-history">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase">
                    <tr>
                        <th class="px-6 py-4">Type de Document & Motif</th>
                        <th class="px-6 py-4">Date de Demande</th>
                        <th class="px-6 py-4 text-center">Statut</th>
                        <th class="px-6 py-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php if (empty($demandes)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-500">
                                Aucune demande effectuée pour le moment.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($demandes as $demande): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-4">
                                        <div class="p-2 bg-indigo-50 rounded-lg text-indigo-500">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-bold text-gray-900"><?= htmlspecialchars($demande['libelle'] ?? 'Document #' . $demande['id_type_doc']) ?></div>
                                            <?php if (!empty($demande['motif'])): ?>
                                                <div class="text-xs text-gray-500 italic"><?= htmlspecialchars($demande['motif']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-gray-600">
                                    <?= date('d M Y', strtotime($demande['date_demande'])) ?>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex justify-center">
                                        <?php
                                        $statut = $demande['statut_demande'];
                                        if ($statut === 'Prêt'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                                <span class="w-2 h-2 bg-emerald-500 rounded-full mr-2"></span> Prêt
                                            </span>
                                        <?php elseif ($statut === 'En traitement' || $statut === 'En cours'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">
                                                <span class="w-2 h-2 bg-orange-500 rounded-full mr-2"></span> En Traitement
                                            </span>
                                        <?php elseif ($statut === 'Terminée'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-200 text-slate-600">
                                                <span class="w-2 h-2 bg-slate-400 rounded-full mr-2"></span> Retirée
                                            </span>
                                        <?php elseif ($statut === 'Rejeté'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                                <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span> Rejetée
                                            </span>
                                        <?php else: // En attente ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-200 text-slate-600">
                                                <span class="w-2 h-2 bg-slate-400 rounded-full mr-2"></span> En Attente
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <?php if ($statut === 'Prêt'): ?>
                                        <a href="telechargement.php?id=<?= $demande['id_demande']; ?>"
                                           class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-bold hover:bg-emerald-100 transition-all border border-emerald-200">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                                            Télécharger
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-300">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <div class="bg-indigo-50 border border-indigo-200 rounded-ifri p-5 flex items-start gap-4 text-indigo-700" data-purpose="info-alert">
            <div class="mt-0.5">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                    <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                </svg>
            </div>
            <p class="text-sm font-medium">
                Le délai moyen de traitement est de 48h ouvrables. Vous recevrez une notification dès qu'un document est disponible.
            </p>
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
