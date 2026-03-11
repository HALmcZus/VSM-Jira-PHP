<?php

namespace App\Model;

use App\Service\JiraService;

/**
 * AbstractIssueCollection
 *
 * Classe de base pour tout agrégat d'issues Jira avec calcul de métriques.
 *
 * Pattern Template Method :
 * Le constructeur orchestre le cycle de vie (chargement → calculs).
 * Chaque sous-classe implémente uniquement le chargement de ses données propres.
 */
abstract class AbstractIssueCollection
{
    protected JiraService $jiraService;
    protected Config $config;
    protected Timeline $timeline;

    /** @var Issue[] */
    protected array $issues = [];
    protected int $issuesCount = 0;

    protected float $totalLeadTime = 0.0;
    protected float $averageLeadTime = 0.0;
    protected float $totalCycleTime = 0.0;
    protected float $averageCycleTime = 0.0;
    protected float $totalTimeSpentInRefinement = 0.0;
    protected float $averageTimeSpentInRefinement = 0.0;
    protected float $totalTimeSpentInSprint = 0.0;
    protected float $averageTimeSpentInSprint = 0.0;
    protected float $totalTimeSpentInOther = 0.0;
    protected float $averageTimeSpentInOther = 0.0;

    protected array $timelineByStatus = [];
    protected array $averageTimeByStatus = [];
    protected array $aggregatedWaitingTimes = [];

    /**
     * Constructeur — orchestre le cycle de vie via Template Method.
     *
     * @param int $id Identifiant numérique Jira (Version ID ou Issue ID)
     */
    public function __construct(int $id)
    {
        $this->jiraService = new JiraService();
        $this->config      = new Config();
        $this->timeline    = new Timeline();

        $this->loadCollectionData($id);
        $this->loadIssues($id);
        $this->calculateLeadAndCycleTime();
        $this->calculateTimelineByStatus();
        $this->calculateAverageTimeByStatus();
    }

    /**
     * Charge les données propres à la collection (Version ou Feature).
     * Ne doit PAS charger les issues — c'est le rôle de loadIssues().
     *
     * @param int $id
     */
    abstract protected function loadCollectionData(int $id): void;

    /**
     * Charge les issues associées et peuple $this->issues + $this->issuesCount.
     *
     * @param int $id
     */
    abstract protected function loadIssues(int $id): void;

    /**
     * Calcule Lead Time, Cycle Time et les temps par phase
     * en agrégeant les métriques de chaque issue.
     */
    protected function calculateLeadAndCycleTime(): void
    {
        if ($this->issuesCount === 0) {
            return;
        }

        foreach ($this->issues as $issue) {
            $this->totalLeadTime               += $issue->getLeadTime() ?? 0;
            $this->totalCycleTime              += $issue->getCycleTime() ?? 0;
            $this->totalTimeSpentInRefinement  += $issue->getTimeSpentInRefinement() ?? 0.0;
            $this->totalTimeSpentInSprint      += $issue->getTimeSpentInSprint() ?? 0.0;
            $this->totalTimeSpentInOther       += $issue->getTimeSpentInOther() ?? 0.0;

            foreach ($issue->getWaitingTimes() as $label => $days) {
                $this->aggregatedWaitingTimes[$label] = ($this->aggregatedWaitingTimes[$label] ?? 0.0) + $days;
            }
        }

        $this->averageLeadTime  = round($this->totalLeadTime / $this->issuesCount, 2);
        $this->averageCycleTime = round($this->totalCycleTime / $this->issuesCount, 2);
    }

    /**
     * Calcule le temps cumulé par statut Jira, agrégé sur toutes les issues.
     */
    protected function calculateTimelineByStatus(): void
    {
        foreach ($this->issues as $issue) {
            foreach ($issue->getTimeByStatus(false) as $statusName => $timeSpent) {
                $this->timelineByStatus[$statusName] = ($this->timelineByStatus[$statusName] ?? 0) + $timeSpent;
            }
        }
    }

    /**
     * Calcule le temps moyen par statut et par phase (refinement, sprint, other).
     */
    protected function calculateAverageTimeByStatus(): void
    {
        if ($this->issuesCount === 0) {
            return;
        }

        foreach ($this->timelineByStatus as $statusName => $timeSpent) {
            $this->averageTimeByStatus[$statusName] = round($timeSpent / $this->issuesCount, 2);
        }

        $this->averageTimeSpentInRefinement = round($this->totalTimeSpentInRefinement / $this->issuesCount, 2);
        $this->averageTimeSpentInSprint     = round($this->totalTimeSpentInSprint / $this->issuesCount, 2);
        $this->averageTimeSpentInOther      = round($this->totalTimeSpentInOther / $this->issuesCount, 2);
    }

    // ==================== Getters ====================

    /** @return Issue[] */
    public function getIssues(): array
    {
        return $this->issues;
    }
    public function getIssuesCount(): int
    {
        return $this->issuesCount;
    }

    public function getTotalLeadTime(): float
    {
        return $this->totalLeadTime;
    }
    public function getAverageLeadTime(): float
    {
        return $this->averageLeadTime;
    }
    public function getTotalCycleTime(): float
    {
        return $this->totalCycleTime;
    }
    public function getAverageCycleTime(): float
    {
        return $this->averageCycleTime;
    }
    public function getTotalTimeSpentInRefinement(): float
    {
        return $this->totalTimeSpentInRefinement;
    }
    public function getAverageTimeSpentInRefinement(): float
    {
        return $this->averageTimeSpentInRefinement;
    }
    public function getTotalTimeSpentInSprint(): float
    {
        return $this->totalTimeSpentInSprint;
    }
    public function getAverageTimeSpentInSprint(): float
    {
        return $this->averageTimeSpentInSprint;
    }
    public function getTotalTimeSpentInOther(): float
    {
        return $this->totalTimeSpentInOther;
    }
    public function getAverageTimeSpentInOther(): float
    {
        return $this->averageTimeSpentInOther;
    }

    public function getTimelineByStatus(): array
    {
        return $this->timeline->getSortedTimelineByStatus($this->timelineByStatus);
    }

    public function getAverageTimeByStatus(): array
    {
        return $this->averageTimeByStatus;
    }

    /**
     * @return array<string, float> label => jours ouvrés cumulés, trié desc
     */
    public function getAggregatedWaitingTimes(): array
    {
        arsort($this->aggregatedWaitingTimes);
        return $this->aggregatedWaitingTimes;
    }
}
