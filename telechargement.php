<?php
session_start();

// Si l'étudiant n'est pas connecté, on le laisse quand même voir la page
// mais on lui demande de se connecter d'abord
$est_connecte = isset($_SESSION['user_id']);

require_once __DIR__ . '/ifri_gestion_docs.php';

$id_demande = isset($_GET['id']) ? intval($_GET['id']) : 0;
$demande = null;
$error_msg = '';
$download_path = '';
$code_verified = false;

// Si un code est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_demande > 0) {
    $code_saisi = isset($_POST['code_secret']) ? strtoupper(trim($_POST['code_secret'])) : '';

    try {
        $stmt = $pdo->prepare("
            SELECT d.*, t.libelle as doc_type, e.nom, e.prenom, e.matricule
            FROM demandes d
            JOIN types_documents t ON d.id_type_doc = t.id_type
            JOIN etudiants e ON d.id_etudiant = e.id_etudiant
            WHERE d.id_demande = ? AND d.statut_demande = 'Prêt' AND d.code_secret IS NOT NULL
        ");
        $stmt->execute([$id_demande]);
        $demande = $stmt->fetch();

        if (!$demande) {
            $error_msg = 'Demande introuvable ou pas encore prête.';
        } elseif ($code_saisi !== $demande['code_secret']) {
            $error_msg = 'Code secret incorrect. Veuillez réessayer.';
        } elseif (empty($demande['document_pdf']) || !file_exists(__DIR__ . '/' . $demande['document_pdf'])) {
            $error_msg = 'Le fichier PDF n\'est pas disponible. Contactez l\'administration.';
        } else {
            $code_verified = true;
            $download_path = $demande['document_pdf'];

            // Enregistrer la date de téléchargement
            $update = $pdo->prepare("UPDATE demandes SET date_retrait = NOW(), statut_demande = 'Terminée' WHERE id_demande = ?");
            $update->execute([$id_demande]);
        }
    } catch (PDOException $e) {
        $error_msg = 'Erreur lors de la vérification.';
    }
} elseif ($id_demande > 0) {
    // Pré-remplir les infos de la demande
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, t.libelle as doc_type, e.nom, e.prenom, e.matricule
            FROM demandes d
            JOIN types_documents t ON d.id_type_doc = t.id_type
            JOIN etudiants e ON d.id_etudiant = e.id_etudiant
            WHERE d.id_demande = ? AND d.statut_demande = 'Prêt' AND d.code_secret IS NOT NULL
        ");
        $stmt->execute([$id_demande]);
        $demande = $stmt->fetch();
    } catch (PDOException $e) {
        $demande = null;
    }
}

$student_nom = $demande ? htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) : '';
$doc_type = $demande ? htmlspecialchars($demande['doc_type']) : '';
$matricule = $demande ? htmlspecialchars($demande['matricule'] ?? '') : '';
$code_cache = $demande ? htmlspecialchars($demande['code_secret']) : '';
$date_creation = $demande ? date('d/m/Y', strtotime($demande['date_demande'])) : '';

