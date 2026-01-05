<?php
namespace App\Service;

use Dotenv\Dotenv;
use Exception;

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
     * WIP, not tested
     * Get Version infos by ID
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
     * @return array
     */
    public function getVersionsByProjectId(int $projectId): array
    {
        $url = $this->baseUrl . self::API_URL_PROJECT_VERSIONS;
        $url = str_replace('{project_id}', $projectId, $url);

        try {
            $result = $this->request($url);

            if ($result['success'] === false || !$result['issues']) {
                throw new Exception("Erreur lors de la récupération de la Version Jira : " . $result['message']);
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
     * Get Jira issues by Version ID
    * @see https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-search/#api-rest-api-3-search-jql-get
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
     * Get issues detail from Jira API bulk fetch
     */
    public function getIssuesDetails(array $issuesIds)
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
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-search/#api-rest-api-3-search-jql-get
     */
    public function callSearchApiGet(string $jql, array $fields = []): array
    {
        $query = http_build_query([
            'jql' => $jql,
            'fields' => $fields
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
     */
    public function callBulkFetchApi(array $payload): array
    {
        $url = $this->baseUrl . self::API_URL_FETCH_ISSUES;

        try {
            $result = $this->request($url, json_encode($payload, JSON_THROW_ON_ERROR), true);

            if (!isset($result['issues'])) {
                throw new Exception('Réponse Jira invalide (bulkfetch)');
            }

            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l’appel à Jira : ' . $e->getMessage()
            ];
        }
    }


    /**
     * Performs a low-level HTTP request to the Jira REST API using cURL.
     */
    private function request(string $url, $payload=null, $isPost=false): array
    {
        try {
            //Vérifie si les credentials API sont valides, si pas déjà fait
            if (!$this->areCredentialsVerified) {
                $this->checkCredentials();
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $this->email . ':' . $this->token);

            $headers = ['Accept: application/json'];
            //Si on passe un payload, on le charge et on indique dans les headers qu'il s'agit de json
            if ($payload !== null) {
                $headers[] = 'Content-Type: application/json';
                if ($isPost) {
                    curl_setopt($ch, CURLOPT_POST, true);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception("Erreur cURL : " . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode >= 400) {
                throw new Exception("Erreur HTTP Jira ($httpCode) : $response");
            }

            $data = json_decode($response, true);
            if (!$data) {
                throw new Exception("Invalid response from Jira");
            }
            return $data;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'appel à Jira : ' . $e->getMessage()
            ];
        }
    }
}
