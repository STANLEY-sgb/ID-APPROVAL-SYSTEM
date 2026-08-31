<?php
/**
 * Mengo Hospital Branding Header Component
 *
 * Parameters (optional):
 * - $variant: 'auth' | 'sidebar' | 'header' | 'compact' (default: 'sidebar')
 * - $showSubtitle: bool (default: true)
 */
$variant = $variant ?? 'sidebar';
$showSubtitle = $showSubtitle ?? true;
?>

<?php if ($variant === 'auth'): ?>
  <div class="mengo-branding mengo-branding-auth" style="text-align: center; margin-bottom: 8px;">
    <div style="display: inline-block; background: #ffffff; padding: 6px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 12px;">
      <img src="/assets/images/logo.png" alt="Mengo Hospital — HR ID Approval System" style="height: 76px; width: auto; max-width: 100%; object-fit: contain; display: block;" onerror="this.onerror=null; this.src='/assets/images/mengo-logo.png';">
    </div>
    <h2 style="font-size: 18px; font-weight: 800; color: #0b1329; letter-spacing: 0.5px; margin: 0 0 2px;">
      MENGO HOSPITAL
    </h2>
    <?php if ($showSubtitle): ?>
      <p style="font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; margin: 0;">
        HR ID Approval &amp; Printing System
      </p>
    <?php endif; ?>
  </div>

<?php elseif ($variant === 'sidebar'): ?>
  <div class="sidebar-header" style="padding: 16px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
    <div class="sidebar-logo-container" style="flex-shrink: 0; background: #ffffff; padding: 4px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.25); border: 1px solid rgba(197, 155, 39, 0.4);">
      <img src="/assets/images/logo.png" alt="Mengo Hospital Logo" style="height: 38px; width: auto; max-width: 38px; object-fit: contain; display: block;" onerror="this.onerror=null; this.src='/assets/images/mengo-logo.png';">
    </div>
    <div class="hospital-title-wrap" style="line-height: 1.2;">
      <h1 style="font-size: 13.5px; font-weight: 800; color: #ffffff; letter-spacing: 0.5px; margin: 0;">MENGO HOSPITAL</h1>
      <span style="font-size: 10.5px; color: #c59b27; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">HR ID Approval System</span>
    </div>
  </div>

<?php else: ?>
  <div class="mengo-branding-inline" style="display: inline-flex; align-items: center; gap: 10px;">
    <div style="background: #ffffff; padding: 3px; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); border: 1px solid rgba(197, 155, 39, 0.3);">
      <img src="/assets/images/logo.png" alt="Mengo Hospital Logo" style="height: 32px; width: auto; object-fit: contain; display: block;" onerror="this.onerror=null; this.src='/assets/images/mengo-logo.png';">
    </div>
    <div style="line-height: 1.2;">
      <div style="font-weight: 800; font-size: 13px; color: #0b1329;">MENGO HOSPITAL</div>
      <div style="font-size: 10.5px; color: #c59b27; text-transform: uppercase; font-weight: 700;">HR ID Approval System</div>
    </div>
  </div>
<?php endif; ?>
