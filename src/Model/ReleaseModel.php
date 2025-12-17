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
        /**"issues": [
            "530796",
            "496526",
            "467535",
            "467532",
            "467404",
            "467401",
            "466894",
            "466892",
            "462331",
            "462330"
          ]
        */
        $this->versionIssuesIds = $result;
        return $this->versionIssuesIds;
    }


    public function getIssuesDetailsByVersion(int $versionId) : array 
    {
        //Evite un second appel pour récupérer les IDs si on les a déjà
        if (!$this->versionIssuesIds) {
            $this->getIssuesIdsByVersion($versionId);
        }
        
        $this->versionIssuesDetails = $this->jiraService->getIssuesDetails($this->versionIssuesIds);
        
        return $this->versionIssuesDetails;
    }
}