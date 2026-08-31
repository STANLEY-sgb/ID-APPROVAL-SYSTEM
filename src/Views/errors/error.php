<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $code ?? '403' ?> — Mengo Hospital ID System</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #0b1329 0%, #1e293b 100%); }
    .error-card { background: white; border-radius: 12px; padding: 48px 40px; text-align: center; max-width: 520px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
  </style>
</head>
<body>
  <div class="error-card">
    <div style="font-size: 64px; font-weight: 900; color: <?= isset($code) && $code == 403 ? '#ef4444' : ($code == 404 ? '#f59e0b' : '#6366f1') ?>; line-height: 1;">
      <?= $code ?? '403' ?>
    </div>
    <div style="font-size: 20px; font-weight: 700; color: #1e293b; margin-top: 12px;">
      <?= htmlspecialchars($title ?? 'Access Denied') ?>
    </div>
    <div style="font-size: 14px; color: #64748b; margin-top: 8px; line-height: 1.5;">
      <?= htmlspecialchars($message ?? 'You do not have permission to access this page.') ?>
    </div>
    <div style="margin-top: 28px; display: flex; justify-content: center; gap: 12px;">
      <a href="javascript:history.back()" class="btn btn-outline">
        <i class="fa-solid fa-arrow-left"></i> Go Back
      </a>
      <a href="/" class="btn btn-primary">
        <i class="fa-solid fa-house"></i> Home
      </a>
    </div>
    <div style="margin-top: 20px; font-size: 11px; color: #94a3b8;">
      Mengo Hospital ID Card Management System
    </div>
  </div>
</body>
</html>
