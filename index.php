<?php
session_start();

// Auto-rename .test_claude to .claude (one-time operation)
$testClaude = __DIR__ . '/.test_claude';
$claude     = __DIR__ . '/.claude';
if (is_dir($testClaude) && !is_dir($claude) && !file_exists($claude)) {
    @rename($testClaude, $claude);
}

$error   = $_SESSION['error']   ?? null;
$success = $_SESSION['success'] ?? null;

unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connexion — IFRI Portail</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #f0f2f7;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow-x: hidden;
    }

    /* ── Halos colorés de fond ── */
    body::before {
      content: '';
      position: fixed;
      top: -120px; left: -120px;
      width: 520px; height: 520px;
      background: radial-gradient(circle, rgba(59,130,246,0.13) 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }
    body::after {
      content: '';
      position: fixed;
      bottom: -100px; right: -80px;
      width: 480px; height: 480px;
      background: radial-gradient(circle, rgba(74,222,128,0.12) 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }

    /* ── Carte principale ── */
    .card {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 900px;
      background: #ffffff;
      border-radius: 18px;
      box-shadow: 0 8px 40px rgba(30,64,175,0.10), 0 2px 8px rgba(0,0,0,0.06);
      display: flex;
      overflow: hidden;
      min-height: 460px;
    }

    /* ── Panneau bleu gauche ── */
    .blue-panel {
      background: linear-gradient(170deg, #1a3faa 0%, #1e4db7 55%, #1a5276 100%);
      width: 46%;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 36px;
      color: #fff;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .blue-panel::before {
      content: '';
      position: absolute;
      bottom: -60px; right: -60px;
      width: 200px; height: 200px;
      background: rgba(255,255,255,0.04);
      border-radius: 50%;
    }
    .blue-panel::after {
      content: '';
      position: absolute;
      top: -40px; left: -40px;
      width: 150px; height: 150px;
      background: rgba(255,255,255,0.04);
      border-radius: 50%;
    }

    /* ── Icône terminal ── */
    .terminal-icon {
      width: 68px; height: 68px;
      border: 2.5px solid rgba(255,255,255,0.55);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 26px;
      position: relative;
      z-index: 1;
    }

    /* ── Inputs ── */
    .input-wrap {
      position: relative;
    }
    .input-wrap svg {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      pointer-events: none;
    }
    .input-wrap input {
      width: 100%;
      padding: 13px 14px 13px 42px;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      font-size: 0.9rem;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: #1e293b;
      background: #fff;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .input-wrap.has-toggle input {
      padding-right: 96px;
    }
    .input-wrap input:focus {
      border-color: #1a3faa;
      box-shadow: 0 0 0 3px rgba(26,63,170,0.10);
    }
    .input-wrap input::placeholder { color: #cbd5e1; }
    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: none;
      color: #475569;
      font-size: 0.78rem;
      font-weight: 700;
      cursor: pointer;
      padding: 8px 10px;
      border-radius: 8px;
    }
    .password-toggle:hover {
      color: #1a3faa;
    }

    /* ── Bouton principal ── */
    .btn-primary {
      width: 100%;
      padding: 14px;
      background: #1a3faa;
      color: #fff;
      font-weight: 700;
      font-size: 0.95rem;
      font-family: 'Plus Jakarta Sans', sans-serif;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background .2s, transform .1s;
    }
    .btn-primary:hover { background: #1535a0; }
    .btn-primary:active { transform: scale(.98); }

    /* ── Boutons secondaires ── */
    .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 18px;
      border: 1.5px solid rgba(255,255,255,0.35);
      border-radius: 8px;
      font-size: 0.78rem;
      font-weight: 600;
      color: #fff;
      background: rgba(255,255,255,0.08);
      cursor: pointer;
      transition: background .2s;
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .btn-secondary:hover { background: rgba(255,255,255,0.16); }

    /* ── Alertes ── */
    .alert-error {
      background: #fef2f2; border: 1px solid #fecaca;
      color: #dc2626; border-radius: 8px;
      padding: 10px 14px; font-size: 0.85rem; font-weight: 500;
    }
    .alert-success {
      background: #f0fdf4; border: 1px solid #bbf7d0;
      color: #16a34a; border-radius: 8px;
      padding: 10px 14px; font-size: 0.85rem; font-weight: 500;
    }

    /* ── Checkbox custom ── */
    input[type="checkbox"] { accent-color: #1a3faa; }

    /* ── Wrapper logo ── */
    .logo-box {
      width: 80px; height: 80px;
      background: #fff;
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 2px 12px rgba(30,64,175,0.10);
      overflow: hidden;
      margin: 0 auto 10px;
    }
    .logo-box img { width: 62px; height: 62px; object-fit: contain; }
  </style>
</head>
<body>

  <div style="position:relative;z-index:1;text-align:center;margin-bottom:22px;">
    <div class="logo-box">
      <img src="./images/IFRI.png" alt="Logo IFRI" />
    </div>
    <h1 style="font-size:1.35rem;font-weight:800;color:#1a3faa;margin:0 0 3px;">IFRI Portail</h1>
    <p style="font-size:0.8rem;color:#64748b;margin:0;">Institut de Formation et de Recherche en Informatique</p>
  </div>

  <div class="card">

    <div class="blue-panel">
      <div class="terminal-icon">
        <svg width="34" height="34" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="2" y="5" width="30" height="24" rx="3" stroke="white" stroke-width="2"/>
          <polyline points="8,14 14,19 8,24" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
          <line x1="17" y1="24" x2="26" y2="24" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
      </div>

      <h2 style="font-size:1.6rem;font-weight:800;margin:0 0 14px;line-height:1.25;position:relative;z-index:1;">
        Excellence en<br>Informatique
      </h2>
      <p style="font-size:0.82rem;line-height:1.65;opacity:.85;margin:0 0 30px;position:relative;z-index:1;">
        Espace de gestion et de suivi des documents administratifs. Connectez-vous pour accéder aux services de l'institut.
      </p>

      <div style="display:flex;gap:12px;position:relative;z-index:1;">
        <button class="btn-secondary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Gestion Centrale
        </button>
        <button class="btn-secondary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Espace Sécurisé
        </button>
      </div>
    </div>

    <div style="flex:1;padding:46px 40px;display:flex;flex-direction:column;justify-content:center;">

      <?php if ($error): ?>
        <div class="alert-error" style="margin-bottom:20px;">
          <span>⚠ <?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert-success" style="margin-bottom:20px;">
          <span>✓ <?= htmlspecialchars($success) ?></span>
        </div>
      <?php endif; ?>

      <div id="content-login">
        <h2 style="font-size:1.45rem;font-weight:800;color:#0f172a;margin:0 0 6px;">Portail d'Authentification</h2>
        <p style="font-size:0.85rem;color:#64748b;margin:0 0 26px;">Entrez vos accès pour vous connecter à la plateforme.</p>

        <form method="POST" action="auth_process.php" novalidate>
          <input type="hidden" name="action" value="login" />

          <div style="margin-bottom:18px;">
            <label style="display:block;font-size:0.82rem;font-weight:600;color:#374151;margin-bottom:7px;">
              Identifiant / Matricule / Email Admin
            </label>
            <div class="input-wrap">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
              <input type="text" name="matricule" placeholder="Ex: 10235023 ou admin@ifri-docs.univ.fr" autocomplete="username" required />
            </div>
          </div>

          <div style="margin-bottom:18px;">
            <label style="display:block;font-size:0.82rem;font-weight:600;color:#374151;margin-bottom:7px;">
              Mot de passe
            </label>
            <div class="input-wrap has-toggle">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              <input id="login-password" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required />
              <button type="button" class="password-toggle" data-target="login-password">Voir</button>
            </div>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <label style="display:flex;align-items:center;gap:7px;font-size:0.8rem;color:#475569;cursor:pointer;">
              <input type="checkbox" name="remember" style="width:14px;height:14px;" />
              Se souvenir de moi
            </label>
            <a href="#" style="font-size:0.8rem;color:#1a3faa;font-weight:600;text-decoration:none;">Mot de passe oublié ?</a>
          </div>

          <button type="submit" class="btn-primary">
            Se connecter
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
              <polyline points="10 17 15 12 10 7"/>
              <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
          </button>
        </form>
      </div>

    </div>
  </div>

  <footer style="position:relative;z-index:1;text-align:center;margin-top:22px;font-size:0.75rem;color:#94a3b8;">
    <p style="margin:0 0 6px;">© 2026 IFRI - Institut de Formation et de Recherche en Informatique</p>
    <p style="margin:0;">
      <a href="#" style="color:#94a3b8;text-decoration:none;">Support technique</a>
      <span style="margin:0 10px;">|</span>
      <a href="#" style="color:#94a3b8;text-decoration:none;">Politique de confidentialité</a>
    </p>
  </footer>

  <script>
    function togglePasswordVisibility(event) {
      var button = event.currentTarget;
      var targetId = button.getAttribute('data-target');
      var input = document.getElementById(targetId);
      if (!input) return;

      if (input.type === 'password') {
        input.type = 'text';
        button.textContent = 'Masquer';
      } else {
        input.type = 'password';
        button.textContent = 'Voir';
      }
    }

    document.querySelectorAll('.password-toggle').forEach(function(button) {
      button.addEventListener('click', togglePasswordVisibility);
    });
  </script>
</body>
</html>