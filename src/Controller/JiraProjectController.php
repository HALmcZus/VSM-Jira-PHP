<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Service\JiraService;
use App\Repository\JiraProjectRepository;
use App\UseCase\SearchJiraProjectsUseCase;

/**
 * JiraProjectController
 */
class JiraProjectController
{
    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $query = $request->getQueryParams()['q'] ?? '';

            $jiraService = new JiraService();
            $repository  = new JiraProjectRepository($jiraService);
            $useCase     = new SearchJiraProjectsUseCase($repository);

            $projects = $useCase->execute($query);

            $response->getBody()->write(json_encode($projects));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error'   => $e->getMessage()
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}
