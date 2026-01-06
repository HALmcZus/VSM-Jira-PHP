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
    protected ReleaseModel $releaseModel;

    /**
     * Données brutes issues de l’API Jira
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Dates clés du cycle de vie
     */
    protected ?\DateTime $createdDate = null;
    protected ?\DateTime $firstInProgressDate = null;
    protected ?\DateTime $doneDate = null;
    protected int $leadTime = 0;
    protected int $cycleTime = 0;

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
        // $this->calculateTimeline($data);
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
     *
     * @return void
     */
    protected function buildTimeline(): void
    {
        if (empty($this->data['changelog']['histories'])) {
            return;
        }

        foreach ($this->data['changelog']['histories'] as $history) {
            foreach ($history['items'] as $item) {

                if ($item['field'] !== 'status') {
                    continue;
                }

                //Date du changement de status
                $transitionDate = new \DateTime($history['created']);

                // Premier passage à "In Progress"
                if ($item['toString'] === 'In Progress' && $this->firstInProgressDate === null) {
                    $this->firstInProgressDate = $transitionDate;
                }

                // Dernier passage à "Done"
                if ($item['toString'] === 'Done') {
                    $this->doneDate = $transitionDate;
                }
            }
        }
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
     * setLeadTime
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
            $this->cycleTime = 0.0;
            return;
        }

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
     * Calcule le temps cumulé passé dans chaque status Jira
     * ainsi que par Status Category, à partir du changelog.
     *
     * @param array $issue Issue Jira brute issue de /search/jql
     *
     * @return array{
     *   byStatus: array<string, float>,
     *   byCategory: array<string, float>
     * }
     *
     * @throws Exception Si les données Jira sont incomplètes
     */
    // public function calculateTimeline(array $issue = []): array
    // {
    //     if (empty($issue['fields']['status']) || empty($issue['changelog']['histories'])) {
    //         throw new Exception('Issue Jira incomplète : status ou changelog manquant');
    //     }

    //     $statusTimes = [];
    //     $categoryTimes = [];

    //     // Status initial
    //     $currentStatus = $issue['fields']['status']['name'];
    //     $currentCategory = $issue['fields']['status']['statusCategory']['name'];
    //     $currentDate = new \DateTime($issue['fields']['created']);

    //     // Historique trié chronologiquement (sécurité)
    //     usort($issue['changelog']['histories'], function ($a, $b) {
    //         return strtotime($a['created']) <=> strtotime($b['created']);
    //     });

    //     //On parcourt le changelog
    //     foreach ($issue['changelog']['histories'] as $history) {
    //         foreach ($history['items'] as $item) {
    //             if ($item['field'] !== 'status') {
    //                 continue;
    //             }

    //             $transitionDate = new \DateTime($history['created']);
    //             $interval = $currentDate->diff($transitionDate);
    //             $days = (float) $interval->format('%a');

    //             // Agrégation par status
    //             $statusTimes[$currentStatus] = ($statusTimes[$currentStatus] ?? 0) + $days;
    //             $categoryTimes[$currentCategory] = ($categoryTimes[$currentCategory] ?? 0) + $days;

    //             // Mise à jour du status courant
    //             $currentStatus = $item['toString'];
    //             $currentCategory = $issue['fields']['status']['statusCategory']['name'];
    //             $currentDate = $transitionDate;
    //         }
    //     }

    //     // Dernier segment (jusqu'à date de résolution, ou date du jour si ticket pas résolu)
    //     $endDate = !empty($issue['fields']['resolutiondate'])
    //         ? new \DateTime($issue['fields']['resolutiondate'])
    //         : new \DateTime();

    //     $interval = $currentDate->diff($endDate);
    //     $days = (float) $interval->format('%a');

    //     $statusTimes[$currentStatus] = ($statusTimes[$currentStatus] ?? 0) + $days;
    //     $categoryTimes[$currentCategory] = ($categoryTimes[$currentCategory] ?? 0) + $days;

    //     return [
    //         'byStatus' => $statusTimes,
    //         'byCategory' => $categoryTimes,
    //     ];
    // }
}
