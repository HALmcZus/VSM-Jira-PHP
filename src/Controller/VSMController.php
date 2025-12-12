<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Service\JiraService;

class VSMController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = file_get_contents(__DIR__ . '/../../views/index.html');
        $response->getBody()->write($html);
        return $response;
    }

    public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $fixVersionId = $data['fixVersionId'] ?? null;

        try {
            $jira = new JiraService();
            $jira->checkCredentials();
            $tickets = $jira->getIssuesByFixVersion($fixVersionId);

            $response->getBody()->write(json_encode([
                'success' => true,
                'tickets' => $tickets
            ], JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }
}
