<?php
    /** @var \App\View\VersionView $view */
    $this->view = $view ?? null;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>VSM - Jira</title>
</head>
<body>
    <h1>Value Stream Mapping - Jira</h1>
    <form method="POST" action="/vsm">
        <label>FixVersion ID :</label>
        <input type="text" name="fixVersionId" required placeholder="Indiquer l'ID de la version Jira à utiliser (fixVersion)">
        <button type="submit">OK</button>
    </form>
    <?= $this->view ? $this->view->getVersionName() : 'Renseigner l\' ID de la fixVersion à afficher'; ?>
</body>
</html>
