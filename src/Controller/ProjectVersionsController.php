<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Repository\ProjectVersionsRepository;

class ProjectVersionsController
{
    private ProjectVersionsRepository $projectVersionsRepo;

    public function __construct()
    {
        $this->projectVersionsRepo = new ProjectVersionsRepository();
    }

    /**
     * versionsListByProject
     *
     * @param  mixed $request
     * @param  mixed $response
     * @param  mixed $args
     * @return ResponseInterface
     */
    public function versionsListByProject(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $projectKey = $args['projectKey'] ?? null;

            if (!$projectKey) {
                throw new \Exception('Missing project key');
            }

            $versions = $this->projectVersionsRepo->getProjectVersionsList($projectKey);

            $response->getBody()->write(json_encode($versions));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}
