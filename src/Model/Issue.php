<?php

namespace App\Model;

use Exception;
use App\Model\Config;
use App\Model\Timeline;

/**
 * Issue
 *
 * Représente une Issue Jira.
 * - Hydratée directement depuis l’API Jira
 * - Encapsule les données brutes
 * - Centralise la logique métier (timeline, métriques)
 */
class Issue
{
    const STATUS_TODO = 'To Do';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_DONE = 'Done';
    const ISSUE_URL = '{base_url}/browse/{issue_key}';

    protected Config $config;
    protected Timeline $timeline;

    /**
     * Données brutes issues de l’API Jira
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Données clés du cycle de vie
     */
    protected ?\DateTime $createdDate = null;
    protected ?\DateTime $firstInProgressDate = null;
    protected ?\DateTime $doneDate = null;
    protected float $leadTime = 0.0;
    protected float $cycleTime = 0.0;
    protected array $timeByStatus = [];
    protected array $workflowTimeBreakdown = [
        'refinement' => 0.0,
        'sprint' => 0.0,
        'other' => 0.0
    ];
    protected array $waitingTimes = [];

    /**
     * Issue constructor
     *
     * @param array $data Données brutes issues de l’API Jira
     */
    public function __construct(array $data)
    {
        $this->config = new Config();
        $this->timeline = new Timeline();

        $this->initializeData($data);
        $this->timeline->buildStatusTimeline($this);
        $this->timeline->buildWaitingTimes($this);
        $this->setLeadTime();
        $this->setCycleTime();
    }

    /**
     * Initialise l’objet Issue à partir des données Jira
     *
     * @param array $data
     * @return void
     */
    private function initializeData(array $data): void
    {
        foreach ($data as $index => $value) {
            $this->setData($index, $value);
        }

        if (!empty($this->data['fields']['created'])) {
            $this->createdDate = new \DateTime($this->data['fields']['created']);
        }
    }

    /**
     * Affecte une donnée brute à l’Issue
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    protected function setData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }


    /* =======================
     * ====== GETTERS ========
     * =======================
     */
    public function getData(string $data = null): array
    {
        if ($data !== null && isset($this->data[$data])) {
            return $this->data[$data];
        }
        return $this->data;
    }

    public function getKey(): string
    {
        return $this->data['key'] ?? '';
    }

    public function getSummary(): string
    {
        return $this->data['fields']['summary'] ?? '';
    }

    public function getIssueType(): array
    {
        return $this->data['fields']['issuetype'] ?? [];
    }

    public function getIssueTypeName(): string
    {
        return $this->data['fields']['issuetype']['name'] ?? '';
    }

    public function getIssueTypeIcon(): string
    {
        return $this->data['fields']['issuetype']['iconUrl'] ?? '';
    }

    public function getPriority(): string
    {
        return $this->data['fields']['priority'] ?? '';
    }

    public function getPriorityName(): string
    {
        return $this->data['fields']['priority']['name'] ?? '';
    }

    public function getPriorityIcon(): string
    {
        return $this->data['fields']['priority']['iconUrl'] ?? '';
    }

    public function getStatusName(): string
    {
        return $this->data['fields']['status']['name'] ?? '';
    }

    /**
     * getStatusCategoryColor : retrieves the color name of the status category of the issue
     *
     * @return string the color name of the status category, or an empty string if not found
     */
    public function getStatusCategoryColor(): string
    {
        return $this->data['fields']['status']['statusCategory']['colorName'] ?? '';
    }

    /**
     * getCreatedDate
     *
     * @param  bool $format
     * @return DateTime|string
     */
    public function getCreatedDate(bool $format = true)
    {
        if ($this->createdDate === null) {
            return null;
        }

        return $format
            ? $this->createdDate->format('Y-m-d')
            : $this->createdDate;
    }

    /**
     * Return the resolution date as a DateTime object.
     *
     * @return \DateTime|null
     */
    public function getResolutionDateTime(): \DateTime|null
    {
        return !empty($this->data['fields']['resolutiondate'])
            ? new \DateTime($this->data['fields']['resolutiondate'])
            : new \DateTime();
    }

    /**
     * Return the history of the issue as an array.
     * If $sorted is true, the history will be sorted by date (oldest first).
     *
     * @return array
     */
    public function getHistory($sorted = true): array
    {
        $history = $this->data['changelog']['histories'] ?? [];
        if (empty($history) || !$sorted) {
            return $history;
        }

        // Tri chronologique du changelog
        usort(
            $history,
            fn($a, $b) => strtotime($a['created']) <=> strtotime($b['created'])
        );

        return $history;
    }

