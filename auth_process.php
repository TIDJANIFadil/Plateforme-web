<?php
/**
 * auth_process.php
 * Backend d'authentification IFRI Portail connectée à MySQL
 */

session_start();
require_once 'ifri_gestion_docs.php'; // On inclut la connexion à la BDD

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function redirect_with_message(string $type, string $message, string $tab = 'login'): void {
    $_SESSION[$type]      = $message;
    $_SESSION['active_tab'] = $tab;
    header('Location: index.php');
    exit;
}

$action = isset($_POST['action']) ? sanitize($_POST['action']) : '';

if ($action === 'login') {
    $login_input = sanitize($_POST['matricule'] ?? '');
    $password    = trim($_POST['password']   ?? '');

    if (empty($login_input) || empty($password)) {
        redirect_with_message('error', 'Veuillez remplir tous les champs.', 'login');
    }

    // --- VÉRIFICATION ADMIN VIA LA BASE DE DONNÉES ---
    try {
        $stmt_admin = $pdo->prepare("SELECT * FROM administrateurs WHERE email = ?");
        $stmt_admin->execute([$login_input]);
        $admin_user = $stmt_admin->fetch();

        if ($admin_user && password_verify($password, $admin_user['mot_de_passe'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $admin_user['id_admin'];
            $_SESSION['admin_email']     = $admin_user['email'];
            $_SESSION['admin_nom']       = $admin_user['prenom'] . ' ' . $admin_user['nom'];
            $_SESSION['role']            = 'admin';
            $_SESSION['login_time']      = time();

            header('Location: espace_administrateur/dashboard_admin.php');
            exit;
        }
    } catch (PDOException $e) {
        // Fallback silencieux si la table administrateurs n'existe pas
    }
    // --- FIN VÉRIFICATION ADMIN ---

    try {
        // --- CAS 1 : L'UTILISATEUR ENTRE UN EMAIL ---
        if (filter_var($login_input, FILTER_VALIDATE_EMAIL)) {
            // Premier essai sur les étudiants
            $stmt = $pdo->prepare('SELECT * FROM etudiants WHERE email = ?');
            $stmt->execute([$login_input]);
            $user = $stmt->fetch();
        } 
        // --- CAS 2 : L'UTILISATEUR ENTRE UN MATRICULE ---
        else {
            $stmt = $pdo->prepare('SELECT * FROM etudiants WHERE matricule = ?');
            $stmt->execute([$login_input]);
            $user = $stmt->fetch();
        }

        // Vérification de l'utilisateur et du mot de passe haché
        if ($user && password_verify($password, $user['mot_de_passe'])) {
            
            if ($user['statut_compte'] !== 'valide') {
                redirect_with_message('error', 'Votre compte est suspendu ou en attente de validation.', 'login');
            }

            session_regenerate_id(true);
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id']        = $user['id_etudiant'];
            $_SESSION['nom']            = $user['nom'];
            $_SESSION['prenom']         = $user['prenom'];
            $_SESSION['role']           = 'etudiant';
            $_SESSION['user_matricule'] = $user['matricule'];
            $_SESSION['user_name']      = $user['prenom'] . ' ' . strtoupper($user['nom']);
            $_SESSION['user_role']      = 'etudiant';
            $_SESSION['login_time']     = time();

            // Notification admin : connexion étudiant
            try {
                $stmt_admin_notif = $pdo->prepare("INSERT INTO admin_notifications (titre, message, type_notif, id_etudiant, cree_at) VALUES (?, ?, 'connexion', ?, NOW())");
                $stmt_admin_notif->execute([
                    "🔵 Connexion — " . $user['prenom'] . " " . $user['nom'],
                    $user['prenom'] . " " . $user['nom'] . " (" . $user['matricule'] . ") s'est connecté à son espace.",
                    $user['id_etudiant']
                ]);
            } catch (PDOException $e) {
                // Silence — la notification ne doit pas bloquer la connexion
            }

            header('Location: dashboard.php');
            exit;
        } else {
            redirect_with_message('error', 'Identifiants incorrects ou compte inexistant.', 'login');
        }

    } catch (PDOException $e) {
        redirect_with_message('error', 'Une erreur technique est survenue. Code: 01', 'login');
    }
}

if ($action === 'signup') {
    $matricule = sanitize($_POST['matricule'] ?? '');
    $nom       = sanitize($_POST['nom']       ?? '');
    $prenom    = sanitize($_POST['prenom']    ?? '');
    $email     = sanitize($_POST['email']     ?? '');
    $password  = $_POST['password']           ?? '';

    if (empty($matricule) || empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        redirect_with_message('error', 'Tous les champs sont obligatoires.', 'signup');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !str_ends_with($email, '@ifri.bj')) {
        redirect_with_message('error', "L'adresse email doit être une adresse @ifri.bj valide.", 'signup');
    }

    if (strlen($password) < 6) {
        redirect_with_message('error', 'Le mot de passe doit contenir au moins 6 caractères.', 'signup');
    }

    try {
        $check = $pdo->prepare('SELECT id_etudiant FROM etudiants WHERE matricule = ? OR email = ?');
        $check->execute([$matricule, $email]);
        
        if ($check->rowCount() > 0) {
            redirect_with_message('error', 'Ce matricule ou cet email est déjà inscrit.', 'signup');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            "INSERT INTO etudiants (matricule, nom, prenom, email, mot_de_passe, statut_compte) VALUES (?, ?, ?, ?, ?, 'valide')"
        );
        $stmt->execute([$matricule, strtoupper($nom), $prenom, $email, $hash]);

        $new_user_id = $pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id']        = $new_user_id;
        $_SESSION['nom']            = $nom;
        $_SESSION['prenom']         = $prenom;
        $_SESSION['role']           = 'etudiant';
        $_SESSION['user_matricule'] = $matricule;
        $_SESSION['user_name']      = $prenom . ' ' . strtoupper($nom);
        $_SESSION['user_role']      = 'etudiant';
        $_SESSION['login_time']     = time();
        
        $_SESSION['success'] = "Compte créé avec succès ! Bienvenue sur votre espace étudiant.";

        header('Location: dashboard.php');
        exit;

    } catch (PDOException $e) {
        redirect_with_message('error', 'Une erreur technique est survenue lors de l\'inscription.', 'signup');
    }
}