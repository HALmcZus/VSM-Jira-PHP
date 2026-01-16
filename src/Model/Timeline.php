<?php
namespace App\Model;

use App\Model\Config;

/**
 * Timeline
 */
class Timeline
{
    private Config $config;
    
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->config = new Config();
    }

    /**
     * Calcule le nombre de jours ouvrés entre deux dates (week-ends et jours fériés exclus).
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
     * getSortedTimelineByStatus : ordonnance les status Jira suivant le workflow configuré
     *
     * @param  mixed $timelineByStatus
     * @param  mixed $splitOtherStatuses
     * @return array
     */
    public function getSortedTimelineByStatus(array $timelineByStatus, $splitOtherStatuses = true): array
    {
        $workflow = $this->config->getJiraWorkflow();

        //Sort as defined in config_files/jira_workflow.json
        $orderedStatuses = array_merge(
            $workflow['refinement_statuses'] ?? [],
            $workflow['sprint_statuses'] ?? [],
            $workflow['done_statuses'] ?? []
        );

        // Sort known statuses first
        $sortedTimeline = [];
        foreach ($orderedStatuses as $status) {
            if (isset($timelineByStatus[$status]) && $timelineByStatus[$status] > 0) {
                $sortedTimeline[$status] = $timelineByStatus[$status];
                unset($timelineByStatus[$status]);
            }
        }
    
        // Move unknown statuses to the end
        $otherStatuses = [];
        if (!empty($timelineByStatus)) {
            foreach ($timelineByStatus as $status => $days) {
                if ($days <= 0) {
                    continue;
                }
                $otherStatuses[$status] = $days;
            }
        }

        return $splitOtherStatuses 
        ? ['workflowStatuses' => $sortedTimeline, 'otherStatuses' => $otherStatuses] 
        : array_merge($sortedTimeline, $otherStatuses);
    }

    /**
     * Format a string as a key (snake_case without accents nor special chars)
     */
    public function stringAsKey(string $string): string
    {
        $string = mb_strtolower($string, 'UTF-8');
        $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        $string = preg_replace('/[^a-z0-9]+/', '_', $string);
    
        return trim($string, '_');
    }
}