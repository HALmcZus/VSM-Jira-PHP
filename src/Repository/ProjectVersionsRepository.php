<?php

namespace App\Repository;

use App\Service\JiraVersionService;

/**
 * JiraProjectRepository
 */
class ProjectVersionsRepository
{
    const VERSION_STATUS_UNRELEASED = '🛠️Non livré';
    const VERSION_STATUS_RELEASED = '🚀Livré';
    const VERSION_STATUS_ARCHIVED = '📦Archivé';

    private JiraVersionService $jiraService;

    /**
     * __construct
     *
     * @param  mixed $jira
     * @return void
     */
    public function __construct(JiraVersionService $jiraService)
    {
        $this->jiraService = $jiraService;
    }

    /**
     * getProjectVersionsList
     *
     * @param  mixed $query
     * @return array
     */
    public function getProjectVersionsList(string $projectKey): array
    {
        $versions = $this->jiraService->getVersionsByProject($projectKey);

        return $this->sortVersionsList($versions);
    }

    /**
     * sortVersionsList : unreleased first, then released, then archived. Each status is also sorted by updated date.
     *
     * @param  mixed $versions
     * @return array
     */
    protected function sortVersionsList(array $versions): array
    {
        $mapped = array_map(static function ($v) {
            if (!empty($v['archived'])) {
                $status = self::VERSION_STATUS_ARCHIVED;
            } elseif (!empty($v['released'])) {
                $status = self::VERSION_STATUS_RELEASED;
            } else {
                $status = self::VERSION_STATUS_UNRELEASED;
            }

            return [
                'id'      => $v['id'],
                'key'     => $v['key'] ?? null,
                'name'    => $v['name'],
                'status'  => $status,
                'updated' => $v['releaseDate'] ?? $v['startDate'] ?? $v['userReleaseDate'] ?? '1970-01-01',
                'label'   => $v['name'] . ' (' . $status . ')'
            ];
        }, $versions);



        // Sorting rules
        $statusWeight = [
            self::VERSION_STATUS_UNRELEASED => 0,
            self::VERSION_STATUS_RELEASED   => 1,
            self::VERSION_STATUS_ARCHIVED   => 2,
        ];

        usort($mapped, static function ($a, $b) use ($statusWeight) {
            // 1. Status
            if ($statusWeight[$a['status']] !== $statusWeight[$b['status']]) {
                return $statusWeight[$a['status']] <=> $statusWeight[$b['status']];
            }

            // 2. Updated desc
            return strcmp($b['updated'], $a['updated']);
        });

        return $mapped;
    }
}
