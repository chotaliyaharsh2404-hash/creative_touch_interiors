<?php
/**
 * Creative Touch Interiors — Admin Footer Version Component
 * Displays subtle application version indicator in compliance with CTI Design System.
 */
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.2.2');
}
?>
<footer class="admin-footer" style="margin-top: 3.5rem; padding-top: 1.5rem; padding-bottom: 1.5rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; font-size: 0.78rem; color: #64748b;">
    <div style="display: flex; align-items: center; gap: 0.45rem;">
        <span style="font-weight: 600; color: #334155;">Creative Touch Interiors</span>
        <span style="color: #cbd5e1;">&bull;</span>
        <span style="font-weight: 700; color: #0057FF; font-family: monospace; letter-spacing: 0.02em;">v<?php echo htmlspecialchars(APP_VERSION); ?></span>
    </div>
    <div style="font-size: 0.74rem; color: #94a3b8;">
        Architecture &bull; Spatial Design &bull; Executive Suite
    </div>
</footer>