    /**
     * getFirstInProgressDate
     *
     * @param  bool $format
     * @return DateTime|string
     */
    public function getFirstInProgressDate(bool $format = true)
    {
        if ($this->firstInProgressDate === null) {
            return null;
        }

        return $format
            ? $this->firstInProgressDate->format('Y-m-d')
            : $this->firstInProgressDate;
    }

    /**
     * Set the first in-progress date of the issue.
     *
     * @param \DateTime $date The first in-progress date of the issue.
     *
     * @return void
     */
    public function setFirstInProgressDate(\DateTime $date): void
    {
        $this->firstInProgressDate = $date;
    }

    /**
     * getDoneDate
     *
     * @param  bool $format
     * @return DateTime|string
     */
    public function getDoneDate(bool $format = true)
    {
        if ($this->doneDate === null) {
            return null;
        }

        return $format
            ? $this->doneDate->format('Y-m-d')
            : $this->doneDate;
    }

    /**
     * Set the Done date of the issue.
     *
     * @param \DateTime $date The Done date of the issue.
     *
     * @return void
     */
    public function setDoneDate(\DateTime $date): void
    {
        $this->doneDate = $date;
    }

    /**
     * setLeadTime (jours calendaires)
     *
     * @return void
     */
    private function setLeadTime(): void
    {
        if (!$this->createdDate || !$this->doneDate) {
            $this->leadTime = 0;
            return;
        }

        $this->leadTime = ($this->doneDate->diff($this->createdDate)->days) + 1;
    }

    /**
     * Lead Time (jours calendaires)
     *
     * @return float
     */
    public function getLeadTime(): float
    {
        return $this->leadTime;
    }

    /**
     * setCycleTime (jours ouvrés)
     *
     * @return void
     */
    private function setCycleTime(): void
    {
        if (!$this->firstInProgressDate || !$this->doneDate) {
            $this->cycleTime = 0;
            return;
        }

        //Calcul en jours ouvrés (excluant week-ends et jours fériés)
        $this->cycleTime = $this->timeline->calculateBusinessDays(
            $this->firstInProgressDate,
            $this->doneDate
        );
    }

    /**
     * Cycle Time (jours ouvrés)
     *
     * @return float
     */
    public function getCycleTime(): float
    {
        return $this->cycleTime;
    }


    /**
     * Set the timeline by status of the issue.
     *
     * @param array $timeByStatus Timeline by status, where each key is a status name and each value is the number of days spent in that status.
     *
     * @return void
     */
    public function setTimeByStatus(array $timeByStatus): void
    {
        $this->timeByStatus = $timeByStatus;
    }

    /**
     * Temps cumulé passé par status Jira
     *
     * @return array<string,int>
     */
    public function getTimeByStatus($splitOtherStatuses): array
    {
        return $this->timeline->getSortedTimelineByStatus($this->timeByStatus, $splitOtherStatuses);
    }

    /**
     * Sets the workflow time breakdown for the issue.
     *
     * @param array $workflowTimeBreakdown Workflow time breakdown, where each key is a category name and each value is the number of days spent in that category.
     *
     * @return void
     */
    public function setWorkflowTimeBreakdown(array $workflowTimeBreakdown)
    {
        $this->workflowTimeBreakdown = $workflowTimeBreakdown;
    }

    public function getWorkflowTimeBreakdown(): array
    {
        return $this->workflowTimeBreakdown;
    }

    public function getTimeSpentInRefinement(): float
    {
        return $this->workflowTimeBreakdown['refinement'];
    }

    public function getTimeSpentInSprint(): float
    {
        return $this->workflowTimeBreakdown['sprint'];
    }

    public function getTimeSpentInOther(): float
    {
        return $this->workflowTimeBreakdown['other'];
    }

    public function getIssueUrl(): string
    {
        return str_replace(
            ['{base_url}', '{issue_key}'],
            [$_ENV['JIRA_BASE_URL'], $this->getKey()],
            self::ISSUE_URL
        );
    }

    public function setWaitingTimes(array $waitingTimes): void
    {
        $this->waitingTimes = $waitingTimes;
    }

    public function getWaitingTimes(): array
    {
        return $this->waitingTimes;
    }
}
