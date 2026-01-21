<?php

namespace App\Repository;

/**
 * Interface JiraProjectRepositoryInterface
 *
 * Abstraction d'accès aux projets Jira
 */
interface JiraProjectRepositoryInterface
{
    /**
     * Recherche des projets Jira par texte
     *
     * @param string $query
     * @return array
     */
    public function search(string $query): array;
}
