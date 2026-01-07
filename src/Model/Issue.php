<?php
namespace App\Model;

use Exception;
use App\Model\ReleaseModel;

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

    protected ReleaseModel $releaseModel;

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
    protected int $leadTime = 0;
    protected int $cycleTime = 0;
    protected array $timeByStatus = [];
    protected array $timeByCategory = [];

    /**
     * Issue constructor
     *
     * @param array $data Données brutes issues de l’API Jira
     */
    public function __construct(array $data)
    {
        $this->releaseModel = new ReleaseModel();
        $this->initialize($data);
    }

    /**
     * Initialise l’objet Issue à partir des données Jira
     *
     * @param array $data
     * @return void
     */
    private function initialize(array $data): void
    {
        foreach ($data as $index => $value) {
            $this->setData($index, $value);
        }

        $this->initializeDates();
        $this->buildTimeline();
        $this->setLeadTime();
        $this->setCycleTime();
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

    /**
     * Initialise les dates principales
     *
     * @return void
     */
    private function initializeDates(): void
    {
        if (!empty($this->data['fields']['created'])) {
            $this->createdDate = new \DateTime($this->data['fields']['created']);
        }
    }

    /**
     * Reconstruit la timeline de l’issue à partir du changelog Jira
     * et calcule le temps cumulé par status et par status category.
     *
     * Cette méthode est appelée une seule fois à l'initialisation
     * de l'objet Issue.
     *
     * @return void
     */
    protected function buildTimeline(): void
    {
        if (empty($this->data['changelog']['histories'])) {
            return;
        }
    
        // Status initial
        $currentStatus = $this->data['fields']['status']['name'];
        $currentCategory = $this->data['fields']['status']['statusCategory']['name'];
        $currentDate = new \DateTime($this->data['fields']['created']);
    
        // Sécurisation : tri chronologique du changelog
        usort(
            $this->data['changelog']['histories'],
            fn ($a, $b) => strtotime($a['created']) <=> strtotime($b['created'])
        );
    
        //Parcours du changelog, en ciblant uniquement les changements de status
        foreach ($this->data['changelog']['histories'] as $history) {
            foreach ($history['items'] as $item) {
    
                if ($item['field'] !== 'status') {
                    continue;
                }

                //Date du changement du status
                $transitionDate = new \DateTime($history['created']);
                $days = (int) $currentDate->diff($transitionDate)->days;
    
                // Agrégation
                $this->timeByStatus[$currentStatus] =
                    ($this->timeByStatus[$currentStatus] ?? 0) + $days;
    
                $this->timeByCategory[$currentCategory] =
                    ($this->timeByCategory[$currentCategory] ?? 0) + $days;
    
                // Premier passage à En cours
                if ($item['toString'] === self::STATUS_IN_PROGRESS && $this->firstInProgressDate === null) {
                    $this->firstInProgressDate = $transitionDate;
                }
                // Dernier passage à Done (pour prendre en compte les éventuels allers-retours de status)
                if ($item['toString'] === self::STATUS_DONE) {
                    $this->doneDate = $transitionDate;
                }
    
                // Mise à jour du status courant
                $currentStatus = $item['toString'];
                $currentCategory = $this->data['fields']['status']['statusCategory']['name'];
                $currentDate = $transitionDate;
            }
        }
    
        // Dernier segment (jusqu'à Done, ou date du jour si ticket pas encore résolu)
        $endDate = !empty($this->data['fields']['resolutiondate'])
            ? new \DateTime($this->data['fields']['resolutiondate'])
            : new \DateTime();
    
        $days = (int) $currentDate->diff($endDate)->days;
    
        $this->timeByStatus[$currentStatus] =
            ($this->timeByStatus[$currentStatus] ?? 0) + $days;
    
        $this->timeByCategory[$currentCategory] =
            ($this->timeByCategory[$currentCategory] ?? 0) + $days;
    }
    

    /* =======================
     * ====== GETTERS ========
     * =======================
     */

    /**
     * Accès aux données brutes si nécessaire (debug / extension)
     */
    public function getRawData(): array
    {
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

    public function getStatusCategory(): array
    {
        return $this->data['fields']['status']['statusCategory'] ?? [];
    }

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
     * @return int
     */
    public function getLeadTime(): int
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
        $this->cycleTime = $this->releaseModel->calculateBusinessDays(
            $this->firstInProgressDate,
            $this->doneDate
        );
    }

    /**
     * Cycle Time (jours ouvrés)
     *
     * @return int
     */
    public function getCycleTime(): int
    {
        return $this->cycleTime;
    }
    
    /**
     * Temps cumulé passé par status Jira
     *
     * @return array<string,int>
     */
    public function getTimeByStatus(): array
    {
        return $this->timeByStatus;
    }

    /**
     * Temps cumulé passé par Status Category Jira
     *
     * @return array<string,int>
     */
    public function getTimeByCategory(): array
    {
        return $this->timeByCategory;
    }
}
