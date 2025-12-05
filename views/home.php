<?php ob_start(); ?>
<div class="card">
    <h2>Analyse d'une Release (fixVersion)</h2>

    <form method="POST">
        <label for="versionId">ID de la Release (fixVersion) :</label>
        <input type="text" id="versionId" name="versionId" placeholder="Ex : 12345">

        <button type="submit">Tester l'accès Jira</button>
    </form>

    <?php if (!empty($message)): ?>
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>