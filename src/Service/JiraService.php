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
    const API_URL_SEARCH = "/rest/api/3/search/jql";
    const API_URL_VERSION = "/rest/api/2/version";
    const API_URL_PROJECT_VERSIONS = "/rest/api/2/project/{project_id}/versions";
    const API_URL_FETCH_ISSUES = "/rest/api/3/issue/bulkfetch";
    
    private string $baseUrl;
    private string $email;
    private string $token;
    private bool $areCredentialsVerified = false;
    

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->baseUrl = $_ENV['JIRA_BASE_URL'] ?? throw new Exception("JIRA_BASE_URL manquant dans le fichier .env");
        $this->email = $_ENV['JIRA_EMAIL'] ?? throw new Exception("JIRA_EMAIL manquant dans le fichier .env");
        $this->token = $_ENV['JIRA_API_TOKEN'] ?? throw new Exception("JIRA_API_TOKEN manquant dans le fichier .env");
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

        $url = $this->baseUrl . '/rest/api/3/myself';
        $response = $this->request($url);

        if (!isset($response['accountId'])) {
            $this->areCredentialsVerified = false;
            throw new Exception('Invalid Jira credentials.');
        }

        $this->areCredentialsVerified = true;
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

        try {
            return $this->request($url);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'appel à Jira : ' . $e->getMessage()
            ];
        }
    }


    /**
     * Get Versions from a Jira Project ID
     * 
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v2/api-group-project-versions/#api-rest-api-2-project-project-id-versions-get
     *
     * @param  mixed $projectId
     * @return array
     */
    public function getVersionsByProjectId(int $projectId): array
    {
        $url = $this->baseUrl . self::API_URL_PROJECT_VERSIONS;
        $url = str_replace('{project_id}', $projectId, $url);

        try {
            $result = $this->request($url);

            if (!is_array($result)) {
                throw new Exception("Erreur lors de la récupération de la Version Jira : " . print_r($result, true));
            }
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'appel à Jira : ' . $e->getMessage()
            ];
        }
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
            // pour plus tard VSM
            // 'expand' => ['changelog']
        ];

        $result = $this->callSearchApiGet($jql, $fields);

        return $result;
    }

    
    /**
     * getIssuesDetails
     *
     * @param  mixed $issuesIds
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
                "summary",
                "project",
                "assignee",
                "priority",
                "status",
                "created",
                "resolutiondate",
                'issuetype',
                // "history",
                // "changelog"
            ],
            'maxResults' => count($issuesIds)
        ];  

        // $result = $this->callBulkFetchApi($payload);
        $result = $this->callSearchApiPost($payload);

        return $result;
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

        try {
            $result = $this->request($fullUrl);
    
            if (!isset($result['issues'])) {
                throw new Exception('Réponse Jira invalide (' . self::API_URL_SEARCH . ')');
            }            
    
            return $result['issues'];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'appel à Jira : ' . $e->getMessage()
            ];
        }
    }
    

    /**
     *   Executes a Jira Search API POST call.
     * 
     *   //Payload JSON si plusieurs requêtes
     *   // $payload = json_encode([
     *   //     "queries" => [
     *   //         [
     *   //             "query" => [
     *   //                 "jql" => $jql
     *   //             ]
     *   //         ]
     *   //     ]
     *   // ]);
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

        try {
            $result = $this->request($url, $jsonPayload, true);
    
            if (!isset($result['issues'])) {
                throw new Exception('Réponse Jira invalide (' . self::API_URL_SEARCH . ')');
            }            
    
            return $result['issues'];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'appel à Jira : ' . $e->getMessage()
            ];
        }
    }


    /**
     * Calls Jira Bulk Fetch API (/issue/bulkfetch)
     * 
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-bulk-fetch/#api-rest-api-3-issue-bulkfetch-post
     *
     * @param  mixed $payload
     * @return array
     */
    // public function callBulkFetchApi(array $payload): array
    // {
    //     $url = $this->baseUrl . self::API_URL_FETCH_ISSUES;

    //     try {
    //         $result = $this->request($url, json_encode($payload, JSON_THROW_ON_ERROR), true);

    //         if (!isset($result['issues'])) {
    //             throw new Exception('Réponse Jira invalide (bulkfetch)');
    //         }

    //         return $result;
    //     } catch (Exception $e) {
    //         return [
    //             'success' => false,
    //             'message' => 'Erreur lors de l’appel à Jira : ' . $e->getMessage()
    //         ];
    //     }
    // }


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
    
        $response = curl_exec($ch);
    
        if (curl_errno($ch)) {
            throw new Exception('Erreur cURL : ' . curl_error($ch));
        }
    
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            throw new Exception("Erreur HTTP Jira ($httpCode) : $response");
        }
    
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception('Réponse Jira invalide (JSON)');
        }
    
        return $data;
    }    
}
