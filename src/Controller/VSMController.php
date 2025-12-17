<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Model\ReleaseModel;

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
        $versionId = $data['fixVersionId'] ?? null;

        try {
            $release = new ReleaseModel();
            $version = $release->getVersionById($versionId);
            $issues = $release->getIssuesByVersion($versionId);

            $response->getBody()->write(json_encode([
                    'success' => true,
                    'version' => $version,
                    'issues' => $issues
                ], JSON_PRETTY_PRINT)
            );
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }
}
