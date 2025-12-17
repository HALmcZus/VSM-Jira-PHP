<?php
namespace App\Model;

use App\Service\JiraService;

class ReleaseModel
{
    protected JiraService $jiraService;

    public function __construct()
    {
        $this->jiraService = new JiraService();
        $this->jiraService->checkCredentials();
    }
    
    // $version = $jira->getVersionById($versionId);
    // $issues = $jira->getIssuesByVersion($versionId);

    public function getVersionById(int $versionId) : array 
    {
        $result = $this->jiraService->getVersionById($versionId);

        return $result;
    }


    public function getVersionsByProjectId(int $projectId) : array 
    {
        $result = $this->jiraService->getVersionsByProjectId($projectId);

        return $result;
    }


    public function getIssuesByVersion(int $versionId) : array 
    {
        $issues = $this->jiraService->getIssuesByVersion($versionId);

        $result = [];
        foreach ($issues['issues'] as $issue) {
            $result[] = $issue['id'] ?? '';
        }

        return $result;
    }


    /**
     * TODO
     */
    public function getIssuesDetails(array $issues)
    {
            // $issues[] = [
            //     'id' => $issue['fields']['id'] ?? '',
            //     'key' => $issue['fields']['key'] ?? '',
            //     'summary' => $issue['fields']['summary'] ?? '',
            //     'status' => $issue['fields']['status']['name'] ?? '',
            //     'created' => $issue['fields']['created'] ?? '',
            //     'updated' => $issue['fields']['updated'] ?? ''
            // ];
    }
}