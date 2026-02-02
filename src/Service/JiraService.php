<?php

namespace App\Service;

use Dotenv\Dotenv;
use Exception;

/**
 * JiraService
 */
class JiraService
{
    //API /rest/api/3/search déprécié ! Utiliser /rest/api/3/search/jql à la place
    const API_URL_SEARCH = '/rest/api/3/search/jql';
    const API_URL_VERSION = '/rest/api/2/version';
    const API_URL_PROJECT_VERSIONS = '/rest/api/2/project/{project_id}/versions';
    const API_URL_FETCH_ISSUES = '/rest/api/3/issue/bulkfetch';
    const API_URL_PROJECT_SEARCH = '/rest/api/3/project/search';

    private string $baseUrl;
    private string $email;
    private string $token;
    private bool $isDemo = false;
    private bool $areCredentialsVerified = false;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->baseUrl = $_ENV['JIRA_BASE_URL'] ?? throw new Exception("JIRA_BASE_URL manquant dans le fichier .env");
        $this->email   = $_ENV['JIRA_EMAIL'] ?? throw new Exception("JIRA_EMAIL manquant dans le fichier .env");
        $this->token   = $_ENV['JIRA_API_TOKEN'] ?? throw new Exception("JIRA_API_TOKEN manquant dans le fichier .env");
        $this->isDemo  = $_ENV['IS_DEMO'] ?? false;
    }

    /**
     * Ping Jira "myself" API to check if credentials are valid.
     * Throw exception if not.
     *
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-myself/#api-rest-api-3-myself-get
     *
     * @return void
     */
    public function checkCredentials(): void
    {
        //Force pour 1er check, pour éviter boucle infinie entre request() et checkCredentials()
        $this->areCredentialsVerified = true;

        try {
            $url = $this->baseUrl . '/rest/api/3/myself';
            $response = $this->request($url);

            if (!isset($response['accountId'])) {
                $this->areCredentialsVerified = false;
                throw new Exception('checkCredentials(...) : Invalid Jira credentials, verify the .env file.' . print_r($response, true));
            }

            $this->areCredentialsVerified = true;
        } catch (Exception $e) {
            $this->areCredentialsVerified = false;
            throw new Exception('checkCredentials(...) : Invalid Jira credentials, verify the .env file. ' . $e->getMessage());
        }
    }

    /**
     * getVersionById
     *
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v2/api-group-version/#api-rest-api-2-version-id-get
     *
     * @param  mixed $versionId
     * @return array
     */
    public function getVersionById(int $versionId): array
    {
        $url = $this->baseUrl . self::API_URL_VERSION . '/' . $versionId;

        return $this->request($url);
    }

    /**
     * Get Versions from a Jira Project ID or Key
     *
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v2/api-group-project-versions/#api-rest-api-2-project-project-id-versions-get
     *
     * @param string $projectKey
     * @return array
     */
    public function getVersionsByProject(string $projectKey): array
    {
        $url = $this->baseUrl . self::API_URL_PROJECT_VERSIONS;
        $url = str_replace('{project_id}', $projectKey, $url);

        $result = $this->request($url);

        if (!is_array($result)) {
            throw new Exception("Erreur lors de la récupération de la Version Jira : " . print_r($result, true));
        }
        return $result;
    }

    /**
     * getIssuesIdsByVersion
     *
     * @param  mixed $versionId
     * @return array
     */
    public function getIssuesIdsByVersion(int $versionId): array
    {
        $jql = "fixVersion = $versionId";

        $fields = [
            'summary',
            'issuetype',
            'assignee',
            'priority',
            'status',
            'created',
            'resolutiondate'
        ];

        $result = $this->callSearchApiGet($jql, $fields);

        return $result;
    }

    /**
     * getIssuesDetails
     *
     * @param  array $issuesIds
     * @return array
     */
    public function getIssuesDetails(array $issuesIds): array
    {
        //Si on passe des int ça ne fonctionnera pas, on force en string
        if (!is_string($issuesIds[0])) {
            $issuesIds = array_map('strval', array_values($issuesIds));
        }

        $payload = [
            'jql' => "id IN (" . implode(",", $issuesIds) . ")",
            'fields' => [
                'summary',
                'project',
                'priority',
                'status',
                'created',
                'resolutiondate',
                'issuetype'
            ],
            'expand' => 'changelog',
            'maxResults' => count($issuesIds)
        ];

        return $this->callSearchApiPost($payload);
    }

    /**
     * Executes a Jira Search API GET call.
     *
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-search/#api-rest-api-3-search-jql-get
     *
     * @param  mixed $jql
     * @param  mixed $fields
     * @return array
     */
    public function callSearchApiGet(string $jql, array $fields = []): array
    {
        $query = http_build_query([
            'jql' => $jql,
            'fields' => $fields ? implode(',', $fields) : ''
        ]);

        $fullUrl = $this->baseUrl . self::API_URL_SEARCH . '?' . $query;

        $result = $this->request($fullUrl);

        if (!isset($result['issues'])) {
            throw new Exception('callSearchApiGet(...) : Réponse Jira invalide (' . self::API_URL_SEARCH . ')' . print_r($result, true));
        }

        return $result['issues'];
    }

    /**
     *   Executes a Jira Search API POST call.
     *
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-search/#api-rest-api-3-search-jql-post
     *
     * @param  mixed $payload
     * @return array
     */
    public function callSearchApiPost(array $payload): array
    {
        $url = $this->baseUrl . self::API_URL_SEARCH;

        $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $result = $this->request($url, $jsonPayload, true);

        if (!isset($result['issues'])) {
            throw new Exception('callSearchApiPost(...) : Réponse Jira invalide (' . self::API_URL_SEARCH . ')' . print_r($result, true));
        }

        return $result['issues'];
    }

    /**
     * Search Jira projects
     *
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-project-search/
     */
    public function searchProjects(string $query): array
    {
        $params = http_build_query([
            'query' => $query,
            'maxResults' => 10
        ]);

        $url = $this->baseUrl . self::API_URL_PROJECT_SEARCH . '?' . $params;

        $result = $this->request($url);

        if (!isset($result['values'])) {
            throw new \Exception('searchProjects(...) : Invalid Jira project search response.' . print_r($result, true));
        }

        return $result['values'];
    }

    /**
     * Performs a low-level HTTP request to the Jira REST API using cURL.
     *
     * @param  mixed $url
     * @param  mixed $payload
     * @param  mixed $isPost
     * @return array
     */
    private function request(string $url, $payload = null, bool $isPost = false): array
    {
        if (!$this->areCredentialsVerified) {
            $this->checkCredentials();
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->email . ':' . $this->token,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        //Si on passe un payload, on le charge et on indique dans les headers qu'il s'agit de json
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POST, $isPost);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json'
            ]);
        }

        if ($this->isDemo === true) {
            // ⚠️ Démo uniquement ⚠️ (proxy SSL corporate) => on bypass la vérification SSL
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('request(...) : Erreur cURL : ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            throw new Exception("request(...) : Erreur HTTP Jira ($httpCode) : $response");
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception('request(...) : Réponse Jira invalide (JSON)' . print_r($data, true));
        }

        return $data;
    }
}
