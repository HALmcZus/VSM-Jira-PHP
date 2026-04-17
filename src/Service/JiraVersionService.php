<?php

namespace App\Service;

use Exception;
use App\Model\Config;

/**
 * JiraVersionService
 *
 * Service de résolution et de cache des Versions Jira.
 *
 * Responsabilités :
 * - Charger et mettre en cache les versions des projets surveillés (watched_projects.json)
 * - Résoudre un nom de version (exact, case-insensitive) vers ses données complètes
 * - Déléguer la résolution par ID numérique à JiraService::getVersionById()
 *
 * Le cache (jira_versions.json) est indexé par nom en minuscules pour le matching
 * case-insensitive. Chaque entrée contient les données complètes retournées par
 * GET /rest/api/2/version/{id}.
 */
class JiraVersionService extends JiraService
{
    private const CACHE_FILE            = __DIR__ . '/../../cache/jira_versions.json';
    private const WATCHED_PROJECTS_FILE = __DIR__ . '/../../config/watched_projects.json';
    private const TTL                   = 86400; // 1 jour en secondes

    private Config $config;

    /**
     * Structure du cache :
     * [
     *   'updatedAt' => int,
     *   'versions'  => [
     *     'cqfd4.6.0 tid' => [ ...données complètes API Jira... ],
     *     ...
     *   ]
     * ]
     *
     * @var array{updatedAt: int, versions: array<string, array>}
     */
    private array $cache = [
        'updatedAt' => 0,
        'versions'  => [],
    ];

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->config = new Config();
        $this->loadCache();
    }

    // =========================================================================
    // API publique
    // =========================================================================

    /**
     * Résout un nom de version (case-insensitive, exact) vers ses données complètes.
     *
     * Retourne null si aucune version ne correspond.
     * Si plusieurs projets ont une version avec le même nom, retourne null également
     * (l'appelant doit utiliser findAllByName() pour détecter ce cas).
     *
     * Algorithme :
     * 1. Lookup dans le cache (après normalisation en minuscules)
     * 2. Si cache expiré ou absent → fetchWatchedProjects() puis re-lookup
     * 3. Retourne null si toujours introuvable
     *
     * @param  string $name    Nom exact de la version (ex: "CQFD4.6.0 TID")
     * @param  bool   $refresh Force le rechargement du cache depuis l'API
     * @return array|null      Données complètes de la version, ou null
     */
    public function findByName(string $name, bool $refresh = false): ?array
    {
        $results = $this->findAllByName($name, $refresh);

        // Doublon inter-projets : on laisse l'appelant gérer
        if (count($results) !== 1) {
            return null;
        }

        return $results[0];
    }

    /**
     * Retourne toutes les versions dont le nom correspond (case-insensitive, exact).
     *
     * Permet à l'appelant de détecter :
     * - 0 résultat → version introuvable
     * - 1 résultat → cas nominal
     * - N résultats → ambiguïté inter-projets
     *
     * @param  string $name    Nom exact de la version
     * @param  bool   $refresh Force le rechargement du cache
     * @return array[]         Tableau de données complètes (peut être vide)
     */
    public function findAllByName(string $name, bool $refresh = false): array
    {
        /**
         * Premier chargement avec 20-40 projets : si chaque projet a 20 versions, ça fait 400-800 appels API GET /version/{id}.
         * C'est lourd. Vérifiez si GET /project/{key}/versions retourne déjà description, startDate, releaseDate, released, archived — si oui, on peut éviter le second appel par version.
         * Je l'ai supposé incomplet, mais si votre instance retourne tout, on peut simplifier fetchAndStoreVersion()
         */

        $key = strtolower(trim($name));

        // Refresh forcé ou cache expiré : on recharge
        if ($refresh || $this->isCacheExpired()) {
            $this->fetchWatchedProjects();
            $this->saveCache();
        }

        // Lookup dans le cache
        $matches = $this->cache['versions'][$key] ?? [];

        // Introuvable et cache non encore rechargé : tentative unique
        if (empty($matches) && !$refresh && !$this->isCacheExpired()) {
            $this->fetchWatchedProjects();
            $this->saveCache();
            $matches = $this->cache['versions'][$key] ?? [];
        }

        return $matches;
    }

    // =========================================================================
    // Fetch & cache
    // =========================================================================

    /**
     * Charge les versions de tous les projets surveillés depuis l'API Jira.
     *
     * Pour chaque projet listé dans watched_projects.json :
     * 1. GET /project/{key}/versions → liste des versions (id + name)
     * 2. GET /version/{id}           → données complètes de chaque version
     *
     * Stocke le résultat dans $this->cache['versions'] indexé par strtolower(name).
     * Plusieurs versions avec le même nom (projets différents) sont regroupées
     * dans un tableau pour permettre la détection de doublons.
     *
     * @return void
     * @throws Exception Si watched_projects.json est illisible ou malformé
     */
    private function fetchWatchedProjects(): void
    {
        $projects = $this->loadWatchedProjects();

        // Reset du cache versions (on repart de zéro à chaque fetch)
        $this->cache['versions']  = [];
        $this->cache['updatedAt'] = time();

        foreach ($projects as $projectKey) {
            $this->fetchVersionsForProject($projectKey);
        }
    }

    /**
     * Charge et retourne la liste des projets surveillés depuis watched_projects.json.
     *
     * @return string[] Tableau de clés de projets (ex: ["CQFD", "ABC"])
     * @throws Exception Si le fichier est absent ou malformé
     */
    private function loadWatchedProjects(): array
    {
        $projects  = $this->config->getWatchedProjects();

        if (!is_array($projects) || empty($projects)) {
            throw new Exception('config_files/watched_projects.json est vide ou malformé.');
        }

        return $projects;
    }

    /**
     * Charge toutes les versions d'un projet et les stocke dans le cache.
     *
     * Pour chaque version : appel à getVersionById() pour obtenir les données
     * complètes (description, dates, statut released/archived...).
     *
     * Les erreurs par version sont silencieuses (on logue et on continue)
     * pour ne pas bloquer le fetch des autres projets.
     *
     * @param  string $projectKey Clé du projet Jira (ex: "CQFD")
     * @return void
     */
    private function fetchVersionsForProject(string $projectKey): void
    {
        try {
            // Liste légère : id + name seulement
            $versions = $this->getVersionsByProject($projectKey);
        } catch (Exception $e) {
            // Projet inaccessible ou inexistant : on ignore et on continue
            error_log("JiraVersionService: impossible de charger le projet $projectKey : " . $e->getMessage());
            return;
        }

        foreach ($versions as $version) {
            $this->fetchAndStoreVersion((int) $version['id']);
        }
    }

    /**
     * Charge les données complètes d'une version via GET /version/{id}
     * et les stocke dans le cache.
     *
     * @param  int $versionId ID numérique de la version
     * @return void
     */
    private function fetchAndStoreVersion(int $versionId): void
    {
        try {
            $data = $this->getVersionById($versionId);

            if (empty($data['id']) || empty($data['name'])) {
                return;
            }

            $this->storeInCache($data);
        } catch (Exception $e) {
            error_log("JiraVersionService: impossible de charger la version $versionId : " . $e->getMessage());
        }
    }

    /**
     * Stocke les données complètes d'une version dans le cache.
     *
     * Clé de cache : strtolower(trim(name)) pour matching case-insensitive.
     * Plusieurs versions avec le même nom sont regroupées dans un tableau
     * (cas de doublons inter-projets).
     *
     * @param  array $versionData Données complètes retournées par GET /version/{id}
     * @return void
     */
    private function storeInCache(array $versionData): void
    {
        $key = strtolower(trim($versionData['name']));

        // On regroupe les doublons dans un tableau indexé
        $this->cache['versions'][$key][] = $versionData;
    }

    // =========================================================================
    // Persistence du cache
    // =========================================================================

    private function loadCache(): void
    {
        if (!file_exists(self::CACHE_FILE)) {
            return;
        }

        $data = json_decode(file_get_contents(self::CACHE_FILE), true);

        if (is_array($data)) {
            $this->cache = $data;
        }
    }

    private function saveCache(): void
    {
        file_put_contents(
            self::CACHE_FILE,
            json_encode($this->cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function isCacheExpired(): bool
    {
        return (time() - $this->cache['updatedAt']) > self::TTL;
    }
}
