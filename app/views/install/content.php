<?php
/**
 * Installer Step 5: Content / Templates (AP31).
 * Variables: $error, $success, $languages, $templates
 */
$esc = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$templateCount = count($templates);
?>
<h2 style="margin-bottom: 1rem;">Schritt 5: Startinhalte</h2>

<p style="color: #636e72; margin-bottom: 1.25rem;">
    WorkPages enthält praxisnahe Vorlagen für Scrum, Meetings, Organisation und Dokumentation.
    Sie können diese jetzt importieren oder später über die Admin-Oberfläche nachladen.
</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= $esc($error) ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= $esc($success) ?></div>
    <div class="text-center mt-1">
        <a href="?r=install&amp;step=done" class="btn btn-primary">Installation abschliessen</a>
    </div>
<?php else: ?>
    <form method="post" action="?r=install&amp;step=content">
        <?= Security::csrfField() ?>

        <div class="form-group">
            <label style="display:flex; align-items:center; gap:0.5rem; padding:0.75rem; border:1px solid #dfe6e9; border-radius:6px; cursor:pointer; margin-bottom:0.5rem;">
                <input type="radio" name="import_choice" value="none">
                <span>
                    <strong>Keine Startinhalte</strong>
                    <span style="display:block; font-size:0.85rem; color:#636e72;">Leere Installation ohne Vorlagen</span>
                </span>
            </label>

            <label style="display:flex; align-items:center; gap:0.5rem; padding:0.75rem; border:2px solid #0984e3; border-radius:6px; cursor:pointer; margin-bottom:0.5rem; background:#eef5ff;">
                <input type="radio" name="import_choice" value="templates" checked>
                <span>
                    <strong>Templates importieren</strong> <span style="color:#0984e3; font-size:0.85rem;">(empfohlen)</span>
                    <span style="display:block; font-size:0.85rem; color:#636e72;">
                        <?= (int) $templateCount ?> Vorlagen für Scrum, Meetings, Organisation und Dokumentation
                    </span>
                </span>
            </label>
        </div>

        <div class="form-group" id="language-select">
            <label for="language">Sprache der Vorlagen</label>
            <select name="language" id="language" class="form-input">
                <?php foreach (array_keys($languages) as $lang): ?>
                    <option value="<?= $esc($lang) ?>" <?= $lang === 'de' ? 'selected' : '' ?>>
                        <?= $lang === 'de' ? 'Deutsch' : ($lang === 'en' ? 'English' : strtoupper($lang)) ?>
                        (<?= count($languages[$lang]) ?> Vorlagen)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($languages)): ?>
        <div style="margin-bottom:1.25rem;">
            <details>
                <summary style="cursor:pointer; font-size:0.9rem; color:#636e72;">Enthaltene Vorlagen anzeigen</summary>
                <ul style="margin-top:0.5rem; font-size:0.85rem; color:#636e72;">
                    <?php foreach ($templates as $tpl): ?>
                        <li style="padding:0.15rem 0;">
                            <strong><?= $esc(ucfirst($tpl['category'])) ?></strong> – <?= $esc($tpl['title']) ?>
                            <span style="opacity:0.6;">(<?= $esc(strtoupper($tpl['language'])) ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </div>
        <?php endif; ?>

        <div class="btn-group">
            <a href="?r=install&amp;step=admin" class="btn btn-secondary">Zurueck</a>
            <button type="submit" class="btn btn-primary">Weiter</button>
        </div>
    </form>

    <script>
    (function() {
        var radios = document.querySelectorAll('input[name="import_choice"]');
        var langSelect = document.getElementById('language-select');
        function toggle() {
            var checked = document.querySelector('input[name="import_choice"]:checked');
            if (langSelect) {
                langSelect.style.display = (checked && checked.value === 'templates') ? '' : 'none';
            }
        }
        for (var i = 0; i < radios.length; i++) {
            radios[i].addEventListener('change', toggle);
        }
        toggle();
    })();
    </script>
<?php endif; ?>
