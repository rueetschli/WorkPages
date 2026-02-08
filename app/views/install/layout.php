<?php
/**
 * Installer layout - standalone (no app layout, no sidebar).
 * Variables: $step (string), plus step-specific data via extract().
 */
$appName = 'WorkPages';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f6fa;
            color: #2d3436;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 2rem 1rem;
        }
        .installer {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            max-width: 640px;
            width: 100%;
            padding: 2.5rem;
        }
        .installer-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .installer-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .installer-header p {
            color: #636e72;
            font-size: 0.95rem;
        }
        .steps {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .step-indicator {
            background: #dfe6e9;
            color: #636e72;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .step-indicator.active {
            background: #0984e3;
            color: #fff;
        }
        .step-indicator.done {
            background: #00b894;
            color: #fff;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #ffeef0;
            color: #d63031;
            border: 1px solid #fab1a0;
        }
        .alert-success {
            background: #e8f8f5;
            color: #00b894;
            border: 1px solid #55efc4;
        }
        .alert-info {
            background: #eef5ff;
            color: #0984e3;
            border: 1px solid #74b9ff;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }
        .form-input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #dfe6e9;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #0984e3;
            box-shadow: 0 0 0 3px rgba(9,132,227,0.1);
        }
        .form-hint {
            font-size: 0.8rem;
            color: #636e72;
            margin-top: 0.2rem;
        }
        .btn {
            display: inline-block;
            padding: 0.65rem 1.5rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s;
        }
        .btn-primary {
            background: #0984e3;
            color: #fff;
        }
        .btn-primary:hover {
            background: #0773c5;
        }
        .btn-block {
            display: block;
            width: 100%;
            text-align: center;
        }
        .btn-secondary {
            background: #dfe6e9;
            color: #2d3436;
        }
        .btn-secondary:hover {
            background: #b2bec3;
        }
        .check-list {
            list-style: none;
            margin-bottom: 1.5rem;
        }
        .check-list li {
            padding: 0.5rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }
        .check-ok { color: #00b894; font-weight: 700; }
        .check-fail { color: #d63031; font-weight: 700; }
        .btn-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        .text-center { text-align: center; }
        .mt-1 { margin-top: 1rem; }
        .mt-2 { margin-top: 2rem; }
    </style>
</head>
<body>

<div class="installer">
    <div class="installer-header">
        <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> Installer</h1>
        <p>Installation und Einrichtung</p>
    </div>

    <?php
    $steps = ['environment' => 'Umgebung', 'database' => 'Datenbank', 'schema' => 'Schema', 'admin' => 'Admin', 'done' => 'Fertig'];
    $stepKeys = array_keys($steps);
    $currentIdx = array_search($step, $stepKeys, true);
    if ($step !== 'locked'):
    ?>
    <div class="steps">
        <?php foreach ($steps as $key => $label):
            $idx = array_search($key, $stepKeys, true);
            $cls = 'step-indicator';
            if ($idx < $currentIdx) $cls .= ' done';
            elseif ($idx === $currentIdx) $cls .= ' active';
        ?>
            <span class="<?= $cls ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    $viewFile = APP_DIR . '/views/install/' . $step . '.php';
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        echo '<p>Installer-Schritt nicht gefunden.</p>';
    }
    ?>
</div>

</body>
</html>
