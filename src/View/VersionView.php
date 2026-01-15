<?php
namespace App\View;

use App\Model\Config;
use App\Model\Version;
use App\Model\Timeline;

/**
 * Formate les données du Back, et les expose au Front
 */
class VersionView
{
    const STATUS_TRANSLATION_TODO = 'À faire';
    const STATUS_TRANSLATION_IN_PROGRESS = 'En cours';
    const STATUS_TRANSLATION_DONE = 'Terminé';
    const REFINEMENT_ICON = '🧠';
    const SPRINT_ICON = '⚙️';
    const DONE_ICON = '✅';
    const OTHER_ICON = '❓';

    private Config $config;
    private Timeline $timeline;
    private Version $version;
  
    /**
     * __construct
     *
     * @param  mixed $version
     * @return void
     */
    public function __construct(Version $version)
    {
        $this->config = new Config();
        $this->timeline = new Timeline();
        $this->version = $version;
    }

    /**
     * normalizeStatusName
     *
     * @param  mixed $statusName
     * @return string
     */
    public function normalizeStatusName(string $statusName): string
    {
        $statusName = $this->timeline->normalizeStatusName($statusName);
        $icon = $this->getCategoryStatusIcon($statusName);
        return $icon . ' ' . $statusName;
    }
    
    /**
     * getCategoryStatusIcon
     *
     * @param  mixed $statusName
     * @return string
     */
    public function getCategoryStatusIcon(string $statusName): string
    {
        $statusName = $this->timeline->normalizeStatusName($statusName);

        $workflow = $this->config->getJiraWorkflow();
        $refinementStatuses = $this->timeline->normalizeArray($workflow['refinement_statuses']);
        $sprintStatuses = $this->timeline->normalizeArray($workflow['sprint_statuses']);
        $doneStatuses = $this->timeline->normalizeArray($workflow['done_statuses']);

         // Détermine l'icône en fonction de la catégorie du status
         $icon = self::OTHER_ICON;

        if (in_array($statusName, $refinementStatuses ?? [], true)) {
            $icon = self::REFINEMENT_ICON;
        } elseif (in_array($statusName, $sprintStatuses ?? [], true)) {
            $icon = self::SPRINT_ICON;
        } elseif (in_array($statusName, $doneStatuses ?? [], true)) {
            $icon = self::DONE_ICON;
        } else {
            $icon = self::OTHER_ICON;
        }

        return $icon;
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
        return $this->version->getId() ?? '?';
    }
    
    /**
     * getVersionName
     *
     * @return string
     */
    public function getVersionName(): string
    {
        return $this->version->getName() ?? '<i>Nom de version non renseigné.</i>';
    }
    
    /**
     * getVersionDescription
     *
     * @return string
     */
    public function getVersionDescription(): string
    {
        return $this->version->getDescription() 
        ? htmlspecialchars($this->version->getDescription(), ENT_QUOTES, 'UTF-8')
        : '<i>Description non renseignée.</i>'; 
    }
    
    /**
     * getVersionUrl
     *
     * @return string
     */
    public function getVersionUrl(): string
    {
        return $this->version->getUrl() ?? '#';
    }
    
    /**
     * getVersionStartDate
     *
     * @return string
     */
    public function getVersionStartDate(): string
    {
        return $this->version->getStartDate() ?? '<i>Date non renseignée.</i>';
    }
    
    /**
     * getVersionReleaseDate
     *
     * @return string
     */
    public function getVersionReleaseDate(): string
    {
        return $this->version->getReleaseDate() ?? '<i>Non renseignée.</i>';
    }
    
    /**
     * isVersionReleased
     *
     * @return bool
     */
    public function isVersionReleased(): bool
    {
        return $this->version->isReleased();
    }
    
    /**
     * isVersionOverdue
     *
     * @return bool
     */
    public function isVersionOverdue(): bool
    {
        return $this->version->isOverdue();
    }
    
    /**
     * getProjectId
     */
    public function getProjectId()
    {
        return $this->version->getProjectId();
    }
    
    /**
     * getTotalLeadTime
     *
     * @return float
     */
    public function getTotalLeadTime(): int
    {
        return $this->version->getTotalLeadTime();
    }
    
    /**
     * getTotalLeadTime
     *
     * @return float
     */
    public function getAverageLeadTime(): float
    {
        return $this->version->getAverageLeadTime();
    }
    
    /**
     * getTotalLeadTime
     *
     * @return float
     */
    public function getTotalCycleTime(): int
    {
        return $this->version->getTotalCycleTime();
    }
    
    /**
     * getTotalLeadTime
     *
     * @return float
     */
    public function getAverageCycleTime(): float
    {
        return $this->version->getAverageCycleTime();
    }
    
    /**
     * getTotalLeadTime
     *
     * @return int
     */
    public function getTotalTimeSpentInRefinement(): int
    {
        return $this->version->getTotalTimeSpentInRefinement();
    }
    
    /**
     * getTotalLeadTime
     *
     * @return int
     */
    public function getTotalTimeSpentInSprint(): int
    {
        return $this->version->getTotalTimeSpentInSprint();
    }
    
    /**
     * getTotalLeadTime
     *
     * @return int
     */
    public function getTotalTimeSpentInOther(): int
    {
        return $this->version->getTotalTimeSpentInOther();
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
        return $this->version->getIssues();
    }
    
    /**
     * getIssuesCount
     *
     * @return int
     */
    public function getIssuesCount(): int
    {
        return $this->version->getIssuesCount();
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
     * getTimelineByStatus
     *
     * @param  mixed $timelineByStatus
     * @return array
     */
    public function getTimelineByStatus(): array
    {
        return $this->version->getTimelineByStatus();
    }
    
    /**
     * getAverageTimeSpentInRefinement
     *
     * @return float
     */
    public function getAverageTimeSpentInRefinement(): float
    {
        return $this->version->getAverageTimeSpentInRefinement();
    }
    
    /**
     * getAverageTimeSpentInSprint
     *
     * @return float
     */
    public function getAverageTimeSpentInSprint(): float
    {
        return $this->version->getAverageTimeSpentInSprint();
    }
        
    /**
     * getAverageTimeSpentInOther
     *
     * @return float
     */
    public function getAverageTimeSpentInOther(): float
    {
        return $this->version->getAverageTimeSpentInOther();
    }
}