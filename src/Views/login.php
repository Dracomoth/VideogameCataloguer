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

use Vault\Repositories\SystemInfoRepository;
use Vault\Services\View;

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

$versions = SystemInfoRepository::getVersions();
$sysVer   = $systemVersion ?? $versions['system'];
$dbVer    = $databaseVersion ?? $versions['database'];

$title = !empty($pageTitle) ? View::e($pageTitle) . ' | Videogame Vault' : 'Sign In | Videogame Vault';
$stylePath = __DIR__ . '/../../assets/css/style.css';
$styleVer  = file_exists($stylePath) ? (string)filemtime($stylePath) : '1.0';

$favPath = __DIR__ . '/../../favicon.ico';
$favVer  = file_exists($favPath) ? (string)filemtime($favPath) : '1.0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="dark">
  <title><?= $title ?></title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎮</text></svg>">
  <link rel="shortcut icon" href="/favicon.ico?v=<?= $favVer ?>">
  <link rel="icon" type="image/x-icon" href="/favicon.ico?v=<?= $favVer ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png?v=<?= $favVer ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png?v=<?= $favVer ?>">
  <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg?v=<?= $favVer ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png?v=<?= $favVer ?>">
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= $styleVer ?>">
  <style>
    :root {
      color-scheme: dark;
    }
    .password-input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
      width: 100%;
    }
    .password-input-wrapper .login-input {
      padding-right: 44px;
    }
    .password-toggle-btn {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--text-dim);
      cursor: pointer;
      padding: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: var(--radius-sm);
      transition: color var(--transition-fast), background-color var(--transition-fast);
      line-height: 1;
    }
    .password-toggle-btn:hover {
      color: #38bdf8;
      background: rgba(56, 189, 248, 0.08);
    }
    .password-toggle-btn svg {
      width: 18px;
      height: 18px;
      display: block;
      pointer-events: none;
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
      line-height: 1;
      box-shadow: 0 0 16px var(--accent-glow);
      margin-bottom: 12px;
      user-select: none;
    }
    .login-badge .brand-logo-icon,
    .login-badge span {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      line-height: 1;
      transform: translateY(-0.09em);
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
      <div class="login-badge"><span class="brand-logo-icon">🎮</span></div>
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
        <label for="login" class="login-label">Username or Email</label>
        <input 
          type="text" 
          id="login" 
          name="login" 
          class="login-input" 
          placeholder="username or name@domain.com" 
          required 
          autofocus
        >
      </div>

      <div class="login-form-group">
        <label for="password" class="login-label">Password</label>
        <div class="password-input-wrapper">
          <input 
            type="password" 
            id="password" 
            name="password" 
            class="login-input" 
            placeholder="••••••••••••" 
            required
          >
          <button 
            type="button" 
            class="password-toggle-btn" 
            onclick="togglePasswordVisibility('password', this)" 
            aria-label="Show password"
            title="Show password"
          >
            <!-- Minimalist Eye Icon (Password Hidden) -->
            <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="login-btn-submit">
        Sign In &rarr;
      </button>
    </form>
  </div>

  <div class="login-footer-copy">
    Videogame Vault &copy; <?= date('Y') ?> &bull; System v<?= View::e($sysVer) ?>, Database v<?= View::e($dbVer) ?>
  </div>
</div>

<script>
function togglePasswordVisibility(fieldId, btn) {
  const input = document.getElementById(fieldId);
  if (!input) return;
  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
  btn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
  
  if (isPassword) {
    // Slashed eye (Eye Off)
    btn.innerHTML = `
      <svg class="eye-off-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
        <line x1="1" y1="1" x2="23" y2="23"></line>
      </svg>
    `;
  } else {
    // Regular eye (Eye)
    btn.innerHTML = `
      <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <circle cx="12" cy="12" r="3"></circle>
      </svg>
    `;
  }
}
</script>
</body>
</html>
