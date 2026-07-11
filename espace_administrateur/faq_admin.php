<?php
session_start();

// Sécurité : Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../index.php');
    exit;
}

// Connexion à la base de données
require_once __DIR__ . '/../ifri_gestion_docs.php';
$pdo = getPDO();

// Compteur notifications admin
$admin_notif_count = 0;
try {
    $admin_notif_count = $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE lue = 0")->fetchColumn();
} catch (PDOException $e) {}

// Avatar header
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

$faq_categories = [
    'Général' => [
        ['q' => 'Qu\'est-ce que IFRI Portail ?', 'r' => 'IFRI Portail est une plateforme de gestion et de suivi des documents administratifs destinée aux étudiants et au personnel de l\'Institut de Formation et de Recherche en Informatique (IFRI).'],
        ['q' => 'Qui peut utiliser cette plateforme ?', 'r' => 'La plateforme est accessible aux étudiants inscrits à l\'IFRI ainsi qu\'au personnel administratif habilité.'],
    ],
    'Comptes & Connexion' => [
        ['q' => 'Comment créer un compte étudiant ?', 'r' => 'Les comptes étudiants sont créés par l\'administration. Vous recevrez un email avec vos identifiants de connexion (matricule et mot de passe temporaire).'],
        ['q' => 'Que faire si j\'ai oublié mon mot de passe ?', 'r' => 'Contactez l\'administration pour réinitialiser votre mot de passe. Un nouveau mot de passe temporaire vous sera envoyé par email.'],
        ['q' => 'Comment modifier mon mot de passe ?', 'r' => 'Une fois connecté, allez dans votre espace "Paramètres" depuis le menu latéral. Vous pourrez y changer votre mot de passe en fournissant l\'ancien et le nouveau.'],
    ],
    'Gestion des demandes' => [
        ['q' => 'Comment soumettre une demande de document ?', 'r' => 'Depuis votre tableau de bord ou la page "Mes Demandes", cliquez sur "Nouvelle demande". Sélectionnez le type de document, joignez les pièces requises et soumettez.'],
        ['q' => 'Quels types de documents puis-je demander ?', 'r' => 'Vous pouvez demander : Attestation d\'inscription, Relevé de notes, Attestation de succès, Duplicata de scolarité, Réclamation, Attestation de main-levée, Supplément au diplôme, Certification de documents, et Attestation d\'admissibilité.'],
        ['q' => 'Comment suivre l\'état de ma demande ?', 'r' => 'Connectez-vous à votre espace et allez dans "Mes Demandes". Vous verrez le statut de chaque demande : En attente, En cours, Prêt, Terminée ou Rejeté.'],
        ['q' => 'Que faire si ma demande est rejetée ?', 'r' => 'Consultez le motif du rejet dans les détails de la demande. Vous pouvez soumettre une nouvelle demande en corrigant les problèmes signalés.'],
    ],
    'Documents & Téléchargement' => [
        ['q' => 'Comment télécharger mon document une fois prêt ?', 'r' => 'Lorsque votre document est marqué "Prêt", vous recevrez un code secret par email. Rendez-vous sur la page de téléchargement, entrez le code et récupérez votre document.'],
        ['q' => 'Le code secret ne fonctionne pas, que faire ?', 'r' => 'Vérifiez que vous utilisez le code le plus récent. Si le problème persiste, contactez l\'administration pour obtenir de l\'aide.'],
        ['q' => 'Quels formats de fichiers sont acceptés pour les pièces jointes ?', 'r' => 'Les formats acceptés sont : PDF, JPG, JPEG, PNG. Chaque fichier ne doit pas dépasser la taille maximale autorisée.'],
    ],
    'Administration' => [
        ['q' => 'Comment inscrire un nouvel étudiant ?', 'r' => 'En tant qu\'administrateur, utilisez le bouton "Inscrire un étudiant" depuis le tableau de bord. Remplissez les informations requises et le système générera un mot de passe temporaire.'],
        ['q' => 'Comment traiter une demande ?', 'r' => 'Depuis la page "Demandes", cliquez sur une demande pour voir les détails et les pièces fournies. Vous pouvez changer le statut, télécharger le document PDF final, ou rejeter la demande avec un motif.'],
        ['q' => 'Comment gérer les types de documents ?', 'r' => 'Dans la page "Paramètres", vous pouvez ajouter ou activer/désactiver les types de documents proposés aux étudiants.'],
        ['q' => 'Puis-je modifier mon profil administrateur ?', 'r' => 'Oui, allez dans la page "Profile" ou "Paramètres" pour modifier vos informations personnelles (nom, prénom, email, téléphone) et votre mot de passe.'],
    ],
    'Sécurité' => [
        ['q' => 'Mes données sont-elles protégées ?', 'r' => 'Oui, toutes les données sont stockées de manière sécurisée et les mots de passe sont cryptés. La plateforme utilise des sessions sécurisées pour protéger vos informations.'],
        ['q' => 'Que faire en cas d\'activité suspecte sur mon compte ?', 'r' => 'Contactez immédiatement l\'administration. Vous pouvez également changer votre mot de passe depuis les paramètres de votre compte.'],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>FAQ — IFRI Portail</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f0f4f8; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
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
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease;
            opacity: 0;
        }
        .faq-answer.open {
            max-height: 300px;
            opacity: 1;
        }
        .faq-question svg {
            transition: transform 0.3s ease;
        }
        .faq-question.open svg {
            transform: rotate(180deg);
        }
        .ifri-blue {
            color: #003d7a;
        }
    </style>
</head>
<body class="flex min-h-screen">

<!-- BARRE LATÉRALE -->
<aside class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full z-40">
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

<!-- CONTENU PRINCIPAL -->
<main class="flex-1 ml-64 overflow-y-auto h-screen">
    <!-- HEADER -->
    <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8">
        <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" style="font-size:18px;">search</span>
            <input type="text" id="searchInput" placeholder="Rechercher dans la FAQ..."
            class="w-64 md:w-80 pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-[#004A99]/20 focus:border-[#004A99] transition-all placeholder:text-slate-400" />
        </div>
        <div class="flex items-center space-x-6">
            <a href="notifications_admin.php" class="relative inline-flex items-center justify-center w-8 h-8 rounded-full <?= $admin_notif_count > 0 ? 'bg-amber-100' : 'hover:bg-gray-100'; ?> transition-all">
                <span class="material-symbols-outlined <?= $admin_notif_count > 0 ? 'text-amber-600' : 'text-gray-500'; ?>" style="font-size:18px;">notifications</span>
                <?php if ($admin_notif_count > 0): ?>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow-lg"><?= min($admin_notif_count, 99); ?></span>
                <?php endif; ?>
            </a>
            <a href="faq_admin.php" class="text-[#003d7a] hover:bg-blue-50 p-2 rounded-full transition-colors">
                <span class="material-symbols-outlined">help</span>
            </a>
            <a href="profile_admin.php" class="flex items-center space-x-3">
                <span class="text-sm font-semibold text-slate-700"><?= $admin_nom_header; ?></span>
                <?php if ($admin_avatar): ?>
                    <img src="<?= $admin_avatar; ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200" alt="Avatar"/>
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-[#003d7a] text-white flex items-center justify-center font-bold text-sm border border-gray-200">ADM</div>
                <?php endif; ?>
            </a>
        </div>
    </header>

    <div class="max-w-4xl mx-auto p-8">
        <!-- En-tête -->
        <div class="mb-10 text-center">
            <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-[#003d7a]" style="font-size:32px;">quiz</span>
            </div>
            <h2 class="text-3xl font-extrabold text-[#003d7a] tracking-tight">Questions fréquentes</h2>
            <p class="text-gray-500 mt-2 max-w-xl mx-auto">Retrouvez les réponses aux questions les plus courantes sur l'utilisation de la plateforme IFRI Portail.</p>
        </div>

        <!-- Catégories FAQ -->
        <div class="space-y-8">
            <?php foreach ($faq_categories as $categorie => $questions): ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-7 py-4 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[#003d7a]">
                                <?= match($categorie) {
                                    'Général' => 'info',
                                    'Comptes & Connexion' => 'lock',
                                    'Gestion des demandes' => 'description',
                                    'Documents & Téléchargement' => 'download',
                                    'Administration' => 'admin_panel_settings',
                                    'Sécurité' => 'security',
                                    default => 'help',
                                }; ?>
                            </span>
                            <h3 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($categorie) ?></h3>
                        </div>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($questions as $faq): ?>
                            <div class="faq-item">
                                <button onclick="toggleFaq(this)" class="faq-question w-full flex items-center justify-between px-7 py-4 text-left hover:bg-gray-50 transition-colors">
                                    <span class="text-sm font-semibold text-gray-800 pr-4"><?= htmlspecialchars($faq['q']) ?></span>
                                    <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <div class="faq-answer px-7">
                                    <p class="text-sm text-gray-600 pb-4 leading-relaxed"><?= htmlspecialchars($faq['r']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Section contact -->
        <div class="mt-10 bg-[#003d7a] rounded-2xl p-8 text-white text-center">
            <span class="material-symbols-outlined text-4xl mb-3 opacity-80">support_agent</span>
            <h3 class="text-xl font-bold mb-2">Vous n'avez pas trouvé votre réponse ?</h3>
            <p class="text-sm opacity-90 mb-5">Contactez l'équipe administrative pour obtenir de l'aide.</p>
            <a href="dashboard_admin.php" class="inline-flex items-center gap-2 px-6 py-3 bg-white text-[#003d7a] rounded-xl font-semibold hover:bg-gray-100 transition-colors">
                <span class="material-symbols-outlined text-sm">mail</span>
                Contacter l'administration
            </a>
        </div>
    </div>
</main>

<script>
    function toggleFaq(btn) {
        var answer = btn.nextElementSibling;
        var isOpen = answer.classList.contains('open');

        // Fermer toutes les autres réponses
        document.querySelectorAll('.faq-answer.open').forEach(function(el) {
            el.classList.remove('open');
            el.previousElementSibling.classList.remove('open');
        });

        if (!isOpen) {
            answer.classList.add('open');
            btn.classList.add('open');
        }
    }

    // ===== RECHERCHE EN TEMPS RÉEL =====
    document.getElementById('searchInput')?.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        var items = document.querySelectorAll('.faq-item');
        items.forEach(function(item) {
            if (q === '') {
                item.style.display = '';
            } else {
                var text = item.textContent.toLowerCase();
                item.style.display = text.indexOf(q) >= 0 ? '' : 'none';
            }
        });
    });
</script>

<script src="../assets/js/app.js"></script>
</body>
</html>
