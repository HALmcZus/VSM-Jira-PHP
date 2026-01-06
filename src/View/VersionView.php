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
    
    /**
     * __construct
     *
     * @param  mixed $versionData
     * @param  mixed $versionIssues
     * @return void
     */
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
    /**
     * getVersionId
     *
     * @return string
     */
    public function getVersionId(): string
    {
        return $this->versionData['id'] ?? '?';
    }
    
    /**
     * getVersionName
     *
     * @return string
     */
    public function getVersionName(): string
    {
        return $this->versionData['name'] ?? '<i>Nom de version non renseigné.</i>';
    }
    
    /**
     * getVersionDescription
     *
     * @return string
     */
    public function getVersionDescription(): string
    {
        return $this->versionData['description'] !== null
            ? htmlspecialchars($this->versionData['description'], ENT_QUOTES, 'UTF-8')
            : '<i>Description non renseignée.</i>';
    }
    
    /**
     * getVersionStartDate
     *
     * @return string
     */
    public function getVersionStartDate(): string
    {
        return $this->versionData['startDate'] ?? '<i>Date de début non renseignée.</i>';
    }
    
    /**
     * getVersionReleaseDate
     *
     * @return string
     */
    public function getVersionReleaseDate(): string
    {
        return $this->versionData['releaseDate'] ?? '<i>Date de release non renseignée.</i>';
    }
    
    /**
     * isVersionReleased
     *
     * @return bool
     */
    public function isVersionReleased(): bool
    {
        return (bool) ($this->versionData['released'] ?? false);
    }
    
    /**
     * isVersionOverdue
     *
     * @return bool
     */
    public function isVersionOverdue(): bool
    {
        return (bool) ($this->versionData['overdue'] ?? false);
    }
    
    /**
     * getProjectId
     *
     * @return int
     */
    public function getProjectId(): int
    {
        return (int) ($this->versionData['projectId'] ?? 0);
    }

    /**
     * ******************************
     * * All about Version's Issues *
     * ******************************
     */    
    /**
     * getIssues
     *
     * @return void
     */
    public function getIssues()
    {
        return $this->versionIssues;
    }
    
    /**
     * getIssuesCount
     *
     * @return int
     */
    public function getIssuesCount(): int
    {
        return count($this->versionIssues);
    }
    
    /**
     * getStatusCSSClass
     *
     * @param  mixed $statusCategoryKey
     * @return string
     */
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
    
    /**
     * getAverageCycleTime
     *
     * @return float
     */
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
    
    /**
     * getTotalCycleTime
     *
     * @return int
     */
    public function getTotalCycleTime(): int
    {
        $total = 0;
        foreach ($this->versionIssues as $issue) {
            $total += $issue['cycleTime'] ?? 0;
        }
        return $total;
    }
}