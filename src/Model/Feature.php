<?php

namespace App\Model;

use Exception;
use App\Repository\ProjectFeaturesRepository;

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
    private ProjectFeaturesRepository $repository;
    /** Issue représentant le ticket Feature lui-même (pour ses propres métriques) */
    private ?Issue $selfAsIssue = null;

    public function __construct(int $id)
    {
        $this->repository = new ProjectFeaturesRepository();
        return parent::__construct($id);
    }

    /**
     * {@inheritdoc}
     *
     * Charge les métadonnées de la Feature Jira, avec changelog et renderedFields
     * pour permettre le calcul des métriques propres au ticket Feature
     * et le rendu fidèle de la description.
     */
    protected function loadCollectionData(int $id): void
    {
        $result = $this->jiraService->getIssueById($id, [
            'summary',
            'status',
            'issuetype',
            'priority',
            'description',
            'created',
            'customfield_10075',                    // Jalon (Date)
            'customfield_10011',                    // Epic name (text)
            'customfield_10244',                    // Equipe (array)
            self::PLANNING_INTERVAL_CUSTOM_FIELD,   // Planning Interval
        ], ['renderedFields', 'changelog']);

        if (!isset($result['id'])) {
            throw new Exception("Feature Jira introuvable (ID: $id)");
        }

        $this->featureData = $result;

        // Crée un objet Issue depuis le ticket Feature lui-même
        // pour calculer ses métriques propres (Lead Time, Cycle Time, temps par statut)
        $this->selfAsIssue = new Issue($result);
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
    public function getStatusLabel()
    {
        return $this->repository->getStatusLabel($this->getStatusName());
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
     * Retourne les valeurs numériques du Planning Interval.
     *
     * @return array
     */
    public function getPlanningIntervals(): array
    {
        return $this->featureData['fields'][self::PLANNING_INTERVAL_CUSTOM_FIELD] ?? ['N/C'];
    }

    /**
     * Retourne l'URL Jira de la Feature.
     */
    public function getUrl(): string
    {
        $key = $this->getKey();
        return $key ? $_ENV['JIRA_BASE_URL'] . '/browse/' . $key : '#';
    }

    /**
     * Retourne le nom de la priorité du ticket Feature.
     *
     * @return string|null Ex: "High", "Medium"
     */
    public function getPriorityName(): ?string
    {
        return $this->featureData['fields']['priority']['name'] ?? null;
    }

    /**
     * Retourne l'URL de l'icône de priorité.
     *
     * @return string|null
     */
    public function getPriorityIconUrl(): ?string
    {
        return $this->featureData['fields']['priority']['iconUrl'] ?? null;
    }

    /**
     * Retourne la date du Jalon (customfield_10075), au format Y-m-d ou null.
     *
     * @return string|null Ex: "2025-12-31"
     */
    public function getJalon(): ?string
    {
        return $this->featureData['fields']['customfield_10075'] ?? null;
    }

    /**
     * Retourne le nom de l'Epic lié (customfield_10011).
     *
     * @return string|null
     */
    public function getEpicName(): ?string
    {
        return $this->featureData['fields']['customfield_10011'] ?? null;
    }

    /**
     * Retourne la liste des équipes (customfield_10244).
     *
     * Gestion défensive : supporte les scalaires (string), les tableaux de strings,
     * et les tableaux d'objets Jira (avec clé 'name' ou 'value').
     *
     * @return string[]
     */
    public function getContributingTeams(): array
    {
        $raw = $this->featureData['fields']['customfield_10244'] ?? [];

        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (mixed $item): ?string {
                if (is_array($item)) {
                    return $item['name'] ?? $item['value'] ?? null;
                }
                return is_string($item) ? $item : null;
            },
            $raw
        )));
    }

    /**
     * Retourne la description de la Feature rendue en HTML par Jira (via renderedFields).
     * Retourne null si non renseignée.
     *
     * @return string|null HTML échappé par Jira, prêt à l'affichage
     */
    public function getDescriptionHtml(): ?string
    {
        return $this->featureData['renderedFields']['description'] ?? null;
    }

    /**
     * Retourne l'objet Issue représentant le ticket Feature lui-même.
     * Permet d'accéder à ses métriques propres (Lead Time, Cycle Time, etc.)
     *
     * @return Issue|null
     */
    public function getSelfAsIssue(): ?Issue
    {
        return $this->selfAsIssue;
    }
}
