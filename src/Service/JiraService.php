<?php
namespace App\Service;

use Dotenv\Dotenv;
use Exception;

class JiraService
{
    const API_URL_SEARCH = "/rest/api/3/search/jql";
    const API_URL_VERSION = "/rest/api/2/version";
    const API_URL_PROJECT_VERSIONS = "/rest/api/2/project/{project_id}/versions";

    private string $baseUrl;
    private string $email;
    private string $token;


    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->baseUrl = $_ENV['JIRA_BASE_URL'] ?? throw new Exception("JIRA_BASE_URL manquant dans le fichier .env");
        $this->email = $_ENV['JIRA_EMAIL'] ?? throw new Exception("JIRA_EMAIL manquant dans le fichier .env");
        $this->token = $_ENV['JIRA_API_TOKEN'] ?? throw new Exception("JIRA_API_TOKEN manquant dans le fichier .env");

        $this->checkCredentials();
    }


    /**
     * Ping Jira "myself" API to check if credentials are valid.
     * Throw exception if not.
     */
    public function checkCredentials(): void
    {
        $url = $this->baseUrl . '/rest/api/3/myself';
        $response = $this->request($url);

        if (!isset($response['accountId'])) {
            throw new Exception('Invalid Jira credentials.');
        }
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
     */
    public function getIssuesByVersion(int $versionId): array
    {
        // Payload JSON simple
        $result = $this->callSearchApi([
            "jql" => "fixVersion = $versionId"
        ]);

        return $result;
    }


    /**
     * WIP
     */
    public function getIssuesDetails(array $issues)
    {
            // $issues[] = [
            //     'id' => $issue['fields']['id'] ?? '',
            //     'key' => $issue['fields']['key'] ?? '',
            //     'summary' => $issue['fields']['summary'] ?? '',
            //     'status' => $issue['fields']['status']['name'] ?? '',
            //     'created' => $issue['fields']['created'] ?? '',
            //     'updated' => $issue['fields']['updated'] ?? ''
            // ];
    }

    /**
     *   Executes a Jira Search API call using a pre-built payload.
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
     */
    public function callSearchApi(array $payload): array
    {
        $url = $this->baseUrl . self::API_URL_SEARCH;
        $payload = json_encode($payload);

        try {
            $result = $this->request($url, $payload);

            if ($result['success'] === false || !$result['issues']) {
                throw new Exception("Erreur lors de la récupération des tickets Jira : " . $result['message']);
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
     * Performs a low-level HTTP request to the Jira REST API using cURL.
     */
    private function request(string $url, $payload=null): array
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $this->email . ':' . $this->token);

            $headers = ['Accept: application/json'];
            if ($payload !== null) {
                //Si on passe un payload, on le charge et on indique dans les headers qu'il s'agit de json
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode >= 400) {
                throw new Exception("Erreur HTTP Jira ($httpCode) : $response");
            }

            if (curl_errno($ch)) {
                throw new Exception("Erreur cURL : " . curl_error($ch));
            }
            curl_close($ch);

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
