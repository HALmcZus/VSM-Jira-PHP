<?php
namespace App\Model;

use Exception;
use App\Service\JiraService;

class ReleaseModel
{
    protected JiraService $jiraService;
    protected array $versionData = [];
    protected array $versionIssuesIds = [];
    protected array $versionIssuesDetails = [];

    public function __construct()
    {
        $this->jiraService = new JiraService();
    }

    public function getVersionById(int $versionId) : array 
    {
        $result = $this->jiraService->getVersionById($versionId);
        /**"version": {
            "self": "https://imsa.atlassian.net/rest/api/2/version/19075",
            "id": "19075",
            "description": "REP-210 Mise à disposition de DPAE pour la DGT 6 création des messages MOM DPAE pour alimentation base nationale",
            "name": "REP-210 DPAE DGT",
            "archived": false,
            "released": false,
            "startDate": "2025-06-29",
            "releaseDate": "2025-07-31",
            "overdue": true,
            "userStartDate": "29/juin/25",
            "userReleaseDate": "31/juil./25",
            "projectId": 11547
          },
        */
        if (!$result['id']) {
            throw new Exception("Erreur lors de la récupération de la Version Jira : " . $result['message']);
        }
        $this->versionData = $result;
        return $this->versionData;
    }


    public function getVersionsByProjectId(int $projectId) : array 
    {
        $result = $this->jiraService->getVersionsByProjectId($projectId);

        return $result;
    }


    public function getIssuesIdsByVersion(int $versionId) : array 
    {
        $issues = $this->jiraService->getIssuesIdsByVersion($versionId);
        
        $result = [];
        foreach ($issues['issues'] as $issue) {
            $result[] = $issue['id'] ?? '';
        }

        $this->versionIssuesIds = $result;
        return $this->versionIssuesIds;
    }

    public function getIssuesDetailsByVersion(int $versionId, $raw = false) : array 
    {
        //Evite un second appel pour récupérer les IDs si on les a déjà
        if (!$this->versionIssuesIds) {
            $this->getIssuesIdsByVersion($versionId);
        }
        
        $rawIssues = $this->jiraService->getIssuesDetails($this->versionIssuesIds);
        
        //Si demandé, retourne les données brutes        
        //Sinon par défaut, nettoie la réponse brute pour ne garder que les données utiles à afficher
        $this->versionIssuesDetails = $raw 
        ? $rawIssues 
        : $this->cleanRawIssuesData($rawIssues);

        return $this->versionIssuesDetails;
    }

    /**
     * Nettoie la réponse brute pour ne garder que les données utiles à afficher
     */
    protected function cleanRawIssuesData(array $rawIssues) : array
    {
        foreach ($rawIssues['issues'] as $index => $issue) {
            // $assignee = $issue['fields']['assignee']['displayName'] ?? 'Non assigné';

            //Calcul du Cycle Time
            $createdDate = new \DateTime($issue['fields']['created']);
            $resolvedDate = isset($issue['fields']['resolutiondate']) ? new \DateTime($issue['fields']['resolutiondate']) : null;
            $cycleTime = null;
            if ($resolvedDate) {
                $interval = $createdDate->diff($resolvedDate);
                //Nombre de jours entre les deux dates
                $cycleTime = (int) $interval->format('%a'); 
            }

            $usefulIssuesDetails[$index] = [
                'key' => $issue['key'],
                'summary' => $issue['fields']['summary'],
                'statusName' => $issue['fields']['status']['name'],
                'statusCategoryColor' => $issue['fields']['status']['statusCategory']['colorName'],
                'statusCategoryKey' => $issue['fields']['status']['statusCategory']['key'],
                // 'assignee' => $assignee,
                'created' => $this->formatDate($issue['fields']['created']),
                'resolutiondate' => $this->formatDate($issue['fields']['resolutiondate']) ?? null,
                'priority' => $issue['fields']['priority']['name'] ?? '—',
                // 'history' => $issue['changelog']['histories'] ?? [],
                // 'changeLog' => $issue['changelog'] ?? [],
                'cycleTime' => $cycleTime
            ];
        }
        return $usefulIssuesDetails;
    }

    protected function formatDate(?string $dateStr): ?string
    {
        if ($dateStr === null) {
            return null;
        }

        $date = new \DateTime($dateStr);
        return $date->format('d/m/Y');
    }
}