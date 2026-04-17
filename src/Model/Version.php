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
    /** ID numérique Jira résolu, utilisé pour charger les issues via JQL fixVersion */
    private string $resolvedVersionId = '';

    /**
     * {@inheritdoc}
     *
     * Charge les métadonnées de la Version Jira (nom, dates, statut...).
     *
     * Algorithme :
     * - Si $id est numérique : appel direct à getVersionById()
     * - Sinon : résolution du nom via JiraVersionService::findByName()
     *   - 0 résultat → VersionNotFoundException (message distinctif)
     *   - N résultats → VersionAmbiguousException (message distinctif)
     *   - 1 résultat → données complètes déjà en cache
     *
     * Dans tous les cas, $this->resolvedVersionId est alimenté avec l'ID numérique
     * pour être réutilisé par loadIssues().
     *
     * @param  mixed $id ID numérique ou nom de version
     * @throws VersionNotFoundException   Si aucune version ne correspond
     * @throws VersionAmbiguousException  Si plusieurs versions correspondent
     * @throws Exception                  Erreur API Jira
     */
    protected function loadCollectionData(mixed $id): void
    {
        $refresh = !empty($_GET['refresh']) || !empty($_POST['refresh']);

        if (is_numeric($id)) {
            // Résolution directe par ID numérique
            $result = $this->jiraVersionService->getVersionById((int) $id);
        } else {
            // Résolution par nom : on vérifie d'abord les doublons
            $all = $this->jiraVersionService->findAllByName((string) $id, $refresh);

            if (count($all) === 0) {
                throw new \RuntimeException(
                    "Aucune version trouvée pour le nom \"{$id}\"."
                        . " Vérifiez l'orthographe ou recherchez par projet."
                );
            }

            if (count($all) > 1) {
                $projects = implode(', ', array_column($all, 'projectId'));
                throw new \RuntimeException(
                    "Plusieurs versions trouvées pour le nom \"{$id}\" (projets : {$projects})."
                        . " Précisez votre recherche en utilisant la sélection par projet."
                );
            }

            $result = $all[0];
        }

        if (empty($result['id'])) {
            throw new \Exception("Erreur lors de la récupération de la Version Jira : " . print_r($result, true));
        }

        // Stocke l'ID numérique résolu pour loadIssues()
        $this->resolvedVersionId = (string) $result['id'];

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
     * Utilise $this->resolvedVersionId (ID numérique) et non le $id original
     * qui peut être un nom en clair.
     *
     * @param string $id Ignoré ici — on utilise $this->resolvedVersionId
     */
    protected function loadIssues(string $id): void
    {
        try {
            // On utilise l'ID numérique résolu, pas le $id original (qui peut être un nom)
            $this->loadIssueIds($this->resolvedVersionId);

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
     * @param string $versionId
     */
    private function loadIssueIds(string $versionId): void
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
