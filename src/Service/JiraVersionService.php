<?php

namespace App\Service;

/**
 * JiraVersionService
 */
class JiraVersionService extends JiraService
{
    private string $cacheFile;
    private int $ttl = 86400; // 1 jour

    private array $cache = [
        'updatedAt' => 0,
        'versions' => []
    ];

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->cacheFile = __DIR__ . '/../../cache/jira_versions.json';
        $this->loadCache();
    }

    /**
     * findByName
     *
     * @param  mixed $name
     * @return array
     */
    public function findByName(string $name): ?array
    {
        //TODO cas plusieurs versions avec le même nom sur deux projets différents ?

        // 1️⃣ Cherche dans cache
        if (isset($this->cache['versions'][$name])) {
            return $this->cache['versions'][$name];
        }

        // 2️⃣ Cherche via l'API d'autocomplete (plus rapide que de récupérer toutes les versions d'un projet)
        // $this->fetchFromAutocomplete($name);

        // if (isset($this->cache['versions'][$name])) {
        //     $this->saveCache();
        //     return $this->cache['versions'][$name];
        // }

        // 3️⃣ fallback global si pas trouvé et cache trop vieux
        // if ($this->isCacheExpired()) {
        $this->fetchAllVersions();

        if (isset($this->cache['versions'][$name])) {
            $this->saveCache();
            return $this->cache['versions'][$name];
        }
        // }

        return null;
    }

    /**
     * fetchFromAutocomplete
     *
     * @param  mixed $query
     * @return void
     */
    private function fetchFromAutocomplete(string $name): void
    {
        // $query = http_build_query([
        //     'jql' => $jql,
        //     'fields' => $fields ? implode(',', $fields) : '',
        //     'maxResults' => $maxResults
        // ]);

        $fullUrl = $this->baseUrl . self::API_URL_VERSION_V3 . '?"' . $name . '"';

        $response = $this->request($fullUrl);

        // $response = $this->request('GET', '/rest/api/3/version', [
        //     'query' => $query
        // ]);

        foreach ($response['values'] ?? [] as $version) {
            $this->storeInCache($version);
        }


        // private function fetchFromAutocomplete(string $query): void
        // {
        //     // On cherche des issues avec cette fixVersion
        //     $jql = sprintf('fixVersion = "%s"', addslashes($query));

        //     $response = $this->callSearchApiGet($jql, ['fixVersions', 'project']);

        //     foreach ($response['issues'] ?? [] as $issue) {
        //         foreach ($issue['fields']['fixVersions'] ?? [] as $version) {
        //             $this->store([
        //                 'id' => $version['id'],
        //                 'name' => $version['name'],
        //                 'projectId' => $issue['fields']['project']['id'] ?? null
        //             ]);
        //         }
        //     }
        // }
    }

    /**
     * fetchAllVersions
     *
     * @return void
     */
    private function fetchAllVersions(): void
    {
        $startAt = 0;
        $maxResults = 50;

        do {
            $query = http_build_query([
                'expand' => 'versions',
                'startAt' => $startAt,
                'maxResults' => $maxResults
            ]);

            $response = $this->searchProjects($query);

            foreach ($response['values'] ?? [] as $project) {
                foreach ($project['versions'] ?? [] as $version) {
                    $this->storeInCache($version);
                }
            }

            $isLast = $response['isLast'] ?? true;
            $startAt += $maxResults;
        } while (!$isLast);

        $this->cache['updatedAt'] = time();
    }

    /**
     * store
     *
     * @param  mixed $version
     * @return void
     */
    private function storeInCache(array $version): void
    {
        $this->cache['versions'][$version['name']] = [
            'id' => $version['id'],
            'projectId' => $version['projectId'] ?? null
        ];
    }

    /**
     * loadCache
     *
     * @return void
     */
    private function loadCache(): void
    {
        if (!file_exists($this->cacheFile)) {
            return;
        }

        $content = file_get_contents($this->cacheFile);
        $data = json_decode($content, true);

        if (is_array($data)) {
            $this->cache = $data;
        }
    }

    /**
     * saveCache
     *
     * @return void
     */
    private function saveCache(): void
    {
        file_put_contents(
            $this->cacheFile,
            json_encode($this->cache, JSON_PRETTY_PRINT)
        );
    }

    /**
     * isCacheExpired
     *
     * @return bool
     */
    private function isCacheExpired(): bool
    {
        return (time() - $this->cache['updatedAt']) > $this->ttl;
    }
}