// Initiales
$init_p = $demande ? mb_strtoupper(mb_substr($demande['prenom'] ?? '', 0, 1, 'UTF-8'), 'UTF-8') : '';
$init_n = $demande ? mb_strtoupper(mb_substr($demande['nom'] ?? '', 0, 1, 'UTF-8'), 'UTF-8') : '';
$initiales = (!empty($init_p) || !empty($init_n)) ? $init_p . $init_n : "?";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>IFRI Portail - Téléchargement sécurisé</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 50%, #dce8f5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .main-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.2), 0 0 0 1px rgba(255,255,255,0.5);
            width: 100%;
            max-width: 900px;
            overflow: hidden;
            animation: cardEnter 0.6s cubic-bezier(0.16,1,0.3,1) forwards;
            transform-origin: center;
        }
        @keyframes cardEnter {
            0% { opacity: 0; transform: scale(0.92) translateY(30px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

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

        /* === CODE INPUT === */
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .code-input {
            width: 44px;
            height: 56px;
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            color: #1e293b;
            transition: all 0.15s ease;
            outline: none;
            text-transform: uppercase;
        }
        .code-input:focus {
            border-color: #004A99;
            box-shadow: 0 0 0 3px rgba(0,74,153,0.15);
            transform: translateY(-2px);
        }
        .code-input.filled {
            border-color: #006e0c;
            background: #f0fdf4;
        }
        .code-input.error {
            border-color: #ef4444;
            background: #fef2f2;
            animation: shake 0.4s ease-in-out;
        }
        .code-input.success {
            border-color: #006e0c;
            background: #006e0c;
            color: white;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(8px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(5px); }
        }

        /* === SUCCESS STATE === */
        .success-overlay {
            animation: successIn 0.6s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes successIn {
            0% { opacity: 0; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }

        .check-circle-big {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #006e0c, #00a815);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 12px 32px rgba(0,110,12,0.3);
            animation: pop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.3s both;
        }
        .check-circle-big svg {
            width: 44px; height: 44px;
            stroke-dasharray: 60;
            stroke-dashoffset: 60;
            animation: draw 0.5s ease-out 0.6s forwards;
        }
        @keyframes pop {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }
        @keyframes draw {
            to { stroke-dashoffset: 0; }
        }

        .download-btn {
            background: linear-gradient(135deg, #004A99, #00387a);
            color: white;
            padding: 16px 40px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.25s;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 24px rgba(0,74,153,0.3);
            border: none;
            cursor: pointer;
        }
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(0,74,153,0.4);
        }

        .fade-up {
            opacity: 0; transform: translateY(16px);
            animation: fadeUp 0.5s ease-out forwards;
        }
        .fade-up-d1 { animation-delay: 0.1s; }
        .fade-up-d2 { animation-delay: 0.2s; }
        .fade-up-d3 { animation-delay: 0.35s; }
        .fade-up-d4 { animation-delay: 0.5s; }
        .fade-up-d5 { animation-delay: 0.65s; }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

        .glow-dot {
            animation: dotPulse 2s ease-in-out infinite;
        }
        @keyframes dotPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        .btn-secondary {
            background: white; color: #475569;
            padding: 12px 24px; border-radius: 14px;
            font-weight: 600; font-size: 14px;
            border: 1.5px solid #e2e8f0; transition: all 0.2s;
        }
        .btn-secondary:hover { background: #f8fafc; border-color: #cbd5e1; }

        .confetti-piece {
            position: fixed; width: 10px; height: 10px;
            top: -10px; z-index: 999;
            animation: confettiFall 3s ease-in forwards;
            pointer-events: none;
        }
        @keyframes confettiFall {
            0% { transform: translateY(0) rotate(0deg) scale(1); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg) scale(0.3); opacity: 0; }
        }

        .btn-primary {
            background: #004A99; color: white; padding: 14px 32px; border-radius: 14px;
            font-weight: 700; font-size: 14px; transition: all 0.2s;
            border: none; cursor: pointer;
        }
        .btn-primary:hover { background: #00387a; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(0,74,153,0.25); }

        .doc-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 20px;
        }

@media (max-width: 768px) {
            .main-card { max-width: 480px; }
            .split-layout { flex-direction: column; }
        }
    </style>
</head>
<body>

    <div class="main-card">
        <div class="accent-bar"></div>

        <?php if (!$demande && !$error_msg): ?>
            <!-- ===== PAGE D'ACCUEIL - DEMANDE MANQUANTE ===== -->
            <div class="p-8 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-100 flex items-center justify-center">
                    <span class="material-symbols-outlined text-amber-600 text-3xl">search</span>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-900 mb-2">Document introuvable</h1>
                <p class="text-slate-500 text-sm mb-6">Le document que vous recherchez n'est pas disponible ou le lien est invalide.</p>
                <a href="mes_demandes.php" class="btn-primary">Mes demandes</a>
            </div>

        <?php elseif ($code_verified): ?>
            <!-- ===== SUCCÈS - CODE VALIDE ===== -->
            <div class="p-8 text-center success-overlay">
                <div class="check-circle-big">
                    <svg class="text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                        <path d="M4.5 12.75l6 6 9-13.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <div class="mt-6">
                    <h1 class="text-2xl font-extrabold text-slate-900">Téléchargement autorisé</h1>
                    <p class="text-slate-500 text-sm mt-2">Code secret vérifié avec succès ! Votre document est prêt.</p>
                </div>

                <div class="mt-8 bg-slate-50 rounded-2xl p-5 border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-[#004A99] to-[#00387a] text-white flex items-center justify-center font-bold text-lg shrink-0">
                            <?= $initiales; ?>
                        </div>
                        <div class="text-left">
                            <p class="font-bold text-slate-900"><?= $student_nom; ?></p>
                            <p class="text-xs text-slate-500"><?= $matricule; ?> · <?= $doc_type; ?></p>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <a href="<?= $download_path; ?>" download id="downloadLink"
                       class="download-btn">
                        <span class="material-symbols-outlined">download</span>
                        Télécharger mon document
                    </a>
                    <p class="text-xs text-slate-400 mt-3">Le fichier sera téléchargé automatiquement</p>
                </div>

                <div class="mt-8 pt-6 border-t border-slate-200">
                    <a href="dashboard.php" class="btn-secondary inline-flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">arrow_back</span>
                        Retour au tableau de bord
                    </a>
                </div>
            </div>

            <!-- Confetti + auto-download -->
            <script>
            (function() {
                const colors = ['#004A99','#006e0c','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6'];
                for (let i = 0; i < 80; i++) {
                    const el = document.createElement('div');
                    el.className = 'confetti-piece';
                    el.style.left = Math.random() * 100 + 'vw';
                    el.style.background = colors[Math.floor(Math.random() * colors.length)];
                    el.style.width = (Math.random() * 8 + 4) + 'px';
                    el.style.height = (Math.random() * 8 + 4) + 'px';
                    el.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
                    el.style.animationDuration = (Math.random() * 2 + 2.5) + 's';
                    el.style.animationDelay = (Math.random() * 2) + 's';
                    document.body.appendChild(el);
                    setTimeout(() => el.remove(), 5000);
                }
                // Auto-download après 1s
                setTimeout(() => {
                    document.getElementById('downloadLink').click();
                }, 1000);
            })();
            </script>

        <?php else: ?>
            <!-- ===== FORMULAIRE DE CODE SECRET + INFOS DOCUMENT ===== -->
            <div class="p-8">
                <!-- Logo & branding -->
                <div class="text-center mb-6 fade-up fade-up-d1">
                    <div class="inline-flex items-center gap-2 mb-3">
                        <img src="images/IFRI.png" alt="IFRI" class="h-10 w-auto">
                    </div>
                    <h1 class="text-xl font-extrabold text-slate-900">Téléchargement sécurisé</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Saisissez votre code secret pour télécharger votre document
                    </p>
                </div>

                <!-- Split Layout: Document Info + Code Form -->
                <div class="split-layout flex gap-6 items-stretch fade-up fade-up-d2">

                    <!-- LEFT: Document Information Card -->
                    <div class="doc-card p-5 flex-1 flex flex-col">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-[#004A99]/10 text-[#004A99] flex items-center justify-center">
                                <span class="material-symbols-outlined text-sm">description</span>
                            </div>
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Document</span>
                        </div>

                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-[#004A99] to-[#00387a] text-white flex items-center justify-center font-bold text-xl shrink-0 shadow-md">
                                <?= $initiales; ?>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900 text-lg"><?= $doc_type; ?></p>
                                <p class="text-sm text-slate-500"><?= $student_nom; ?></p>
                                <p class="text-xs text-slate-400"><?= $matricule; ?></p>
                            </div>
                        </div>

                        <div class="mt-auto space-y-2.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Statut</span>
                                <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block glow-dot"></span>
                                    Prêt
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Date de disponibilité</span>
                                <span class="font-semibold text-slate-700"><?= $date_creation; ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-slate-500">Format</span>
                                <span class="font-semibold text-slate-700 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm text-red-500">picture_as_pdf</span>
                                    PDF
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Code Secret Form -->
                    <div class="flex-1 flex flex-col justify-center">
                        <!-- Message d'erreur -->
                        <?php if ($error_msg): ?>
                            <div class="bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-700 mb-5 flex items-center gap-2" id="errorMessage">
                                <span class="material-symbols-outlined text-sm">error</span>
                                <?= $error_msg; ?>
                            </div>
                        <?php endif; ?>

                        <form id="codeForm" method="POST" action="telechargement.php?id=<?= $id_demande; ?>" autocomplete="off">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider text-center mb-3">
                                Entrez votre code secret à 8 caractères
                            </p>

                            <div class="code-inputs" id="codeInputs">
                                <?php for ($i = 0; $i < 8; $i++): ?>
                                    <input type="text" maxlength="1"
                                        class="code-input"
                                        id="code_<?= $i; ?>"
                                        data-index="<?= $i; ?>"
                                        inputmode="text"
                                        autocomplete="off"
                                        <?= $i === 0 ? 'autofocus' : ''; ?>
                                        oninput="handleInput(this, <?= $i; ?>)"
                                        onkeydown="handleKeydown(event, <?= $i; ?>)"
                                        onpaste="handlePaste(event)" />
                                <?php endfor; ?>
                            </div>

                            <input type="hidden" name="code_secret" id="code_secret_hidden" value="">

                            <p class="text-xs text-slate-400 text-center mt-3">
                                Le code vous a été communiqué par l'administration
                            </p>

                            <button type="submit" id="submitCode" disabled
                                class="w-full mt-6 bg-[#004A99] text-white font-bold py-3.5 rounded-xl text-sm shadow-sm hover:bg-[#00387a] active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined text-sm">lock_open</span>
                                Vérifier et télécharger
                            </button>
                        </form>
                    </div>

                </div>

                <div class="mt-6 pt-5 border-t border-slate-200 text-center fade-up fade-up-d4">
                    <a href="mes_demandes.php" class="text-xs text-slate-400 hover:text-[#004A99] transition-colors">
                        ← Mes demandes
                    </a>
                </div>
            </div>

            <script>
            const inputs = document.querySelectorAll('.code-input');
            const hiddenInput = document.getElementById('code_secret_hidden');
            const submitBtn = document.getElementById('submitCode');
            const form = document.getElementById('codeForm');

            function handleInput(el, idx) {
                const val = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                el.value = val;

                if (val) {
                    el.classList.remove('error');
                    el.classList.add('filled');
                    if (idx < 7) {
                        inputs[idx + 1].focus();
                    }
                } else {
                    el.classList.remove('filled');
                }

                updateHidden();
            }

            function handleKeydown(e, idx) {
                if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                    inputs[idx - 1].focus();
                    inputs[idx - 1].value = '';
                    inputs[idx - 1].classList.remove('filled');
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (!submitBtn.disabled) {
                        form.submit();
                    }
                }
                if (e.key === 'ArrowLeft' && idx > 0) {
                    inputs[idx - 1].focus();
                }
                if (e.key === 'ArrowRight' && idx < 7) {
                    inputs[idx + 1].focus();
                }
            }

            function handlePaste(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const cleaned = paste.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
                for (let i = 0; i < cleaned.length; i++) {
                    inputs[i].value = cleaned[i];
                    inputs[i].classList.add('filled');
                }
                if (cleaned.length > 0) {
                    inputs[Math.min(cleaned.length, 7)].focus();
                }
                updateHidden();
            }

            function updateHidden() {
                let code = '';
                inputs.forEach(inp => code += inp.value);
                hiddenInput.value = code;
                submitBtn.disabled = code.length < 8;
            }

            <?php if ($error_msg): ?>
            inputs.forEach(inp => {
                inp.classList.add('error');
                setTimeout(() => inp.classList.remove('error'), 400);
            });
            <?php endif; ?>
            </script>

        <?php endif; ?>
    </div>


</body>
</html>
