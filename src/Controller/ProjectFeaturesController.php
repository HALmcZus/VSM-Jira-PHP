<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Repository\ProjectFeaturesRepository;

/**
 * ProjectFeaturesController
 */
class ProjectFeaturesController
{
    private ProjectFeaturesRepository $featuresRepo;

    public function __construct()
    {
        $this->featuresRepo = new ProjectFeaturesRepository();
    }

    public function featuresListByProject(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $projectKey = $args['projectKey'] ?? null;

            if (!$projectKey) {
                throw new \Exception('Missing project key');
            }

            $features = $this->featuresRepo->getProjectFeaturesList($projectKey);

            $response->getBody()->write(json_encode($features));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error'   => $e->getMessage(),
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}
