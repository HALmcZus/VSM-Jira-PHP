<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Model\ReleaseModel;
use App\View\VersionView;

class VsmController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        ob_start();
        $view = null;
        require __DIR__ . '/../../views/index.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $versionId = $data['fixVersionId'] ?? null;

        try {
            $release = new ReleaseModel();

            $versionData = $release->getVersionById($versionId);
            $versionIssues = $release->getIssuesDetailsByVersion($versionId);
            
            $view = new VersionView($versionData, $versionIssues);
            
            ob_start();
            require __DIR__ . '/../../views/index.php';
            $html = ob_get_clean();
            
            $response->getBody()->write($html);
            return $response;
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}
