<?php

namespace App\Model;

use Exception;

/**
 * Feature
 *
 * Agrégat d'issues Jira rattachées à une Feature (via champ parent).
 * Hérite de toute la logique de métriques via AbstractIssueCollection.
 */
class Feature extends AbstractIssueCollection
{
    const PLANNING_INTERVAL_CUSTOM_FIELD = 'customfield_11400';
    private array $featureData = [];

    /**
     * {@inheritdoc}
     *
     * Charge les métadonnées de la Feature Jira (key, summary, statut, PI).
     */
    protected function loadCollectionData(int $id): void
    {
        $result = $this->jiraService->getIssueById($id, [
            'summary',
            'status',
            'issuetype',
            self::PLANNING_INTERVAL_CUSTOM_FIELD
        ]);

        if (!isset($result['id'])) {
            throw new Exception("Feature Jira introuvable (ID: $id)");
        }

        $this->featureData = $result;
    }

    /**
     * {@inheritdoc}
     *
     * Charge les issues enfants via JQL parent = {featureId}, avec changelog embarqué.
     */
    protected function loadIssues(int $id): void
    {
        try {
            $rawIssues = $this->jiraService->getIssuesByParent($id);

            foreach ($rawIssues as $rawIssueData) {
                $this->issues[] = new Issue($rawIssueData);
            }

            $this->issuesCount = count($this->issues);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des tickets de la Feature : " . $e->getMessage());
        }
    }

    // ==================== Getters Feature-spécifiques ====================

    public function getId(): ?string
    {
        return $this->featureData['id'] ?? null;
    }
    public function getKey(): ?string
    {
        return $this->featureData['key'] ?? null;
    }
    public function getName(): ?string
    {
        return $this->featureData['fields']['summary'] ?? null;
    }
    public function getStatusName(): ?string
    {
        return $this->featureData['fields']['status']['name'] ?? null;
    }

    /**
     * Retourne la valeur numérique du Planning Interval, ou null si non renseigné.
     *
     * Gestion défensive : le champ peut être un int, un string, ou un objet select
     * selon la configuration Jira. Le cast en int couvre les trois cas.
     *
     * @return int|null  Ex : 7 pour PI7
     */
    public function getPlanningInterval(): ?int
    {
        $raw = $this->featureData['fields'][self::PLANNING_INTERVAL_CUSTOM_FIELD] ?? null;

        if ($raw === null) {
            return null;
        }

        // Si c'est un select Jira (objet avec clé 'value')
        if (is_array($raw)) {
            $raw = end($raw);
        }

        return $raw !== null ? (int) $raw : null;
    }

    /**
     * Retourne l'URL Jira de la Feature.
     */
    public function getUrl(): string
    {
        $key = $this->getKey();
        return $key ? $_ENV['JIRA_BASE_URL'] . '/browse/' . $key : '#';
    }
}
