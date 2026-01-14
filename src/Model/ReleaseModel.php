<?php
namespace App\Model;

use Exception;
use App\Service\JiraService;
use App\Model\Config;

/**
 * ReleaseModel
 */
class ReleaseModel
{
    const VERSION_URL = '{base_url}/projects/{project_key}/versions/{version_id}';

    protected JiraService $jiraService;
    protected Config $config;

    protected array $versionData = [];
    protected array $versionIssuesIds = [];
    protected array $versionIssues = [];
    
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->jiraService = new JiraService();
        $this->config = new Config();
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
            throw new Exception("Erreur lors de la récupération de la Version Jira (ReleaseModel::getVersionById) : " . print_r($result, true));
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
     * getVersionsByProjectId
     *
     * @param  mixed $projectId
     * @return array
     */
    public function getVersionsByProjectId(int $projectId) : array 
    {
        //WIP: non utilisé pour l'instant
        $result = $this->jiraService->getVersionsByProjectId($projectId);

        return $result;
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
    
            return $this->versionIssues;
        } catch (Exception $e) {
            throw new Exception("Erreur lors de la récupération des tickets Jira rattachés à la Version : " . $e->getMessage());
        }
    }


    /**
     * Calcule le nombre de jours ouvrés entre deux dates (week-ends et jours fériés exclus).
     * TODO: extraire dans un Helper ou Model dédié ?
     *
     * @param \DateTime $start Date de début (incluse)
     * @param \DateTime $end Date de fin (incluse)
     *
     * @return int Nombre de jours ouvrés
     */
    public function calculateBusinessDays(\DateTime $start, \DateTime $end): int 
    {
        if ($start > $end) {
            return 0;
        }
    
        $nonWorkingDays = $this->config->getNonWorkingDays();
        $businessDays = 1;
        $current = clone $start;
    
        while ($current <= $end) {
            $dayOfWeek = (int) $current->format('N'); // 1 (lundi) → 7 (dimanche)
            $currentDate = $current->format('Y-m-d');
    
            $isWeekend = ($dayOfWeek >= 6);
            $isHoliday = in_array($currentDate, $nonWorkingDays, true);
    
            if (!$isWeekend && !$isHoliday) {
                $businessDays++;
            }
    
            $current->modify('+1 day');
        }
    
        return $businessDays;
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
                $statusName = mb_strtolower($statusName, 'UTF-8');
                $timeByStatus[$statusName] = ($timeByStatus[$statusName] ?? 0) + $timeSpent;
            }
        }

        return [
            'byStatus' => $timeByStatus,
            'byCategory' => $timeByCategory,
        ];
    }

}