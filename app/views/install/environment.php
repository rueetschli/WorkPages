<?php
/**
 * Installer Step 1: Environment check.
 * Variables: $checks (array), $allOk (bool)
 */
?>
<h2 style="margin-bottom: 1rem;">Schritt 1: Umgebungspruefung</h2>

<ul class="check-list">
    <?php foreach ($checks as $check): ?>
    <li>
        <span><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></span>
        <span>
            <?php if ($check['ok']): ?>
                <span class="check-ok"><?= htmlspecialchars($check['value'], ENT_QUOTES, 'UTF-8') ?> &#10003;</span>
            <?php else: ?>
                <span class="check-fail"><?= htmlspecialchars($check['value'], ENT_QUOTES, 'UTF-8') ?> &#10007;</span>
            <?php endif; ?>
        </span>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($allOk): ?>
    <div class="alert alert-success">Alle Voraussetzungen erfuellt.</div>
    <div class="text-center mt-1">
        <a href="?r=install&amp;step=database" class="btn btn-primary">Weiter: Datenbank konfigurieren</a>
    </div>
<?php else: ?>
    <div class="alert alert-error">
        Einige Voraussetzungen sind nicht erfuellt. Bitte beheben Sie die Probleme und laden Sie die Seite neu.
    </div>
    <div class="text-center mt-1">
        <a href="?r=install&amp;step=environment" class="btn btn-secondary">Erneut pruefen</a>
    </div>
<?php endif; ?>
