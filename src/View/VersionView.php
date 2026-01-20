<?php

namespace App\View;

use App\Model\Config;
use App\Model\Version;

/**
 * Formate les données du Back, et les expose au Front
 */
class VersionView
{
    const REFINEMENT_ICON = '🧠';
    const SPRINT_ICON = '⚙️';
    const DONE_ICON = '✅';
    const OTHER_ICON = '❓';

    private Config $config;
    private Version $version;

    /**
     * __construct
     *
     * @param  mixed $version
     * @return void
     */
    public function __construct(Version $version)
    {
        $this->config = new Config();
        $this->version = $version;
    }

    /**
     * normalizeStatusName
     *
     * @param  mixed $statusName
     * @return string
     */
    public function normalizeStatusName(string $statusName): string
    {
        $icon = $this->getCategoryStatusIcon($statusName);
        return $icon . ' ' . $statusName;
    }

    /**
     * Détermine l'icône en fonction de la catégorie du status
     *
     * @param  mixed $statusName
     * @return string
     */
    public function getCategoryStatusIcon(string $statusName): string
    {
        $workflow = $this->config->getJiraWorkflow();

        if (in_array($statusName, $workflow['refinement_statuses'] ?? [], true)) {
            $icon = self::REFINEMENT_ICON;
        } elseif (in_array($statusName, $workflow['sprint_statuses'] ?? [], true)) {
            $icon = self::SPRINT_ICON;
        } elseif (in_array($statusName, $workflow['done_statuses'] ?? [], true)) {
            $icon = self::DONE_ICON;
        } else {
            $icon = self::OTHER_ICON;
        }

        return $icon;
    }

    /**
     * *********************
     * * All about Version *
     * *********************
     */
    /**
     * getVersionId
     *
     * @return string
     */
    public function getVersionId(): string
    {
        return $this->version->getId() ?? '?';
    }

    /**
     * getVersionName
     *
     * @return string
     */
    public function getVersionName(): string
    {
        return $this->version->getName() ?? '<i>Nom de version non renseigné.</i>';
    }

    /**
     * getVersionDescription
     *
     * @return string
     */
    public function getVersionDescription(): string
    {
        return $this->version->getDescription()
            ? htmlspecialchars($this->version->getDescription(), ENT_QUOTES, 'UTF-8')
            : '<i>Description non renseignée.</i>';
    }

    /**
     * getVersionUrl
     *
     * @return string
     */
    public function getVersionUrl(): string
    {
        return $this->version->getUrl() ?? '#';
    }

    /**
     * getVersionStartDate
     *
     * @return string
     */
    public function getVersionStartDate(): string
    {
        return $this->version->getStartDate() ?? '<i>Date non renseignée.</i>';
    }

    /**
     * getVersionReleaseDate
     *
     * @return string
     */
    public function getVersionReleaseDate(): string
    {
        return $this->version->getReleaseDate() ?? '<i>Non renseignée.</i>';
    }

    /**
     * isVersionReleased
     *
     * @return bool
     */
    public function isVersionReleased(): bool
    {
        return $this->version->isReleased();
    }

    /**
     * isVersionOverdue
     *
     * @return bool
     */
    public function isVersionOverdue(): bool
    {
        return $this->version->isOverdue();
    }

    /**
     * getProjectId
     */
    public function getProjectId()
    {
        return $this->version->getProjectId();
    }

    /**
     * getTotalLeadTime
     *
     * @return float
     */
    public function getTotalLeadTime(): int
    {
        return $this->version->getTotalLeadTime();
    }

    /**
     * getTotalLeadTime
     *
     * @return float
     */
    public function getAverageLeadTime(): float
    {
        return $this->version->getAverageLeadTime();
    }

    /**
     * getTotalLeadTime
     *
     * @return float
     */
    public function getTotalCycleTime(): int
    {
        return $this->version->getTotalCycleTime();
    }

    /**
     * getTotalLeadTime
     *
     * @return float
     */
    public function getAverageCycleTime(): float
    {
        return $this->version->getAverageCycleTime();
    }

    /**
     * getTotalLeadTime
     *
     * @return int
     */
    public function getTotalTimeSpentInRefinement(): int
    {
        return $this->version->getTotalTimeSpentInRefinement();
    }

    /**
     * getTotalLeadTime
     *
     * @return int
     */
    public function getTotalTimeSpentInSprint(): int
    {
        return $this->version->getTotalTimeSpentInSprint();
    }

    /**
     * getTotalLeadTime
     *
     * @return int
     */
    public function getTotalTimeSpentInOther(): int
    {
        return $this->version->getTotalTimeSpentInOther();
    }

    /**
     * ******************************
     * * All about Version's Issues *
     * ******************************
     */
    /**
     * getIssues
     *
     * @return void
     */
    public function getIssues()
    {
        return $this->version->getIssues();
    }

    /**
     * getIssuesCount
     *
     * @return int
     */
    public function getIssuesCount(): int
    {
        return $this->version->getIssuesCount();
    }

    /**
     * getStatusCSSClass
     *
     * @param  mixed $statusCategoryKey
     * @return string
     */
    public function getStatusCSSClass($statusCategoryKey): string
    {
        echo ''; //Utilisé ?
        if ($statusCategoryKey === 'new') {
            $cssClass = 'blue-gray';
        } elseif ($statusCategoryKey === 'done') {
            $cssClass = 'green';
        } else {
            $cssClass = 'yellow';
        }
        return $cssClass;
    }

    /**
     * getTimelineByStatus
     *
     * @param  mixed $timelineByStatus
     * @return array
     */
    public function getTimelineByStatus(): array
    {
        return $this->version->getTimelineByStatus();
    }

    /**
     * getAverageTimeSpentInRefinement
     *
     * @return float
     */
    public function getAverageTimeSpentInRefinement(): float
    {
        return $this->version->getAverageTimeSpentInRefinement();
    }

    /**
     * getAverageTimeSpentInSprint
     *
     * @return float
     */
    public function getAverageTimeSpentInSprint(): float
    {
        return $this->version->getAverageTimeSpentInSprint();
    }

    /**
     * getAverageTimeSpentInOther
     *
     * @return float
     */
    public function getAverageTimeSpentInOther(): float
    {
        return $this->version->getAverageTimeSpentInOther();
    }

    /**
     * Retourne les étapes du Value Stream Mapping,
     * prêtes à être affichées dans la vue.
     *
     * @return array<int, array{
     *   key: string,
     *   label: string,
     *   average_days: float,
     *   category: string
     * }>
     */
    public function getVsmSteps(): array
    {
        $timeline = $this->version->getTimelineByStatus();

        $steps = [];

        foreach ($timeline['workflowStatuses'] as $statusName => $metrics) {
            $steps[] = [
                'key'          => $statusName,
                'label'        => $this->normalizeStatusName($statusName),
                'average_days' => round($metrics['average_days'] ?? 0, 1),
                'category'     => $this->getStatusCategory($statusName),
            ];
        }

        return $steps;
    }

    /**
     * Retourne la catégorie fonctionnelle du statut
     */
    private function getStatusCategory(string $statusName): string
    {
        $workflow = $this->config->getJiraWorkflow();

        if (in_array($statusName, $workflow['refinement_statuses'] ?? [], true)) {
            return 'refinement';
        }

        if (in_array($statusName, $workflow['sprint_statuses'] ?? [], true)) {
            return 'sprint';
        }

        if (in_array($statusName, $workflow['done_statuses'] ?? [], true)) {
            return 'done';
        }

        return 'other';
    }
}
