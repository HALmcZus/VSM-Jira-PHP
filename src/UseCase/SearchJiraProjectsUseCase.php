<?php

namespace App\UseCase;

use App\Repository\JiraProjectRepositoryInterface;

/**
 * SearchJiraProjectsUseCase
 */
class SearchJiraProjectsUseCase
{
    private JiraProjectRepositoryInterface $repository;

    public function __construct(JiraProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute project search
     *
     * @param string $query
     * @return array
     */
    public function execute(string $query): array
    {
        if (mb_strlen($query) < 2) {
            return [];
        }

        return $this->repository->search($query);
    }
}
