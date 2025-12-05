<?php
declare(strict_types=1);

class JiraClient
{
    private string $baseUrl;
    private string $email;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = $_ENV['JIRA_BASE_URL'] ?? '';
        $this->email   = $_ENV['JIRA_EMAIL'] ?? '';
        $this->token   = $_ENV['JIRA_API_TOKEN'] ?? '';
    }

    public function testCredentials(): array
    {
        try {
            $url = $this->baseUrl . '/rest/api/3/myself';

            $curl = curl_init($url);

            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD        => $this->email . ':' . $this->token,
                CURLOPT_HTTPHEADER     => ['Accept: application/json']
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if (curl_errno($curl)) {
                throw new Exception('Erreur CURL : ' . curl_error($curl));
            }

            curl_close($curl);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Connexion API OK !'];
            }

            return [
                'success' => false,
                'message' => 'Impossible de se connecter. Code HTTP : ' . $httpCode
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage()
            ];
        }
    }
}