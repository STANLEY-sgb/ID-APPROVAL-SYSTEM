<?php
use Mengo\IdApproval\Security\Sanitizer;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= Sanitizer::escape($pageTitle ?? 'Mengo Hospital ID Approval & Printing System') ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --mengo-red: #990000;
      --mengo-red-dark: #7a0000;
      --mengo-blue-dark: #0f172a;
      --mengo-blue-navy: #0f2942;
      --mengo-accent: #c59b27;
      --mengo-green: #059669;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body.auth-page-body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: linear-gradient(135deg, #0b1329 0%, #0f223d 40%, #17375e 75%, #0b1329 100%);
      background-size: 300% 300%;
      animation: gradientBg 18s ease infinite;
      position: relative;
      overflow-x: hidden;
      color: #1e293b;
    }

    @keyframes gradientBg {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    /* Abstract background technology & medical cross patterns */
    .bg-pattern {
      position: absolute;
      inset: 0;
      pointer-events: none;
      z-index: 1;
      overflow: hidden;
      opacity: 0.25;
    }

    .bg-circle {
      position: absolute;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(153, 0, 0, 0.35) 0%, rgba(15, 23, 42, 0) 70%);
      animation: floatShape 14s infinite ease-in-out alternate;
    }

    .bg-circle-1 {
      width: 500px;
      height: 500px;
      top: -150px;
      right: -100px;
      animation-delay: 0s;
    }

    .bg-circle-2 {
      width: 600px;
      height: 600px;
      bottom: -200px;
      left: -150px;
      background: radial-gradient(circle, rgba(14, 165, 233, 0.25) 0%, rgba(15, 23, 42, 0) 70%);
      animation-delay: -5s;
    }

    .bg-medical-grid {
      position: absolute;
      inset: 0;
      background-image: 
        radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
        radial-gradient(rgba(153, 0, 0, 0.1) 1px, transparent 1px);
      background-size: 32px 32px;
      background-position: 0 0, 16px 16px;
    }

    @keyframes floatShape {
      0% { transform: translate(0, 0) scale(1); }
      50% { transform: translate(30px, -20px) scale(1.05); }
      100% { transform: translate(-20px, 30px) scale(0.95); }
    }

    @media (prefers-reduced-motion: reduce) {
      body.auth-page-body, .bg-circle, .auth-card {
        animation: none !important;
        transition: none !important;
      }
    }

    .auth-container {
      position: relative;
      z-index: 10;
      width: 100%;
      max-width: 440px;
    }

    .auth-card {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.15);
      overflow: hidden;
      animation: cardEntrance 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes cardEntrance {
      from {
        opacity: 0;
        transform: translateY(24px) scale(0.98);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .auth-card-top-accent {
      height: 5px;
      background: linear-gradient(90deg, var(--mengo-red) 0%, #d97706 50%, var(--mengo-red) 100%);
    }

    .auth-header {
      padding: 32px 32px 16px;
      text-align: center;
      background: #ffffff;
    }

    .logo-wrapper {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #ffffff;
      padding: 12px 18px;
      border-radius: 14px;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06), 0 0 0 1px rgba(0, 0, 0, 0.04);
      margin-bottom: 16px;
    }

    .logo-wrapper img {
      max-height: 54px;
      width: auto;
      object-fit: contain;
    }

    .auth-system-title {
      font-size: 19px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.3px;
      line-height: 1.25;
      margin-bottom: 4px;
    }

    .auth-system-subtitle {
      font-size: 12px;
      font-weight: 700;
      color: var(--mengo-red);
      text-transform: uppercase;
      letter-spacing: 0.8px;
    }

    .auth-body {
      padding: 24px 32px 32px;
    }

    .auth-divider {
      position: relative;
      text-align: center;
      margin: 16px 0 24px;
    }

    .auth-divider::before {
      content: "";
      position: absolute;
      left: 0;
      top: 50%;
      width: 100%;
      height: 1px;
      background: #e2e8f0;
    }

    .auth-divider span {
      position: relative;
      background: #ffffff;
      padding: 0 12px;
      font-size: 12px;
      font-weight: 600;
      color: #64748b;
    }

    .auth-footer {
      padding: 16px 32px;
      background: #f8fafc;
      border-top: 1px solid #f1f5f9;
      text-align: center;
      font-size: 11.5px;
      color: #64748b;
      font-weight: 500;
    }

    .auth-footer i {
      color: var(--mengo-red);
      margin-right: 4px;
    }

    @media (max-width: 480px) {
      .auth-header {
        padding: 24px 20px 12px;
      }
      .auth-body {
        padding: 16px 20px 24px;
      }
      .auth-footer {
        padding: 12px 20px;
      }
    }
  </style>
</head>
<body class="auth-page-body">

  <!-- Background Decorative Medical Tech Patterns -->
  <div class="bg-pattern">
    <div class="bg-circle bg-circle-1"></div>
    <div class="bg-circle bg-circle-2"></div>
    <div class="bg-medical-grid"></div>
  </div>

  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-card-top-accent"></div>

      <div class="auth-header">
        <div class="logo-wrapper">
          <img src="/assets/images/mengo-logo.png" alt="Mengo Hospital Logo" onerror="this.onerror=null; this.src='/assets/images/logo.png';">
        </div>
        <div class="auth-system-title">MENGO HOSPITAL</div>
        <div class="auth-system-subtitle">HR ID APPROVAL & PRINTING SYSTEM</div>
      </div>

      <div class="auth-body">
        <!-- Flash Messages -->
        <?php if (!empty($flashes)): ?>
          <div class="flash-container" style="margin-bottom: 20px;">
            <?php foreach ($flashes as $flash): ?>
              <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success') ?>" style="border-radius: 8px; font-size: 13px; padding: 10px 14px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid <?= $flash['type'] === 'error' ? 'fa-circle-exclamation' : ($flash['type'] === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-check') ?>"></i>
                <span><?= Sanitizer::escape($flash['message']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
      </div>

      <div class="auth-footer">
        <i class="fa-solid fa-shield-halved"></i> Secure Hospital Administrative Portal &bull; EAT (UTC+3)
      </div>
    </div>
  </div>

</body>
</html>
