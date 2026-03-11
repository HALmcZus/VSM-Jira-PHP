<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Model\Version;
use App\View\VersionView;

/**
 * FeatureController
 */
class FeatureController
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
        require __DIR__ . '/../../views/feature.phtml';
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
        $data      = $request->getParsedBody();
        $featureId = $data['featureId'] ?? null;

        $view  = null;
        $error = null;

        try {
            if (!$featureId) {
                throw new \Exception('Le paramètre featureId est requis.');
            }

            $feature = new \App\Model\Feature((int) $featureId);
            $view    = new \App\View\FeatureView($feature);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        ob_start();
        require __DIR__ . '/../../views/feature.phtml';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }
}
