<?php
namespace App\Model;

use Exception;
use App\Service\JiraService;
use App\Model\Config;
use App\Model\Timeline;

/**
 * Version
 */
class Version
{
    const VERSION_URL = '{base_url}/projects/{project_key}/versions/{version_id}';

    protected JiraService $jiraService;
    protected Config $config;
    protected Timeline $timeline;

    /** Version & Issues data */
    protected array $versionData = [];
    protected array $versionIssuesIds = [];
    protected array $versionIssues = [];
    protected int $issuesCount = 0;

    /** Timeline data */
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
     * @param  mixed $versionId
     * @return void
     */
    public function __construct(int $versionId)
    {
        $this->jiraService = new JiraService();
        $this->config = new Config();
        $this->timeline = new Timeline();

        // Load version data from Jira
        $this->getVersionById($versionId);

        // Load version's issues
        $this->getIssuesDetailsByVersion($versionId);

        // Calculate Times
        $this->calculateVersionLeadAndCycleTime();
        $this->calculateTimelineByStatusAndCategory();
    }
    
    /**
     * getVersionById
     *
     * @param  mixed $versionId
     * @return array
     */
    public function getVersionById(int $versionId) : array 
    {
        $result = $this->jiraService->getVersionById($versionId);

        //TODO: afficher message erreur si version non trouvée pour l'ID demandé
        if (!isset($result['id']) || isset($result['error'])) {
            throw new Exception("Erreur lors de la récupération de la Version Jira (Version::getVersionById) : " . print_r($result, true));
        }

        $result['version_url'] = str_replace(
            ['{base_url}', '{project_key}', '{version_id}'],
            [$_ENV['JIRA_BASE_URL'], $result['projectId'], $result['id']],
            self::VERSION_URL
        );

        $this->versionData = $result;
        return $this->versionData;
    }
    
    /**
     * getIssuesIdsByVersion
     *
     * @param  mixed $versionId
     * @return array
     */
    public function getIssuesIdsByVersion(int $versionId) : array 
    {
        $issues = $this->jiraService->getIssuesIdsByVersion($versionId);
        
        $result = [];
        foreach ($issues as $issue) {
            $result[] = $issue['id'] ?? '';
        }

        $this->versionIssuesIds = $result;

        return $this->versionIssuesIds;
    }
    
    /**
     * getIssuesDetailsByVersion
     *
     * @param  mixed $versionId
     * @return array
     */
    public function getIssuesDetailsByVersion(int $versionId) : array
    {
        try {
            //Evite un second appel pour récupérer les IDs si on les a déjà
            if (!$this->versionIssuesIds) {
                $this->getIssuesIdsByVersion($versionId);
            }
            
            $rawIssues = $this->jiraService->getIssuesDetails($this->versionIssuesIds);
     
            foreach ($rawIssues as $rawIssueData) {
                $this->versionIssues[] = new Issue($rawIssueData);
            }
            
            $this->issuesCount = count($this->versionIssues);

            return $this->versionIssues;

        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des tickets Jira rattachés à la Version : " . $e->getMessage());
        }
    }
    
    /**
     * Calcule le temps cumulé de la release par status
     * et par catégorie de status Jira.
     *
     * Agrège les timelines calculées dans chaque Issue.
     *
     * @return array{
     *   byStatus: array<string, float>,
     *   byCategory: array<string, float>
     * }
     */
    public function calculateTimelineByStatusAndCategory(): array
    {
        $timeByStatus = [];
        $timeByCategory = [];

        /** @var \App\Model\Issue $issue */
        foreach ($this->versionIssues as $issue) {
            // Agrégation par status
            foreach ($issue->getTimeByStatus(false) as $statusName => $timeSpent) {
                $timeByStatus[$statusName] = ($timeByStatus[$statusName] ?? 0) + $timeSpent;
            }
        }

        $this->timelineByStatus = $timeByStatus;
        $this->timelineByCategory = $timeByCategory;

        return [
            'byStatus' => $timeByStatus,
            'byCategory' => $timeByCategory,
        ];
    }
    
    /**
     * getTimelineByStatus
     *
     * @return array
     */
    public function getTimelineByStatus(): array
    {
        return $this->timeline->getSortedTimelineByStatus($this->timelineByStatus);
    }
       
    /**
     * getTimelineByCategory
     *
     * @return array
     */
    public function getTimelineByCategory(): array
    {
        return $this->timelineByCategory;
    }

    /**
     * calculate Version's Lead And Cycle Times (total and average)
     *
     * @return void
     */
    public function calculateVersionLeadAndCycleTime()
    {
        if ($this->issuesCount > 0) {
            /* @var \App\Model\Issue $issue */
            foreach ($this->versionIssues as $issue) {
                $this->totalLeadTime += $issue->getLeadTime() ?? 0;
                $this->totalCycleTime += $issue->getCycleTime() ?? 0;
                $this->totalTimeSpentInRefinement += $issue->getTimeSpentInRefinement() ?? 0;
                $this->totalTimeSpentInSprint += $issue->getTimeSpentInSprint() ?? 0;
                $this->totalTimeSpentInOther += $issue->getTimeSpentInOther() ?? 0;
            }
            $this->averageLeadTime = round($this->totalLeadTime / $this->issuesCount, 2);
            $this->averageCycleTime = round($this->totalCycleTime / $this->issuesCount, 2);
            $this->averageTimeSpentInRefinement = round($this->totalTimeSpentInRefinement / $this->issuesCount, 2);
            $this->averageTimeSpentInSprint = round($this->totalTimeSpentInSprint / $this->issuesCount, 2);
            $this->averageTimeSpentInOther = round($this->totalTimeSpentInOther / $this->issuesCount, 2);
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

    /** *******************
     *** DATA GETTERS **
     ******************* **/    
    /**
     * getId
     */
    public function getId()
    {
        return $this->versionData['id'] ?? null;
    }

    /**
     * getName
    */
    public function getName()
    {
        return $this->versionData['name'] ?? null;
    }

    /**
     * getDescription
    */
    public function getDescription()
    {
        return $this->versionData['description'] ?? null;
    }

    /**
     * getUrl
    */
    public function getUrl()
    {
        return $this->versionData['version_url'] ?? '#';
    }

    /**
     * getStartDate
    */
    public function getStartDate()
    {
        return $this->versionData['startDate'] ?? null;
    }

    /**
     * getReleaseDate
    */
    public function getReleaseDate()
    {
        return $this->versionData['releaseDate'] ?? null;
    }

    /**
     * isReleased
     *
     * @return bool
     */
    public function isReleased(): bool
    {
        return (bool) ($this->versionData['released'] ?? false);
    }

    /**
     * isOverdue
     *
     * @return bool
     */
    public function isOverdue(): bool
    {
        return (bool) ($this->versionData['overdue'] ?? false);
    }

    /**
     * getProjectId
     *
     * @return bool
     */
    public function getProjectId(): bool
    {
        return $this->versionData['projectId'] ?? null;
    }

    /**
     * getIssues
     *
     * @return array
     */
    public function getIssues(): array
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
        return $this->issuesCount;
    }
}