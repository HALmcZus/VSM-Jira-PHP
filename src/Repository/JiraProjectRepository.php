<?php

namespace App\Repository;

use App\Service\JiraService;

/**
 * JiraProjectRepository
 */
class JiraProjectRepository implements JiraProjectRepositoryInterface
{
    private JiraService $jira;

    public function __construct(JiraService $jira)
    {
        $this->jira = $jira;
    }

    /**
     * {@inheritdoc}
     */
    public function search(string $query): array
    {
        // Jira API : GET /rest/api/3/project/search
        $projects = $this->jira->searchProjects($query);

        return array_map(static function ($project) {
            return [
                'id'   => $project['id'],
                'key'  => $project['key'],
                'name' => $project['name'],
            ];
        }, $projects);
    }
}
