<?php
/**
 * Installer locked view - installation already completed.
 */
?>
<div class="alert alert-info">
    WorkPages ist bereits installiert. Der Installer wurde gesperrt.
</div>
<p style="margin-top: 1rem;">
    Um den Installer erneut auszufuehren, setzen Sie <code>INSTALL_UNLOCK</code> auf <code>true</code>
    in <code>config/config.php</code> und loeschen Sie die Datei <code>storage/install.lock</code>.
</p>
<div class="mt-2 text-center">
    <a href="<?= htmlspecialchars(rtrim(($GLOBALS['config']['BASE_URL'] ?? ''), '/'), ENT_QUOTES, 'UTF-8') ?>/?r=login" class="btn btn-primary">Zum Login</a>
</div>
