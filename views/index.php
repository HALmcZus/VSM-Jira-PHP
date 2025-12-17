<?php
    /** @var \App\View\VersionView $view */
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
    
    <?php if ($view): ?>
        <div class="card">
            <h2><?= $view->getVersionName(); ?> (<?= $view->getVersionId(); ?>)</h2>

            <div class="meta">
                <span>📅 Start date: <?= $view->getVersionStartDate() ?? '—'; ?></span>
                <span>🚀 Release date: <?= $view->getVersionReleaseDate() ?? '—'; ?></span>

                <?php if ($view->isReleased()): ?>
                    <span class="badge green">Status : Released</span>
                <?php else: ?>
                    <span class="badge orange">Status : In progress</span>
                <?php endif; ?>
            </div>

            <?php if ($view->getVersionDescription()): ?>
                <p style="margin-top: 12px; color: var(--muted);">
                    <?= $view->getVersionDescription(); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- <div class="card">
            <h2>Tickets in this release</h2>
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Summary</th>
                        <th>Assignee</th>
                        <th>Cycle time (days)</th>
                    </tr>
                </thead>
                <tbody>
                    < ?php foreach ($view->getIssues() as $issue): ?>
                        <tr>
                            <td><strong>< ?= $issue['key']; ?></strong></td>
                            <td>< ?= htmlspecialchars($issue['summary']); ?></td>
                            <td>< ?= $issue['assignee'] ?? '—'; ?></td>
                            <td>< ?= $issue['cycleTime'] ?? '—'; ?></td>
                        </tr>
                    < ?php endforeach; ?>
                </tbody>
            </table>
        </div>
        -->

        <!-- 
        <div class="card">
            <h2>Value Stream Metrics</h2>

            <div class="meta">
                <span>⏱ Average cycle time: <strong>— days</strong></span>
                <span>📦 Tickets: <strong>< ?= count($view->getIssues()); ?></strong></span>
            </div>

            <p style="margin-top: 12px; color: var(--muted);">
                Cycle time is measured from first In Progress to Done.
            </p>
        </div>
         -->

    <?php endif; ?>


</body>
</html>
