<?php
/**
 * src/Views/login.php
 * Standalone Authentication & Sign-In View.
 *
 * Variables provided by AuthController:
 * @var string|null $pageTitle Document title
 * @var string|null $error     Flash error message (if any)
 */

declare(strict_types=1);

use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$title = !empty($pageTitle) ? View::e($pageTitle) . ' | Videogame Vault' : 'Sign In | Videogame Vault';
$stylePath = __DIR__ . '/../../assets/css/style.css';
$styleVer  = file_exists($stylePath) ? (string)filemtime($stylePath) : '1.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark">
  <title><?= $title ?></title>
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= $styleVer ?>">
  <style>
    :root {
      color-scheme: dark;
    }
    body.login-body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: radial-gradient(circle at 50% 30%, #151d30 0%, #0b0f19 80%);
      padding: 20px;
      color-scheme: dark;
    }
    .login-container {
      width: 100%;
      max-width: 440px;
    }
    .login-card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5);
      padding: 36px 32px;
      position: relative;
      overflow: hidden;
    }
    .login-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, #38bdf8, #818cf8, #0284c7);
    }
    .login-brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      margin-bottom: 28px;
    }
    .login-badge {
      width: 52px;
      height: 52px;
      background: rgba(56, 189, 248, 0.12);
      border: 1px solid var(--border-focus);
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      box-shadow: 0 0 16px var(--accent-glow);
      margin-bottom: 12px;
    }
    .login-title {
      font-size: 22px;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.02em;
    }
    .login-subtitle {
      font-size: 13px;
      color: var(--text-muted);
      margin-top: 4px;
    }
    .login-form-group {
      margin-bottom: 20px;
    }
    .login-label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-muted);
      margin-bottom: 8px;
    }
    .login-input {
      width: 100%;
      background: var(--surface-alt);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      color: #fff;
      font-family: inherit;
      font-size: 14px;
      padding: 12px 14px;
      outline: none;
      color-scheme: dark;
      transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    }
    .login-input:focus {
      border-color: var(--border-focus);
      box-shadow: 0 0 0 3px var(--accent-glow);
    }
    /* Browser Autofill Dark Palette Overrides (WebKit, Blink, Gecko) */
    .login-input:-webkit-autofill,
    .login-input:-webkit-autofill:hover,
    .login-input:-webkit-autofill:focus,
    .login-input:-webkit-autofill:active {
      -webkit-box-shadow: 0 0 0 1000px #0c121e inset !important;
      box-shadow: 0 0 0 1000px #0c121e inset !important;
      -webkit-text-fill-color: #f8fafc !important;
      caret-color: #38bdf8 !important;
      border-color: var(--border) !important;
      transition: background-color 50000s ease-in-out 0s;
    }
    .login-input:-webkit-autofill:focus {
      border-color: var(--border-focus) !important;
      -webkit-box-shadow: 0 0 0 1000px #0c121e inset, 0 0 0 3px var(--accent-glow) !important;
      box-shadow: 0 0 0 1000px #0c121e inset, 0 0 0 3px var(--accent-glow) !important;
    }
    .login-input:autofill {
      background-color: #0c121e !important;
      filter: none;
      color: #f8fafc !important;
    }
    .login-btn-submit {
      width: 100%;
      background: linear-gradient(135deg, var(--accent) 0%, #0369a1 100%);
      color: #fff;
      border: 1px solid var(--border-focus);
      border-radius: var(--radius-md);
      padding: 13px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: transform var(--transition-fast), box-shadow var(--transition-fast);
    }
    .login-btn-submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 14px var(--accent-glow);
    }
    .login-btn-submit:active {
      transform: translateY(0);
    }
    .login-alert-error {
      background: var(--danger-surface);
      border: 1px solid var(--danger);
      color: #fca5a5;
      padding: 12px 16px;
      border-radius: var(--radius-md);
      font-size: 13px;
      margin-bottom: 22px;
      line-height: 1.4;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .login-footer-copy {
      text-align: center;
      font-size: 12px;
      color: var(--text-dim);
      margin-top: 20px;
    }
  </style>
</head>
<body class="login-body">
<div class="login-container">
  <div class="login-card">
    <!-- Brand Header -->
    <div class="login-brand">
      <div class="login-badge">🎮</div>
      <h1 class="login-title">Videogame Vault</h1>
      <p class="login-subtitle">Restricted Access & Collection Workbench</p>
    </div>

    <!-- Error Flash Notification -->
    <?php if (!empty($error)): ?>
      <div class="login-alert-error">
        <span>⚠️</span>
        <div><?= View::e($error) ?></div>
      </div>
    <?php endif; ?>

    <!-- Credentials Form -->
    <form method="POST" action="/login" autocomplete="off">
      <div class="login-form-group">
        <label for="email" class="login-label">Email Address</label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          class="login-input" 
          placeholder="name@domain.com" 
          required 
          autofocus
        >
      </div>

      <div class="login-form-group">
        <label for="password" class="login-label">Password</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          class="login-input" 
          placeholder="••••••••••••" 
          required
        >
      </div>

      <button type="submit" class="login-btn-submit">
        Sign In &rarr;
      </button>
    </form>
  </div>

  <div class="login-footer-copy">
    Videogame Vault &copy; <?= date('Y') ?> &bull; Authorized Personnel Only
  </div>
</div>
</body>
</html>
