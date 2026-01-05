<?php
    /** @var \App\View\VersionView $view */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>VSM - Jira</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <h1>Value Stream Mapping - Version Jira</h1>
    <form method="POST" action="/vsm">
        <label>FixVersion ID :</label>
        <input type="text" name="fixVersionId" required placeholder="Indiquer l'ID de la version Jira à utiliser (fixVersion)">
        <button type="submit">OK</button>
    </form>
    
    <?php if ($view): ?>
        <div class="card">
            <h2><?= $view->getVersionName(); ?> (<?= 'ID ' . $view->getVersionId(); ?>)</h2>
            
            <p style="margin-top: 12px; color: var(--muted);">
                <?= $view->getVersionDescription(); ?>
            </p>

            <div class="meta">
                <span>📅 Date de démarrage : <?= $view->getVersionStartDate() ?? '—'; ?></span>
                <span>🚀 Date de release : <?= $view->getVersionReleaseDate() ?? '—'; ?></span>
                
                <?php if ($view->isVersionOverdue()): ?>
                    <span class="badge red">🕗Deadline dépassée</span>
                <?php endif; ?>

                <?php if ($view->isVersionReleased()): ?>
                    <span class="badge green">Status : Terminé</span>
                <?php else: ?>
                    <span class="badge orange">Status : En cours</span>
                <?php endif; ?>

                <span>Cycle Time Moyen : <strong><?= $view->getAverageCycleTime(); ?> jours</strong></span>
                <span>Cycle Time Total : <strong><?= $view->getTotalCycleTime(); ?> jours</strong></span>
            </div>
        </div>

        <div class="card">
            <h2><?= $view->getIssuesCount(); ?> tickets rattachés à cette version :</h2>
            <table>
                <thead>
                    <tr>
                        <th>Priorité</th>
                        <th>Type</th>
                        <th>Key</th>
                        <th>Titre</th>
                        <th>Status</th>
                        <th>Date de création</th>
                        <th>Date de résolution</th>
                        <th>Cycle time (jours)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($view->getIssues() as $issue): ?>
                        <tr>
                            <td><?= $issue['priority'] ?? '—'; ?></td>
                            <td><img src="<?= $issue['issuetype']['iconUrl'] ?? '' ?>"/><?= $issue['issuetype']['name'] ?? '—';?></td>
                            <td><strong><?= $issue['key']; ?></strong></td>
                            <td><?= htmlspecialchars($issue['summary']); ?></td>
                            <!-- <td>< ?= $issue['assignee'] ?? '—'; ?></td> -->
                            <td class="<?= $issue['statusCategoryColor'] ?>"><?= $issue['statusName'] ?? '—'; ?></td>
                            <td><?= $issue['created'] ?? '—'; ?></td>
                            <td><?= $issue['resolutiondate'] ?? '—'; ?></td>
                            <td><?= $issue['cycleTime'] ?? '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

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
