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
    <div class="card">
    <h1>Value Stream Mapping - Version Jira</h1>
        <form method="POST" action="/vsm">
            <label>FixVersion ID :</label>
            <input type="text" name="fixVersionId" required placeholder="Indiquer l'ID de la version Jira à utiliser (fixVersion)">
            <button type="submit">OK</button>
        </form>
    </div>
    
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

                <!-- Lead Time -->
                <div class="metric">
                    <span class="metric-title">📦 <b>Lead Time</b> <em>(jours calendaires Création -> Terminé)</em></span>
                    <span>Total : <strong><?= $view->getTotalLeadTime(); ?> jours</strong></span>
                    <span>Moyen : <strong><?= $view->getAverageLeadTime(); ?> jours</strong></span>
                </div>
                
                <!-- Cycle Time -->
                <div class="metric">
                    <span class="metric-title">🛠️ <b>Cycle Time</b> <em>(jours ouvrés En cours -> Terminé)</em></span>
                    <span>Total : <strong><?= $view->getTotalCycleTime(); ?> jours</strong></span>
                    <span>Moyen : <strong><?= $view->getAverageCycleTime(); ?> jours</strong></span>
                </div>
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
                        <th>1er passage à En cours</th>
                        <th>Date de résolution (Terminé)</th>
                        <th>Lead time</th>
                        <th>Cycle time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php /** @var \App\Model\Issue $issue */ ?>
                    <?php foreach ($view->getIssues() as $issue): ?>
                        <tr>
                            <td><img src="<?= $issue->getPriorityIcon() ?? '' ?>"/><?= $issue->getPriorityName() ?? '—'; ?></td>
                            <td><img src="<?= $issue->getIssueTypeIcon() ?? '' ?>"/><?= $issue->getIssueTypeName() ?? '—';?></td>
                            <td><strong><?= $issue->getKey(); ?></strong></td>
                            <td><?= htmlspecialchars($issue->getSummary()); ?></td>
                            <td class="<?= $issue->getStatusCategoryColor() ?>"><?= $issue->getStatusName() ?? '—'; ?></td>
                            <td><?= $issue->getCreatedDate() ?? '—'; ?></td>
                            <td><?= $issue->getFirstInProgressDate() ?? '—'; ?></td>
                            <td><?= $issue->getDoneDate() ?? '—'; ?></td>
                            <td><?= $issue->getLeadTime() > 0 ? $issue->getLeadTime() . ' jours' : '—'; ?></td>
                            <td><?= $issue->getCycleTime() > 0 ? $issue->getCycleTime() . ' jours' : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>       

    <?php endif; ?>

</body>
</html>
