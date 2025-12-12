<?php
namespace App\Service;

use Dotenv\Dotenv;
use Exception;

class JiraService
{
    private string $baseUrl;
    private string $email;
    private string $token;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->baseUrl = $_ENV['JIRA_BASE_URL'];
        $this->email = $_ENV['JIRA_EMAIL'];
        $this->token = $_ENV['JIRA_API_TOKEN'];
    }

    public function checkCredentials(): void
    {
        $url = $this->baseUrl . '/rest/api/3/myself';
        $response = $this->request($url);

        if (!isset($response['accountId'])) {
            throw new Exception('Invalid Jira credentials.');
        }
    }

    public function getIssuesByFixVersion(string $fixVersionId): array
    {
        $jql = urlencode("fixVersion = $fixVersionId");
        $url = $this->baseUrl . "/rest/api/3/search?jql=$jql";

        $result = $this->request($url);

        $tickets = [];
        foreach ($result['issues'] as $issue) {
            $tickets[] = [
                'key' => $issue['key'],
                'summary' => $issue['fields']['summary'] ?? '',
                'status' => $issue['fields']['status']['name'] ?? '',
                'created' => $issue['fields']['created'] ?? '',
                'updated' => $issue['fields']['updated'] ?? ''
            ];
        }
        return $tickets;
    }

    private function request(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->email . ':' . $this->token);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (!$data) {
            throw new Exception("Invalid response from Jira");
        }
        return $data;
    }
}
