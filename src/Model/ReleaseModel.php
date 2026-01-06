<?php
namespace App\Model;

use Exception;
use App\Service\JiraService;

/**
 * ReleaseModel
 */
class ReleaseModel
{
    //Liste des jours fériés au format Y-m-d
    const HOLIDAYS = [
        // Année précédente
        '2025-01-01', // Jour de l'An
        '2025-04-21', // Lundi de Pâques, variable
        '2025-05-01', // Fête du Travail
        '2025-05-08', // Victoire 1945
        '2025-05-29', // Ascension, variable
        '2025-06-09', // Lundi de Pentecôte, variable
        '2025-07-14', // Fête Nationale
        '2025-08-15', // Assomption
        '2025-11-01', // Toussaint
        '2025-11-11', // Armistice 1918
        '2025-12-25', // Noël
        // Année suivante
        '2026-01-01', // Jour de l'An
        '2026-04-06', // Lundi de Pâques, variable
        '2026-05-01', // Fête du Travail
        '2026-05-08', // Victoire 1945
        '2026-05-14', // Ascension, variable
        '2026-05-25', // Lundi de Pentecôte, variable
        '2026-07-14', // Fête Nationale
        '2026-08-15', // Assomption
        '2026-11-01', // Toussaint
        '2026-11-11', // Armistice 1918
        '2026-12-25', // Noël
    ];
    protected JiraService $jiraService;
    protected array $versionData = [];
    protected array $versionIssuesIds = [];
    protected array $versionIssues = [];
    
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->jiraService = new JiraService();
    }
    
    /**
     * getVersionById
     *
     * @param  mixed $versionId
     * @return array
     */
    public function getVersionById(int $versionId) : array 
    {
        $result = $this->jiraService->getVersionById($versionId);
        if (!$result['id']) {
            throw new Exception("Erreur lors de la récupération de la Version Jira : " . $result['message']);
        }
        $this->versionData = $result;
        return $this->versionData;
    }
    
    /**
     * getVersionsByProjectId
     *
     * @param  mixed $projectId
     * @return array
     */
    public function getVersionsByProjectId(int $projectId) : array 
    {
        $result = $this->jiraService->getVersionsByProjectId($projectId);

        return $result;
    }
    
    /**
     * getIssuesIdsByVersion
     *
     * @param  mixed $versionId
     * @return array
     */
    public function getIssuesIdsByVersion(int $versionId) : array 
    {
        $issues = $this->jiraService->getIssuesIdsByVersion($versionId);
        
        $result = [];
        foreach ($issues as $issue) {
            $result[] = $issue['id'] ?? '';
        }

        $this->versionIssuesIds = $result;
        return $this->versionIssuesIds;
    }
    
    /**
     * getIssuesDetailsByVersion
     *
     * @param  mixed $versionId
     * @return array
     */
    public function getIssuesDetailsByVersion(int $versionId) : array
    {
        try {
            //Evite un second appel pour récupérer les IDs si on les a déjà
            if (!$this->versionIssuesIds) {
                $this->getIssuesIdsByVersion($versionId);
            }
            
            $rawIssues = $this->jiraService->getIssuesDetails($this->versionIssuesIds);
     
            foreach ($rawIssues as $rawIssueData) {
                $this->versionIssues[] = new Issue($rawIssueData);
            }
    
            return $this->versionIssues;
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des tickets Jira rattachés à la Version : " . $e->getMessage());
        }
    }

    /**
     * Nettoie la réponse brute pour ne garder que les données utiles à afficher
     *
     * @param  array $rawIssues
     * @return array
     */
    // protected function formatRawIssuesData(array $rawIssues) : array
    // {
    //     $usefulIssuesDetails = [];

    //     foreach ($rawIssues as $index => $issue) {

    //         $inProgressDate = null;
    //         $doneDate = null;
            
    //         //Calcul du Cycle Time en parcourant le changelog du ticket
    //         if (!empty($issue['changelog']['histories'])) {
    //             foreach ($issue['changelog']['histories'] as $history) {
    //                 foreach ($history['items'] as $item) {
    //                     if ($item['field'] !== 'status') {
    //                         continue;
    //                     }

    //                     //Premier passage à En cours
    //                     if ($item['toString'] === 'In Progress' && $inProgressDate === null) {
    //                         $inProgressDate = new \DateTime($history['created']);
    //                     }

    //                     //Dernier passage à Done
    //                     if ($item['toString'] === 'Done') {
    //                         $doneDate = new \DateTime($history['created']);
    //                     }
    //                 }
    //             }
    //         }

    //         $leadTime = null;
    //         $cycleTime = null;
    //         //Si ticket est Terminé, on calcule les temps de résolution
    //         if ($inProgressDate && $doneDate) {
    //             $createdDate = new \DateTime($issue['fields']['created']);
    //             $leadTime = $doneDate->diff($createdDate)->days;

    //             //Nombre de jours entre les deux dates, excluant week-ends et jours fériés
    //             $cycleTime = $this->calculateBusinessDays($inProgressDate, $doneDate);
    //         }

    //         $usefulIssuesDetails[$index] = [
    //             'key' => $issue['key'],
    //             'summary' => $issue['fields']['summary'],
    //             'issuetype' => $issue['fields']['issuetype'] ?? [],
    //             'statusName' => $issue['fields']['status']['name'],
    //             'statusCategoryColor' => $issue['fields']['status']['statusCategory']['colorName'],
    //             'statusCategoryKey' => $issue['fields']['status']['statusCategory']['key'],
    //             'priority' => $issue['fields']['priority']['name'] ?? '—',
    //             'priorityIcon' => $issue['fields']['priority']['iconUrl'] ?? null,
    //             'created' => $this->formatDate($issue['fields']['created']),
    //             'firstInProgressDate' => $inProgressDate ? $inProgressDate->format('d/m/Y') : null,
    //             'doneDate' => $doneDate ? $doneDate->format('d/m/Y') : null,
    //             'resolutiondate' => $this->formatDate($issue['fields']['resolutiondate']) ?? null, //doublon ? A voir quand diff par Status
    //             'leadTime' => $leadTime,
    //             'cycleTime' => $cycleTime,
    //             // 'storypoints' => $issue['fields']['customfield_10016'] ?? null
    //         ];
    //     }

    //     return $usefulIssuesDetails;
    // }


    /**
     * Calcule le nombre de jours ouvrés entre deux dates (week-ends et jours fériés exclus).
     *
     * @param \DateTime $start Date de début (incluse)
     * @param \DateTime $end Date de fin (incluse)
     *
     * @return int Nombre de jours ouvrés
     */
    public function calculateBusinessDays(\DateTime $start, \DateTime $end): int 
    {
        if ($start > $end) {
            return 0;
        }
    
        $businessDays = 1;
        $current = clone $start;
    
        while ($current <= $end) {
            $dayOfWeek = (int) $current->format('N'); // 1 (lundi) → 7 (dimanche)
            $currentDate = $current->format('Y-m-d');
    
            $isWeekend = ($dayOfWeek >= 6);
            $isHoliday = in_array($currentDate, self::HOLIDAYS, true);
    
            if (!$isWeekend && !$isHoliday) {
                $businessDays++;
            }
    
            $current->modify('+1 day');
        }
    
        return $businessDays;
    }    


    /**
     * formatDate
     *
     * @param  mixed $dateStr
     * @return string
     */
    protected function formatDate(?string $dateStr): ?string
    {
        if ($dateStr === null) {
            return null;
        }

        $date = new \DateTime($dateStr);
        return $date->format('d/m/Y');
    }
}