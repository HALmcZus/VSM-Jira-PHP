<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Model\Version;
use App\View\VersionView;

/**
 * VersionController
 */
class VersionController
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
        require __DIR__ . '/../../views/version.phtml';
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

        $view = null;
        $error = null;

        try {
            if (!$versionId) {
                throw new \Exception('Le paramètre fixVersionId est requis.');
            }

            // Load data
            $version = new Version($versionId);
            $view = new VersionView($version);
        } catch (\Throwable $e) {
            // Capturer l'erreur pour l'afficher dans la vue
            $error = $e->getMessage();
        }

        // Rendu de la vue
        ob_start();
        require __DIR__ . '/../../views/version.phtml';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }
}
