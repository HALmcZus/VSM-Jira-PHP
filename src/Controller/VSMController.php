<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Model\Version;
use App\View\VersionView;

/**
 * VsmController
 */
class VsmController
{
    /**
     * index
     *
     * @param  mixed $request
     * @param  mixed $response
     * @return ResponseInterface
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        ob_start();
        $view = null;
        require __DIR__ . '/../../views/index.php';
        $html = ob_get_clean();
        $response->getBody()->write($html);
        return $response;
    }
    
    /**
     * getIsDemo
     *
     * @return bool
     */
    public function getIsDemo(): bool
    {
        return $_ENV['IS_DEMO'] ?? false;
    }
    
    
    /**
     * process
     *
     * @param  mixed $request
     * @param  mixed $response
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody();
        $versionId = $data['fixVersionId'] ?? null;

        try {
            if (!$versionId) {
                throw new \Exception('Le paramètre fixVersionId est requis.');
            }

            // Load data
            $version = new Version($versionId);
            $view = new VersionView($version);
            
            // Render view
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
