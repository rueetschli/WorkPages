<?php
/**
 * Installer Step 5: Done.
 * Variables: $baseUrl
 */
$esc = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<div class="text-center">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">&#10003;</div>
    <h2 style="margin-bottom: 1rem;">Installation abgeschlossen</h2>

    <div class="alert alert-success">
        WorkPages wurde erfolgreich installiert. Der Installer ist jetzt gesperrt.
    </div>

    <p style="margin: 1rem 0; color: #636e72;">
        Sie koennen sich nun mit den erstellten Admin-Zugangsdaten anmelden.
    </p>

    <a href="<?= $esc($baseUrl) ?>/?r=login" class="btn btn-primary">Zum Login</a>
</div>
