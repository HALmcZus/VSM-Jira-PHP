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
            <h2>
                <a href="<?= $view->getVersionUrl(); ?>" target="_blank">
                    <?= $view->getVersionName(); ?> (<?= 'ID ' . $view->getVersionId(); ?>)
                </a>
            </h2>
            
            <p style="margin-top: 12px; color: var(--muted);">
                <?= $view->getVersionDescription(); ?>
            </p>

            <div class="meta">
                <!-- Infos version -->
                <div class="meta-line meta-line-info">
                    <span>📅 Date de démarrage : <?= $view->getVersionStartDate() ?? '—'; ?></span>
                    <span>🎯 Date cible de livraison : <?= $view->getVersionReleaseDate() ?? '—'; ?></span>

                    <?php if ($view->isVersionOverdue()): ?>
                        <span class="badge red">🕗Deadline dépassée</span>
                    <?php endif; ?>

                    <?php if ($view->isVersionReleased()): ?>
                        <span class="badge green">Status : Terminée 🚀</span>
                    <?php else: ?>
                        <span class="badge orange">Status : En cours</span>
                    <?php endif; ?>
                </div>
                
                <!-- Métriques -->
                <div class="meta-line meta-line-metrics">
                    <!-- Lead Time -->
                    <div class="metric">
                        <span class="metric-title">📦 <b>Lead Time</b> <em>(jours calendaires Création -> Terminé)</em></span>
                        <span>Total : <strong><?= $view->getTotalLeadTime(); ?> jours</strong></span>
                        <span>Moyen : <strong><?= $view->getAverageLeadTime(); ?> jours /ticket</strong></span>
                    </div>

                    <!-- Cycle Time -->
                    <div class="metric">
                        <span class="metric-title">🛠️ <b>Cycle Time</b> <em>(jours ouvrés En cours -> Terminé)</em></span>
                        <span>Total : <strong><?= $view->getTotalCycleTime(); ?> jours</strong></span>
                        <span>Moyen : <strong><?= $view->getAverageCycleTime(); ?> jours /ticket</strong></span>
                    </div>

                    <!-- Time Breakdown -->
                    <div class="metric">
                        <span class="metric-title">🧮 <b>Temps par phase</b></span>
                        <span>Affinage : <strong><?= $view->getTotalTimeSpentInRefinement(); ?> jours.</strong> <em>Moyenne : <strong><?= $view->getAverageTimeSpentInRefinement(); ?> jours /ticket.</strong></em></span>
                        <span>Réal + Qualif : <strong><?= $view->getTotalTimeSpentInSprint(); ?> jours.</strong> <em>Moyenne : <strong><?= $view->getAverageTimeSpentInSprint(); ?> jours /ticket.</strong></em></span>
                        <span>Autre : <strong><?= $view->getTotalTimeSpentInOther(); ?> jours.</strong> <em>Moyenne : <strong><?= $view->getAverageTimeSpentInOther(); ?> jours /ticket.</strong></em></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="timeline-grid">
            <!-- Timeline par Status -->
            <div class="card">
                <details>
                    <summary>🧭 Timeline globale par status (Release)</summary>
                    <ul>
                        <?php foreach ($view->getTimelineByStatus() as $status => $days): ?>
                            <li><?= htmlspecialchars($status); ?> : <strong><?= round($days, 2); ?> jours</strong></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
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
                        <tr>
                            <td colspan="11">
                                <!-- Détails temps par status -->
                                <details>
                                    <summary>Détails du temps passé par status</summary>
                                    <ul>
                                        <?php foreach ($issue->getTimeByStatus() as $statusName => $timeSpent): ?>
                                            <li><?= htmlspecialchars($statusName); ?> : <?= $timeSpent; ?> jours</li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                                <!-- Détails temps par catégorie de status -->
                                <details>
                                    <summary>Détails du temps passé par étapes du cycle de vie du ticket</summary>
                                    <ul>
                                        <li>🧠 Affinage : <strong><?= $issue->getTimeSpentInRefinement(); ?> jours</strong></li>
                                        <li>⚙️ Réal + Qualif : <strong><?= $issue->getTimeSpentInSprint(); ?> jours</strong></li>
                                        <li>❓ Autre : <strong><?= $issue->getTimeSpentInOther(); ?> jours</strong></li>
                                    </ul>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>       

    <?php endif; ?>

</body>
</html>
