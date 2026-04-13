<?php

namespace Tests\Utils;

use App\Model\Config;
use App\Model\Issue;
use App\Model\Timeline;

/**
 * IssueBuilder
 *
 * Construit des objets Issue avec des changelogs synthétiques pour les tests.
 */
class IssueBuilder
{
    private string $status  = 'In Progress';
    private string $created = '2025-01-01T08:00:00+00:00';
    private ?string $resolutionDate = null;
    private array $histories = [];
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function withStatus(string $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withCreatedDate(string $isoDate): self
    {
        $clone = clone $this;
        $clone->created = $isoDate;
        return $clone;
    }

    public function withResolutionDate(string $isoDate): self
    {
        $clone = clone $this;
        $clone->resolutionDate = $isoDate;
        return $clone;
    }

    /**
     * Ajoute un changement de statut dans le changelog.
     */
    public function withStatusTransition(string $isoDate, string $from, string $to): self
    {
        $clone = clone $this;
        $clone->histories[] = [
            'created' => $isoDate,
            'items'   => [
                ['field' => 'status', 'fromString' => $from, 'toString' => $to]
            ],
        ];
        return $clone;
    }

    /**
     * Ajoute un changement de labels dans le changelog.
     * @param string $fromLabels Labels avant (séparés par espace, comme Jira)
     * @param string $toLabels   Labels après (séparés par espace, comme Jira)
     */
    public function withLabelChange(string $isoDate, string $fromLabels, string $toLabels): self
    {
        $clone = clone $this;
        $clone->histories[] = [
            'created' => $isoDate,
            'items'   => [
                ['field' => 'labels', 'fromString' => $fromLabels, 'toString' => $toLabels]
            ],
        ];
        return $clone;
    }

    public function build(): Issue
    {
        $data = [
            'key'    => 'TEST-1',
            'fields' => [
                'summary'        => 'Test issue',
                'status'         => ['name' => $this->status, 'statusCategory' => ['colorName' => 'green']],
                'issuetype'      => ['name' => 'Story', 'iconUrl' => ''],
                'priority'       => ['name' => 'Medium', 'iconUrl' => ''],
                'created'        => $this->created,
                'resolutiondate' => $this->resolutionDate,
            ],
            'changelog' => [
                'histories' => $this->histories,
            ],
        ];

        $timeline = new Timeline($this->config);
        return new Issue($data, $this->config, $timeline);
    }
}
