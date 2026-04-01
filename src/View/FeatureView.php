<?php

namespace App\View;

use App\Model\Config;
use App\Model\Feature;

/**
 * FeatureView
 *
 * Formate et expose les données spécifiques à une Feature Jira.
 * Les données communes (métriques, timeline) sont héritées d'AbstractCollectionView.
 */
class FeatureView extends AbstractCollectionView
{
    private Feature $feature;

    public function __construct(Feature $feature)
    {
        parent::__construct($feature);
        $this->feature = $feature;
    }

    public function getFeatureId(): string
    {
        return $this->feature->getId() ?? '?';
    }
    public function getFeatureKey(): string
    {
        return $this->feature->getKey() ?? '?';
    }

    public function getFeatureName(): string
    {
        return $this->feature->getName() ?? '<i>Nom de feature non renseigné.</i>';
    }

    public function getFeatureUrl(): string
    {
        return $this->feature->getUrl();
    }
    public function getStatusName(): string
    {
        return $this->feature->getStatusName() ?? '—';
    }
    public function getPlanningIntervals(): string
    {
        return implode(', ', $this->feature->getPlanningIntervals());
    }

    /**
     * Retourne le Planning Interval formaté pour l'affichage.
     * Ex : "PI 7" ou null si non renseigné.
     */
    public function getPlanningIntervalLabel(): ?string
    {
        $lastPI = $this->feature->getPlanningInterval();
        return $lastPI !== null ? 'PI ' . $lastPI : null;
    }

    /**
     * Retourne le nom de la priorité de la Feature, ou '—' si absent.
     */
    public function getPriorityName(): string
    {
        return $this->feature->getPriorityName() ?? '—';
    }

    /**
     * Retourne l'URL de l'icône de priorité, ou null si absente.
     */
    public function getPriorityIconUrl(): ?string
    {
        return $this->feature->getPriorityIconUrl();
    }

    /**
     * Retourne le Jalon formaté pour l'affichage, ou '—' si absent.
     */
    public function getJalon(): string
    {
        return $this->feature->getJalon() ?? '—';
    }

    /**
     * Retourne le nom de l'Epic, ou '—' si absent.
     */
    public function getEpicName(): string
    {
        return $this->feature->getEpicName() ?? '—';
    }

    /**
     * Retourne les équipes sous forme de chaîne lisible, ou '—' si aucune.
     */
    public function getTeams(): string
    {
        $teams = $this->feature->getTeams();
        return $teams ? implode(', ', $teams) : '—';
    }

    /**
     * Retourne la description HTML rendue par Jira, ou null si absente.
     */
    public function getDescriptionHtml(): ?string
    {
        return $this->feature->getDescriptionHtml() ?? "<i>Aucune description renseignée.</i>";
    }

    // ==================== Métriques du ticket Feature lui-même ====================

    public function getSelfCreatedDate(): \DateTime|string
    {
        return $this->feature->getSelfAsIssue()?->getCreatedDate() ?? '-';
    }

    public function getSelfStartDate(): \DateTime|string
    {
        return $this->feature->getSelfAsIssue()?->getFirstInProgressDate() ?? '-';
    }

    public function getSelfEndDate(): \DateTime|string
    {
        return $this->feature->getSelfAsIssue()?->getResolutionDateTime(true) ?? '-';
    }

    public function getSelfLeadTime(): float
    {
        return $this->feature->getSelfAsIssue()?->getLeadTime() ?? 0.0;
    }

    public function getSelfCycleTime(): float
    {
        return $this->feature->getSelfAsIssue()?->getCycleTime() ?? 0.0;
    }

    public function getSelfTimeSpentInRefinement(): float
    {
        return $this->feature->getSelfAsIssue()?->getTimeSpentInRefinement() ?? 0.0;
    }

    public function getSelfTimeSpentInSprint(): float
    {
        return $this->feature->getSelfAsIssue()?->getTimeSpentInSprint() ?? 0.0;
    }

    public function getSelfTimeSpentInOther(): float
    {
        return $this->feature->getSelfAsIssue()?->getTimeSpentInOther() ?? 0.0;
    }

    public function getSelfFirstInProgressDate(): ?string
    {
        return $this->feature->getSelfAsIssue()?->getFirstInProgressDate() ?? null;
    }

    public function getSelfDoneDate(): ?string
    {
        return $this->feature->getSelfAsIssue()?->getDoneDate() ?? null;
    }

    /**
     * Retourne la timeline du ticket Feature lui-même, triée selon jira_workflow_features.json.
     * Structure : ['workflowStatuses' => [...], 'otherStatuses' => [...]]
     *
     * @return array
     */
    public function getSelfTimelineByStatus(): array
    {
        return $this->feature->getSelfAsIssue()?->getTimeByStatus(true)
            ?? ['workflowStatuses' => [], 'otherStatuses' => []];
    }

    /**
     * Retourne les étapes VSM du ticket Feature lui-même, selon jira_workflow_features.json.
     * Pour un ticket unique, "average_days" = jours réels passés dans le statut.
     *
     * @return array<int, array{key: string, label: string, days: float, category: string}>
     */
    public function getSelfVsmSteps(): array
    {
        $timeline = $this->getSelfTimelineByStatus();
        $steps = [];

        foreach ($timeline['workflowStatuses'] as $statusName => $days) {
            if ($days <= 0) {
                continue;
            }
            $steps[] = [
                'key'      => $statusName,
                'label'    => $this->normalizeSelfStatusName($statusName),
                'days'     => round($days, 2),
                'category' => $this->getSelfStatusCategory($statusName),
            ];
        }

        return $steps;
    }

    /**
     * Préfixe un nom de statut Feature avec l'icône de sa catégorie (workflow Features).
     *
     * @param  string $statusName
     * @return string Ex: "🧠 Funnel"
     */
    public function normalizeSelfStatusName(string $statusName): string
    {
        return $this->getSelfCategoryIcon($statusName) . ' ' . $statusName;
    }

    /**
     * Retourne la catégorie d'un statut selon jira_workflow_features.json.
     *
     * @param  string $statusName
     * @return string 'refinement'|'sprint'|'done'|'other'
     */
    private function getSelfStatusCategory(string $statusName): string
    {
        $workflow = $this->config->getJiraWorkflow(Config::ISSUE_TYPE_FEATURE);

        return match (true) {
            in_array($statusName, $workflow['refinement_statuses'] ?? [], true) => 'refinement',
            in_array($statusName, $workflow['sprint_statuses'] ?? [], true)     => 'sprint',
            in_array($statusName, $workflow['done_statuses'] ?? [], true)       => 'done',
            default                                                             => 'other',
        };
    }

    /**
     * Retourne l'icône correspondant à la catégorie d'un statut Feature.
     *
     * @param  string $statusName
     * @return string
     */
    private function getSelfCategoryIcon(string $statusName): string
    {
        $workflow = $this->config->getJiraWorkflow(Config::ISSUE_TYPE_FEATURE);

        return match (true) {
            in_array($statusName, $workflow['refinement_statuses'] ?? [], true) => self::REFINEMENT_ICON,
            in_array($statusName, $workflow['sprint_statuses'] ?? [], true)     => self::SPRINT_ICON,
            in_array($statusName, $workflow['done_statuses'] ?? [], true)       => self::DONE_ICON,
            default                                                             => self::OTHER_ICON,
        };
    }
}
