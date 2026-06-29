<?php
session_start();

// Sécurité : vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../ifri_gestion_docs.php';

// Récupération des données POST
$id_demande = isset($_POST['id_demande']) ? intval($_POST['id_demande']) : 0;
$action_type = isset($_POST['action_type']) ? trim($_POST['action_type']) : '';

// Redirection si données invalides
if ($id_demande <= 0 || !in_array($action_type, ['traiter', 'terminer', 'rejeter'])) {
    header('Location: dashboard_admin.php');
    exit;
}

// Définition des statuts
$nouveau_statut = match($action_type) {
    'traiter' => 'En cours',
    'terminer' => 'Prêt',
    'rejeter' => 'Rejeté',
    default => null
};

$success = false;
$demande = null;

try {
    $stmt = $pdo->prepare("
        SELECT d.*, e.nom, e.prenom, e.matricule, e.email, t.libelle
        FROM demandes d
        JOIN etudiants e ON d.id_etudiant = e.id_etudiant
        JOIN types_documents t ON d.id_type_doc = t.id_type
        WHERE d.id_demande = ?
    ");
    $stmt->execute([$id_demande]);
    $demande = $stmt->fetch();

    if ($demande) {
        $update = $pdo->prepare("UPDATE demandes SET statut_demande = ? WHERE id_demande = ?");
        $update->execute([$nouveau_statut, $id_demande]);
        $success = true;
    }
} catch (PDOException $e) {
    $success = false;
}

$matricule = htmlspecialchars($demande['matricule'] ?? '');
$doc_type = htmlspecialchars($demande['libelle'] ?? 'Document');
$email = htmlspecialchars($demande['email'] ?? '');

// Initiales
$init_p = mb_strtoupper(mb_substr($demande['prenom'] ?? '', 0, 1, 'UTF-8'), 'UTF-8');
$init_n = mb_strtoupper(mb_substr($demande['nom'] ?? '', 0, 1, 'UTF-8'), 'UTF-8');
$initiales = (!empty($init_p) || !empty($init_n)) ? $init_p . $init_n : "ET";

// Labels
$action_label = match($action_type) {
    'traiter' => 'Mise en traitement',
    'terminer' => 'Finalisation',
    'rejeter' => 'Rejet',
    default => 'Traitement'
};

$action_verbe = match($action_type) {
    'traiter' => 'prise en charge',
    'terminer' => 'finalisation',
    'rejeter' => 'rejet',
    default => 'traitement'
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Traitement Demande #<?= $id_demande; ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        /* Carte principale */
        .result-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.5);
            width: 100%;
            max-width: 640px;
            overflow: hidden;
            animation: card-enter 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transform-origin: center;
        }

        @keyframes card-enter {
            0% { opacity: 0; transform: scale(0.92) translateY(30px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* Barre d'accent */
        .accent-bar {
            height: 4px;
            background: linear-gradient(90deg, #004A99, #006e0c, #004A99);
            background-size: 200% 100%;
            animation: shimmer 2s ease-in-out infinite;
        }
        @keyframes shimmer {
            0%, 100% { background-position: 200% 0; }
            50% { background-position: -200% 0; }
        }

        /* Checkmark animé */
        .checkmark-circle {
            width: 72px; height: 72px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #006e0c, #00a815);
            box-shadow: 0 8px 24px rgba(0, 110, 12, 0.3);
            animation: pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s both;
        }
        .checkmark-circle svg {
            width: 36px; height: 36px;
            stroke-dasharray: 50;
            stroke-dashoffset: 50;
            animation: draw 0.5s ease-out 0.7s forwards;
        }
        @keyframes pop {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }
        @keyframes draw {
            to { stroke-dashoffset: 0; }
        }

        /* Timeline des étapes */
        .timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 0 8px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 40px;
            right: 40px;
            height: 3px;
            background: #e2e8f0;
            z-index: 0;
            border-radius: 2px;
        }
        .timeline-progress {
            position: absolute;
            top: 20px;
            left: 40px;
            height: 3px;
            background: linear-gradient(90deg, #004A99, #006e0c);
            z-index: 1;
            border-radius: 2px;
            transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .timeline-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            z-index: 2;
            position: relative;
        }
        .timeline-dot {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 3px solid #e2e8f0;
            background: white;
        }
        .timeline-step.active .timeline-dot {
            border-color: #004A99;
            background: #004A99;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 74, 153, 0.3);
        }
        .timeline-step.completed .timeline-dot {
            border-color: #006e0c;
            background: #006e0c;
            box-shadow: 0 4px 12px rgba(0, 110, 12, 0.3);
        }
        .timeline-step.rejected .timeline-dot {
            border-color: #dc2626;
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        .timeline-step .step-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            transition: color 0.3s;
        }
        .timeline-step.active .step-label { color: #004A99; }
        .timeline-step.completed .step-label { color: #006e0c; }

        /* Statut du changement */
        .status-change {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 14px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        /* Animation de fondu pour les éléments */
        .fade-up {
            opacity: 0;
            transform: translateY(12px);
            animation: fadeUp 0.5s ease-out forwards;
        }
        .fade-up:nth-child(1) { animation-delay: 0.1s; }
        .fade-up:nth-child(2) { animation-delay: 0.2s; }
        .fade-up:nth-child(3) { animation-delay: 0.35s; }
        .fade-up:nth-child(4) { animation-delay: 0.5s; }
        .fade-up:nth-child(5) { animation-delay: 0.65s; }
        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }

        /* Particules décoratives */
        .particle {
            position: absolute;
            width: 6px; height: 6px;
            border-radius: 50%;
            pointer-events: none;
            animation: float 4s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); opacity: 0.6; }
            50% { transform: translateY(-20px) scale(1.5); opacity: 0; }
        }

        /* Pulse sur le rejet */
        .pulse-red {
            animation: pulseRed 2s ease-in-out infinite;
        }
        @keyframes pulseRed {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.3); }
            50% { box-shadow: 0 0 0 12px rgba(220, 38, 38, 0); }
        }

        .btn-primary {
            background: #004A99;
            color: white;
            padding: 12px 28px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover {
            background: #00387a;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(0, 74, 153, 0.25);
        }
        .btn-secondary {
            background: white;
            color: #475569;
            padding: 12px 28px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 14px;
            border: 1.5px solid #e2e8f0;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
    </style>
</head>
<body>

    <div class="result-card">
        <!-- Barre d'accent animée -->
        <div class="accent-bar"></div>

        <?php if ($success && $demande): ?>

            <!-- ===== TRAITEMENT RÉUSSI ===== -->
            <?php if ($action_type === 'rejeter'): ?>
                <!-- Version rejet -->
                <div class="p-10 text-center">
                    <div class="fade-up flex justify-center">
                        <div class="checkmark-circle pulse-red" style="background: linear-gradient(135deg, #dc2626, #ef4444); box-shadow: 0 8px 24px rgba(220,38,38,0.3);">
                            <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>

                    <div class="fade-up mt-6">
                        <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Demande rejetée</h1>
                        <p class="text-gray-500 mt-2 text-sm">Le statut a été mis à jour avec succès.</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Version succès -->
                <div class="p-10 text-center">
                    <div class="fade-up flex justify-center">
                        <div class="checkmark-circle">
                            <svg class="text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                <path d="M4.5 12.75l6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>

                    <div class="fade-up mt-6">
                        <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Demande #<?= $id_demande; ?></h1>
                        <p class="text-gray-500 mt-2 text-sm">La <?= $action_verbe; ?> de la demande a été effectuée avec succès.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Chronologie des étapes -->
            <div class="px-10 pb-4 fade-up">
                <div class="timeline">
                    <div class="timeline-progress" style="width: <?= $action_type === 'traiter' ? '50%' : ($action_type === 'terminer' ? '100%' : '100%'); ?>"></div>

                    <div class="timeline-step <?= in_array($action_type, ['traiter', 'terminer']) ? 'completed' : 'active'; ?>">
                        <div class="timeline-dot">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M4.5 12.75l6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <span class="step-label">Reçue</span>
                    </div>

                    <div class="timeline-step <?= $action_type === 'traiter' ? 'active' : ($action_type === 'terminer' ? 'completed' : ''); ?>">
                        <div class="timeline-dot">
                            <?php if ($action_type === 'traiter'): ?>
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M4.5 12.75l6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php elseif ($action_type === 'terminer'): ?>
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M4.5 12.75l6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php else: ?>
                                <span class="text-white text-sm font-bold">⚡</span>
                            <?php endif; ?>
                        </div>
                        <span class="step-label">Traitement</span>
                    </div>

                    <div class="timeline-step <?= $action_type === 'terminer' ? 'completed' : ($action_type === 'rejeter' ? 'rejected' : ''); ?>">
                        <div class="timeline-dot">
                            <?php if ($action_type === 'terminer'): ?>
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path d="M4.5 12.75l6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php else: ?>
                                <span class="text-white text-sm font-bold"><?= $action_type === 'rejeter' ? '✕' : '○'; ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="step-label"><?= $action_type === 'rejeter' ? 'Rejeté' : 'Prêt'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Détails de l'étudiant et du document -->
            <div class="px-10 pb-4">
                <div class="bg-gray-50/70 rounded-2xl p-6 border border-gray-100 fade-up">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-[#004A99] to-[#00387a] text-white flex items-center justify-center font-bold text-lg shadow-md shrink-0">
                            <?= $initiales; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-gray-900 truncate"><?= htmlspecialchars(($demande['prenom'] ?? '') . ' ' . ($demande['nom'] ?? '')); ?></h3>
                            <p class="text-sm text-gray-500 truncate"><?= $matricule; ?> · <?= $email; ?></p>
                        </div>
                        <div class="hidden sm:block text-right">
                            <p class="text-xs text-gray-400">Document</p>
                            <p class="text-sm font-semibold text-gray-800"><?= $doc_type; ?></p>
                        </div>
                    </div>

                    <div class="mt-5 pt-5 border-t border-gray-200">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                            <div>
                                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Demande</p>
                                <p class="text-sm font-bold text-gray-800 mt-1">#<?= $id_demande; ?></p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Date</p>
                                <p class="text-sm font-bold text-gray-800 mt-1"><?= date('d/m', strtotime($demande['date_demande'] ?? 'now')); ?></p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Statut</p>
                                <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-bold
                                    <?= $action_type === 'rejeter' ? 'bg-red-100 text-red-700' : ($action_type === 'terminer' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'); ?>">
                                    <?= $nouveau_statut; ?>
                                </span>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Action</p>
                                <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-bold
                                    <?= $action_type === 'rejeter' ? 'bg-red-50 text-red-600' : ($action_type === 'terminer' ? 'bg-green-50 text-green-600' : 'bg-blue-50 text-blue-600'); ?>">
                                    <?= $action_label; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Boutons de navigation -->
            <div class="px-10 pb-10 pt-2 flex flex-col sm:flex-row gap-3 fade-up">
                <a href="mes_demandes_admin.php" class="btn-primary flex-1 justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Voir toutes les demandes
                </a>
                <a href="dashboard_admin.php" class="btn-secondary flex-1 justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Tableau de bord
                </a>
            </div>

        <?php else: ?>
            <!-- ===== ERREUR ===== -->
            <div class="p-10 text-center">
                <div class="flex justify-center fade-up">
                    <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-xl font-bold text-gray-900 mt-6 fade-up">Erreur de traitement</h1>
                <p class="text-sm text-gray-500 mt-2 fade-up">La demande #<?= $id_demande; ?> est introuvable ou a déjà été traitée.</p>
                <div class="mt-8 flex justify-center gap-3 fade-up">
                    <a href="mes_demandes_admin.php" class="btn-primary">Voir les demandes</a>
                    <a href="dashboard_admin.php" class="btn-secondary">Tableau de bord</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Particules décoratives -->
        <?php if ($success): ?>
            <div class="particle" style="top: 10%; left: 5%; background: #004A99; animation-delay: 0.5s;"></div>
            <div class="particle" style="top: 20%; right: 8%; background: #006e0c; animation-delay: 1s;"></div>
            <div class="particle" style="bottom: 30%; left: 10%; background: #004A99; animation-delay: 1.5s;"></div>
            <div class="particle" style="bottom: 15%; right: 5%; background: #006e0c; animation-delay: 2s;"></div>
        <?php endif; ?>
    </div>

</body>
</html>
