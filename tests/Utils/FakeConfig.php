<?php

namespace Tests\Utils;

use App\Model\Config;

/**
 * FakeConfig
 *
 * Fournit un workflow et des jours fériés déterministes pour les tests.
 * Évite tout appel HTTP à GovApiService.
 */
class FakeConfig extends Config
{
    /** @var string[] */
    private array $nonWorkingDays;

    /** @var array */
    private array $workflow;

    public function __construct(array $nonWorkingDays = [], array $workflow = [])
    {
        // Ne pas appeler parent::__construct() — Config n'a pas de constructeur propre
        $this->nonWorkingDays = $nonWorkingDays;
        $this->workflow       = $workflow ?: self::defaultWorkflow();
    }

    public function getNonWorkingDays(): array
    {
        return $this->nonWorkingDays;
    }

    public function getJiraWorkflow(string $issueType = ''): array
    {
        return $this->workflow;
    }

    public static function defaultWorkflow(): array
    {
        return [
            'refinement_statuses' => ['To Do'],
            'sprint_statuses'     => ['In Progress'],
            'done_statuses'       => ['Done'],
            'waiting_statuses'    => ['En Attente'],
        ];
    }
}
