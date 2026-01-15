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

        $orderedStatuses = array_map('mb_strtolower', $orderedStatuses);
        $timelineByStatus = array_change_key_case($timelineByStatus, CASE_LOWER);

        // Sort known statuses first
        $sortedTimeline = [];
        foreach ($orderedStatuses as $status) {
            if (isset($timelineByStatus[$status]) && $timelineByStatus[$status] > 0) {
                $key = $this->normalizeStatusName($status);
                $sortedTimeline[$key] = $this->normalizeStatusName($timelineByStatus[$status]);
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
                $otherStatuses[$this->normalizeStatusName($status)] = $days;
            }
        }

        return $splitOtherStatuses 
        ? ['workflowStatuses' => $sortedTimeline, 'otherStatuses' => $otherStatuses] 
        : array_merge($sortedTimeline, $otherStatuses);
    }
    
    /**
     * getStatusTranslations
     *
     * @return array
     */
    public function getStatusTranslations(): array
    {
        $workflow = $this->config->getJiraWorkflow();
        $translations = $workflow['translations'] ?? [];
        return array_map('mb_strtolower', $translations);
    }
    
    /**
     * normalizeStatusName : lowercase, translate if needed then capitalize first letter
     *
     * @param  mixed $status
     * @return string
     */
    public function normalizeStatusName(string $status): string
    {
        // Minuscules
        $formattedStatus = mb_strtolower($status, 'UTF-8');
        
        //Si le status est natif Jira, on le traduit
        $statusTranslations = $this->getStatusTranslations();
        if (isset($statusTranslations[$formattedStatus])) {
            $formattedStatus = $statusTranslations[$formattedStatus];
        }

        // Première lettre en majuscule
        return ucfirst($formattedStatus);
    }
    
    /**
     * normalizeArray
     *
     * @param  mixed $array
     * @return array
     */
    public function normalizeArray($array): array
    {
        if (empty($array)) {
            return [];
        }

        $normalizedArray = [];
        foreach ($array as $item) {
            $normalizedArray[] = $this->normalizeStatusName($item);
        }
        return $normalizedArray;
    }
}