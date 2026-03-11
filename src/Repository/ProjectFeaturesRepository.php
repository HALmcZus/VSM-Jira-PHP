<?php

namespace App\Repository;

use App\Service\JiraService;

/**
 * ProjectFeaturesRepository
 *
 * Récupère et trie la liste des Features Jira d'un projet.
 */
class ProjectFeaturesRepository
{
    private JiraService $jira;

    public function __construct()
    {
        $this->jira = new JiraService();
    }

    /**
     * Retourne la liste des Features triées pour un projet donné.
     *
     * Tri : Planning Interval décroissant (null en dernier), puis key croissante.
     *
     * @param string $projectKey Clé du projet Jira (ex: "REP")
     * @return array<int, array{id: string, key: string, name: string, status: string, pi: int|null, label: string}>
     */
    public function getProjectFeaturesList(string $projectKey): array
    {
        $features = $this->jira->getFeaturesByProject($projectKey);

        return $this->sortFeaturesList($features);
    }

    /**
     * Normalise et trie les Features.
     *
     * Algorithme :
     * 1. Mapping vers une structure normalisée avec extraction du PI
     * 2. Tri primaire : PI décroissant (null → fin de liste)
     * 3. Tri secondaire : partie numérique de la key croissante (REP-1 < REP-2)
     *
     * @param array $features Issues brutes retournées par l'API Jira
     * @return array
     */
    protected function sortFeaturesList(array $features): array
    {
        $mapped = array_map(function (array $f): array {
            $status = $f['fields']['status']['name'] ?? '';
            $name   = $f['fields']['summary'] ?? '';
            $key    = $f['key'] ?? '';
            $pi     = $this->extractPlanningInterval($f['fields'][$this->jira::PLANNING_INTERVAL_CUSTOM_FIELD] ?? null);
            $piLabel = $pi !== null ? '[PI' . $pi . '] ' : null;

            return [
                'id'     => $f['id'],
                'key'    => $key,
                'name'   => $name,
                'status' => $status,
                'pi'     => $pi,
                'label'  => $piLabel . $key . ' – ' . $name . ' (' . $status . ')',
            ];
        }, $features);

        usort($mapped, function (array $a, array $b): int {
            // PI décroissant — null toujours en dernier
            if ($a['pi'] !== $b['pi']) {
                if ($a['pi'] === null) {
                    return 1;
                }
                if ($b['pi'] === null) {
                    return -1;
                }
                return $b['pi'] <=> $a['pi'];
            }

            // À PI égal : key numérique croissante (REP-1, REP-2...)
            return $this->extractKeyNumber($b['key']) <=> $this->extractKeyNumber($a['key']);
        });

        return $mapped;
    }

    /**
     * Extrait la valeur numérique du Planning Interval depuis la valeur brute Jira.
     *
     * Gestion défensive des trois types possibles :
     * - Champ numérique  : 7
     * - Champ texte      : "7"
     * - Champ select     : ["value" => "7", "id" => "xxx"]
     *
     * @param mixed $rawValue
     * @return int|null
     */
    private function extractPlanningInterval(mixed $rawValue): ?int
    {
        if ($rawValue === null) {
            return null;
        }

        if (is_array($rawValue)) {
            $rawValue = end($rawValue);
        }

        return $rawValue !== null ? (int) $rawValue : null;
    }

    /**
     * Extrait la partie numérique d'une key Jira pour le tri.
     * Ex : "REP-123" → 123
     *
     * @param string $key
     * @return int
     */
    private function extractKeyNumber(string $key): int
    {
        $parts = explode('-', $key);
        return (int) end($parts);
    }
}
