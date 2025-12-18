<?php
namespace App\View;

/**
 * Formate les données du Back, et les expose au Front
 */
class VersionView extends AbstractView
{
    /**
     * @var array Raw version data provided by the Controller
     */
    private array $versionData;
    private array $versionIssues;

    public function __construct(array $versionData, array $versionIssues)
    {
        $this->versionData = $versionData;
        $this->versionIssues = $versionIssues;
    }

    /**
    * *********************
    * * All about Version *
    * *********************
     */
    public function getVersionId(): string
    {
        return $this->versionData['id'] ?? '';
    }

    public function getVersionName(): string
    {
        return $this->versionData['name'] ?? '';
    }

    public function getVersionDescription(): string
    {
        return $this->versionData['description'] !== null
            ? htmlspecialchars($this->versionData['description'], ENT_QUOTES, 'UTF-8')
            : '';
    }

    public function getVersionStartDate(): string
    {
        return $this->versionData['startDate'] ?? '';
    }

    public function getVersionReleaseDate(): string
    {
        return $this->versionData['releaseDate'] ?? '';
    }

    public function isVersionReleased(): bool
    {
        return (bool) ($this->versionData['released'] ?? false);
    }

    public function isVersionOverdue(): bool
    {
        return (bool) ($this->versionData['overdue'] ?? false);
    }

    public function getProjectId(): int
    {
        return (int) ($this->versionData['projectId'] ?? 0);
    }

    /**
     * ******************************
     * * All about Version's Issues *
     * ******************************
     */
    public function getIssues()
    {
        return $this->versionIssues;
    }

    public function getIssuesCount(): int
    {
        return count($this->versionIssues);
    }

    public function getStatusCSSClass($statusCategoryKey): string
    {
        if ($statusCategoryKey === 'new') {
            $cssClass = 'blue-gray';
        } elseif ($statusCategoryKey === 'done') {
            $cssClass = 'green';
        } else {
            $cssClass = 'yellow';
        }
        return $cssClass;
    }

    public function getAverageCycleTime(): float
    {
        $total = 0;
        $count = 0;
        foreach ($this->versionIssues as $issue) {
            if (isset($issue['cycleTime'])) {
                $total += $issue['cycleTime'];
                $count++;
            }
        }
        return $count > 0 ? round($total / $count, 2) : 0;
    }

    public function getTotalCycleTime(): int
    {
        $total = 0;
        foreach ($this->versionIssues as $issue) {
            $total += $issue['cycleTime'] ?? 0;
        }
        return $total;
    }
}