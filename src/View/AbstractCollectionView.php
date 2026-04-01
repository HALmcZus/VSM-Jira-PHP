<?php

namespace App\View;

use App\Model\AbstractIssueCollection;
use App\Model\Config;

/**
 * AbstractCollectionView
 *
 * Formate et expose au front les données communes à toute collection d'issues
 * (métriques, timelines, VSM).
 */
abstract class AbstractCollectionView
{
    const REFINEMENT_ICON = '🧠';
    const SPRINT_ICON     = '⚙️';
    const DONE_ICON       = '✅';
    const OTHER_ICON      = '❓';

    protected Config $config;
    protected AbstractIssueCollection $collection;

    public function __construct(AbstractIssueCollection $collection)
    {
        $this->config     = new Config();
        $this->collection = $collection;
    }

    // ==================== Formatage statuts ====================

    public function normalizeStatusName(string $statusName): string
    {
        return $this->getCategoryStatusIcon($statusName) . ' ' . $statusName;
    }

    public function getCategoryStatusIcon(string $statusName): string
    {
        $workflow = $this->config->getJiraWorkflow();

        return match (true) {
            in_array($statusName, $workflow['refinement_statuses'] ?? [], true) => self::REFINEMENT_ICON,
            in_array($statusName, $workflow['sprint_statuses'] ?? [], true)     => self::SPRINT_ICON,
            in_array($statusName, $workflow['done_statuses'] ?? [], true)       => self::DONE_ICON,
            default                                                             => self::OTHER_ICON,
        };
    }

    // ==================== Issues ====================

    public function getIssues(): array
    {
        return $this->collection->getIssues();
    }
    public function getIssuesCount(): int
    {
        return $this->collection->getIssuesCount();
    }

    // ==================== Métriques ====================

    public function getTotalLeadTime(): float
    {
        return $this->collection->getTotalLeadTime();
    }
    public function getAverageLeadTime(): float
    {
        return $this->collection->getAverageLeadTime();
    }
    public function getTotalCycleTime(): float
    {
        return $this->collection->getTotalCycleTime();
    }
    public function getAverageCycleTime(): float
    {
        return $this->collection->getAverageCycleTime();
    }
    public function getTotalTimeSpentInRefinement(): float
    {
        return $this->collection->getTotalTimeSpentInRefinement();
    }
    public function getAverageTimeSpentInRefinement(): float
    {
        return $this->collection->getAverageTimeSpentInRefinement();
    }
    public function getTotalTimeSpentInSprint(): float
    {
        return $this->collection->getTotalTimeSpentInSprint();
    }
    public function getAverageTimeSpentInSprint(): float
    {
        return $this->collection->getAverageTimeSpentInSprint();
    }
    public function getTotalTimeSpentInOther(): float
    {
        return $this->collection->getTotalTimeSpentInOther();
    }
    public function getAverageTimeSpentInOther(): float
    {
        return $this->collection->getAverageTimeSpentInOther();
    }

    // ==================== Timeline ====================

    public function getTimelineByStatus(): array
    {
        return $this->collection->getTimelineByStatus();
    }
    public function getAverageTimeByStatus(): array
    {
        return $this->collection->getAverageTimeByStatus();
    }
    public function getAggregatedWaitingTimes(): array
    {
        return $this->collection->getAggregatedWaitingTimes();
    }

    // ==================== VSM ====================

    /**
     * Retourne les étapes du Value Stream Map prêtes à être affichées.
     *
     * @return array<int, array{key: string, label: string, average_days: float, category: string}>
     */
    public function getVsmSteps(): array
    {
        $timeline            = $this->collection->getTimelineByStatus();
        $averageTimeByStatus = $this->collection->getAverageTimeByStatus();

        $steps = [];
        foreach ($timeline['workflowStatuses'] as $statusName => $metrics) {
            $steps[] = [
                'key'          => $statusName,
                'label'        => $this->normalizeStatusName($statusName),
                'average_days' => $averageTimeByStatus[$statusName] ?? '?',
                'category'     => $this->getStatusCategory($statusName),
            ];
        }

        return $steps;
    }

    /**
     * @return string 'refinement'|'sprint'|'done'|'other'
     */
    private function getStatusCategory(string $statusName): string
    {
        $workflow = $this->config->getJiraWorkflow();

        return match (true) {
            in_array($statusName, $workflow['refinement_statuses'] ?? [], true) => 'refinement',
            in_array($statusName, $workflow['sprint_statuses'] ?? [], true)     => 'sprint',
            in_array($statusName, $workflow['done_statuses'] ?? [], true)       => 'done',
            default                                                             => 'other',
        };
    }
}
