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
    private float $averageCycleTime = 0;
    private int $totalCycleTime = 0;
    private float $averageLeadTime = 0;
    private int $totalLeadTime = 0;
    private float $averageTimeSpentInRefinement = 0;
    private int $totalTimeSpentInRefinement = 0;
    private float $averageTimeSpentInSprint = 0;
    private int $totalTimeSpentInSprint = 0;
    private float $averageTimeSpentInOther = 0;
    private int $totalTimeSpentInOther = 0;
    private array $timelineByStatus = [];
    private array $timelineByCategory = [];

    /**
     * __construct
     *
     * @param  mixed $versionData
     * @param  mixed $versionIssues
     * @param  mixed $releaseTimeline
     * @return void
     */
    public function __construct(array $versionData, array $versionIssues, array $releaseTimeline = [])
    {
        $this->versionData = $versionData;
        $this->versionIssues = $versionIssues;
    
        $this->timelineByStatus = $releaseTimeline['byStatus'] ?? [];
        $this->timelineByCategory = $releaseTimeline['byCategory'] ?? [];
    
        $this->calculateVersionLeadAndCycleTime();
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
     * getVersionUrl
     *
     * @return string
     */
    public function getVersionUrl(): string
    {
        return $this->versionData['version_url'] ?? '#';
    }
    
    /**
     * getVersionStartDate
     *
     * @return string
     */
    public function getVersionStartDate(): string
    {
        return $this->versionData['startDate'] ?? '<i>Date non renseignée.</i>';
    }
    
    /**
     * getVersionReleaseDate
     *
     * @return string
     */
    public function getVersionReleaseDate(): string
    {
        return $this->versionData['releaseDate'] ?? '<i>Non renseignée.</i>';
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
        echo ''; //Utilisé ?
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
        echo ''; //Utilisé ?
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
     * calculate Version's Lead And Cycle Times (total and average)
     *
     * @return void
     */
    public function calculateVersionLeadAndCycleTime()
    {
        if ($this->getIssuesCount() > 0) {
            /* @var \App\Model\Issue $issue */
            foreach ($this->versionIssues as $issue) {
                $this->totalLeadTime += $issue->getLeadTime() ?? 0;
                $this->totalCycleTime += $issue->getCycleTime() ?? 0;
                $this->totalTimeSpentInRefinement += $issue->getTimeSpentInRefinement() ?? 0;
                $this->totalTimeSpentInSprint += $issue->getTimeSpentInSprint() ?? 0;
                $this->totalTimeSpentInOther += $issue->getTimeSpentInOther() ?? 0;
            }
            $this->averageLeadTime = round($this->totalLeadTime / $this->getIssuesCount(), 2);
            $this->averageCycleTime = round($this->totalCycleTime / $this->getIssuesCount(), 2);
            $this->averageTimeSpentInRefinement = round($this->totalTimeSpentInRefinement / $this->getIssuesCount(), 2);
            $this->averageTimeSpentInSprint = round($this->totalTimeSpentInSprint / $this->getIssuesCount(), 2);
            $this->averageTimeSpentInOther = round($this->totalTimeSpentInOther / $this->getIssuesCount(), 2);
        }
    }
    
    /**
     * getAverageCycleTime
     *
     * @return float
     */
    public function getAverageCycleTime(): float
    {
        return $this->averageCycleTime;
    }

    /**
     * getTotalCycleTime
     *
     * @return float
     */
    public function getTotalCycleTime(): float
    {
        return $this->totalCycleTime;
    }

    /**
     * getAverageLeadTime
     *
     * @return float
     */
    public function getAverageLeadTime(): float
    {
        return $this->averageLeadTime;
    }

    /**
     * getTotalLeadTime
     *
     * @return float
     */
    public function getTotalLeadTime(): float
    {
        return $this->totalLeadTime;
    }
        
    /**
     * getTimelineByStatus
     *
     * @return array
     */
    public function getTimelineByStatus(): array
    {
        return $this->timelineByStatus;
    }
    
    /**
     * getTimelineByCategory
     *
     * @return array
     */
    public function getTimelineByCategory(): array
    {
        echo ''; //Utilisé ?
        return $this->timelineByCategory;
    }
    
    /**
     * getAverageTimeSpentInRefinement
     *
     * @return float
     */
    public function getAverageTimeSpentInRefinement(): float
    {
        return $this->averageTimeSpentInRefinement;
    }
    
    /**
     * getTotalTimeSpentInRefinement
     *
     * @return int
     */
    public function getTotalTimeSpentInRefinement(): int
    {
        return $this->totalTimeSpentInRefinement;
    }
        
    /**
     * getAverageTimeSpentInSprint
     *
     * @return float
     */
    public function getAverageTimeSpentInSprint(): float
    {
        return $this->averageTimeSpentInSprint;
    }
    
    /**
     * getTotalTimeSpentInSprint
     *
     * @return int
     */
    public function getTotalTimeSpentInSprint(): int
    {
        return $this->totalTimeSpentInSprint;
    }
    
    /**
     * getAverageTimeSpentInOther
     *
     * @return float
     */
    public function getAverageTimeSpentInOther(): float
    {
        return $this->averageTimeSpentInOther;
    }
    
    /**
     * getTotalTimeSpentInOther
     *
     * @return int
     */
    public function getTotalTimeSpentInOther(): int
    {
        return $this->totalTimeSpentInOther;
    }
}