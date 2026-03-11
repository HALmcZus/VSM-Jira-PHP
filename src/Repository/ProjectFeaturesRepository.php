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
    const STATUS_ANALYSING = '🔎Analysing';
    const STATUS_READY = '🚦Prêt';
    const STATUS_PLANNED = '🔜Planifié';
    const STATUS_TODO = '▶️À faire';
    const STATUS_WORKING = '⚙️En cours';
    const STATUS_DONE = '✅Terminé(e)';
    const STATUS_UNACHIEVABLE = '📦Non Atteignable';
    const STATUS_ABANDONED = '🗑️ABANDONNE';

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
            $status = $f['fields']['status']['name'] ?? ''; //TODO picto selon status, cf const de class
            $name   = $f['fields']['summary'] ?? '';
            $key    = $f['key'] ?? '';
            $lastPI = $this->extractLastPlanningInterval($f['fields'][$this->jira::PLANNING_INTERVAL_CUSTOM_FIELD] ?? null);
            $pis    = $this->extractPlanningIntervals($f['fields'][$this->jira::PLANNING_INTERVAL_CUSTOM_FIELD] ?? null);
            $piLabel = $lastPI !== null ? '[PI' . $lastPI . '] ' : null;

            return [
                'id'     => $f['id'],
                'key'    => $key,
                'name'   => $name,
                'status' => $status,
                'lastPI' => $lastPI,
                'PIs'    => $pis,
                'label'  => $piLabel . $key . ' – ' . $name . ' (' . $status . ')',
            ];
        }, $features);

        usort($mapped, function (array $a, array $b): int {
            // PI décroissant — null toujours en dernier
            if ($a['lastPI'] !== $b['lastPI']) {
                if ($a['lastPI'] === null) {
                    return 1;
                }
                if ($b['lastPI'] === null) {
                    return -1;
                }
                return $b['lastPI'] <=> $a['lastPI'];
            }

            // À PI égal : key numérique décroissante (REP-2, REP-1...)
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
    private function extractLastPlanningInterval(mixed $rawValue): ?int
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
     * Extrait toutes les valeurs numériques du Planning Interval depuis la valeur brute Jira.
     *
     * Contrairement à extractLastPlanningInterval() qui retourne uniquement la valeur max,
     * cette méthode retourne le tableau complet pour permettre le filtre multi-PI.
     *
     * Exemple : si une Feature est affectée aux PI 5, 6 et 7 → retourne [5, 6, 7]
     *
     * @param mixed $rawValue Valeur brute du champ customfield_11400
     * @return int[]
     */
    private function extractPlanningIntervals(mixed $rawValue): array
    {
        if ($rawValue === null) {
            return [];
        }

        // Champ scalaire (int ou string) : une seule valeur
        if (!is_array($rawValue)) {
            return [(int) $rawValue];
        }

        // Champ select multiple Jira : tableau d'objets ["value" => "7", ...]
        // ou tableau de scalaires selon la configuration de l'instance
        return array_values(
            array_filter(
                array_map(static function (mixed $item): ?int {
                    if (is_array($item)) {
                        $item = $item['value'] ?? null;
                    }
                    return $item !== null ? (int) $item : null;
                }, $rawValue),
                static fn(?int $v): bool => $v !== null
            )
        );
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
