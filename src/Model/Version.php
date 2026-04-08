<?php

namespace App\Model;

use Exception;

/**
 * Version
 *
 * Agrégat d'issues Jira rattachées à une Version (fixVersion).
 * Hérite de toute la logique de métriques via AbstractIssueCollection.
 */
class Version extends AbstractIssueCollection
{
    const VERSION_URL = '{base_url}/projects/{project_key}/versions/{version_id}';

    private array $versionData = [];
    private array $versionIssuesIds = [];

    /**
     * {@inheritdoc}
     *
     * Charge les métadonnées de la Version Jira (nom, dates, statut...).
     */
    protected function loadCollectionData(int $id): void
    {
        $result = $this->jiraService->getVersionById($id);

        if (!isset($result['id']) || isset($result['error'])) {
            throw new Exception("Erreur lors de la récupération de la Version Jira : " . print_r($result, true));
        }

        $result['version_url'] = str_replace(
            ['{base_url}', '{project_key}', '{version_id}'],
            [$_ENV['JIRA_BASE_URL'], $result['projectId'], $result['id']],
            self::VERSION_URL
        );

        $this->versionData = $result;
    }

    /**
     * {@inheritdoc}
     *
     * Charge les issues via deux appels : récupération des IDs, puis des détails+changelog.
     */
    protected function loadIssues(int $id): void
    {
        try {
            $this->loadIssueIds($id);

            $rawIssues = $this->jiraService->getIssuesDetails($this->versionIssuesIds);

            foreach ($rawIssues as $rawIssueData) {
                $this->issues[] = new Issue($rawIssueData);
            }

            $this->issuesCount = count($this->issues);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des tickets de la Version : " . $e->getMessage());
        }
    }

    /**
     * Charge les IDs des issues de la version via JQL fixVersion.
     *
     * @param int $versionId
     */
    private function loadIssueIds(int $versionId): void
    {
        $issues = $this->jiraService->getIssuesIdsByVersion($versionId);

        $this->versionIssuesIds = array_map(
            static fn($issue) => $issue['id'] ?? '',
            $issues
        );
    }

    // ==================== Getters Version-spécifiques ====================

    public function getId(): ?string
    {
        return $this->versionData['id'] ?? null;
    }
    public function getName(): ?string
    {
        return $this->versionData['name'] ?? null;
    }
    public function getDescription(): ?string
    {
        return $this->versionData['description'] ?? null;
    }
    public function getUrl(): string
    {
        return $this->versionData['version_url'] ?? '#';
    }
    public function getStartDate(): ?string
    {
        return $this->versionData['startDate'] ?? null;
    }
    public function getReleaseDate(): ?string
    {
        return $this->versionData['releaseDate'] ?? null;
    }
    public function isReleased(): bool
    {
        return (bool) ($this->versionData['released'] ?? false);
    }
    public function isOverdue(): bool
    {
        //Note: Jira ne stocke pas l'historique de changement de statut d'une Version
        //il n'est donc pas possible de savoir si la Version a été livré en retard, une fois celle-ci livrée
        return (bool) ($this->versionData['overdue'] ?? false);
    }
    public function getProjectId(): mixed
    {
        return $this->versionData['projectId'] ?? null;
    }
}
