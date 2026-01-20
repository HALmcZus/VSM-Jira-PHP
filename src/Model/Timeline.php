<?php

namespace App\Model;

use App\Model\Config;

/**
 * Timeline
 */
class Timeline
{
    const STATUS_TODO = 'To Do';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_DONE = 'Done';

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
     * TODO: à vérifier cas par cas
     *
     * Reconstruit la timeline de l’issue à partir du changelog Jira
     * et calcule le temps cumulé par status et par status category.
     *
     * Cette méthode est appelée une seule fois à l'initialisation
     * de l'objet Issue.
     *
     * @return void
     */
    public function buildStatusTimeline(Issue $issue)
    {
        $history = $issue->getHistory();
        if (empty($history)) {
            return;
        }

        // Get Jira workflow from jira_workflow.json
        $workflow = $this->config->getJiraWorkflow();
        $workflowTimeBreakdown = $issue->getWorkflowTimeBreakdown();

        // Current status data
        $currentStatus = $issue->getStatusName();
        $currentStatusCreatedDate = $issue->getCreatedDate(false);

        foreach ($history as $historyItem) {
            foreach ($historyItem['items'] as $status) {
                // Ignore les éléments d'historique ne concernant pas les status
                if ($status['field'] !== 'status') {
                    continue;
                }

                //Date du changement de status
                if (empty($historyItem['created'])) {
                    throw new \RuntimeException('Transition date cannot be null');
                }
                $transitionDate = new \DateTime($historyItem['created']);
                $daysInStatus = (int) $currentStatusCreatedDate->diff($transitionDate)->days;

                /**
                 * Agrégation du temps par status et catégorie de status
                 */
                if (!isset($timeByStatus[$currentStatus])) {
                    $timeByStatus[$currentStatus] = 0;
                }
                $timeByStatus[$currentStatus] += $daysInStatus;

                $this->updateWorkflowTimeBreakdown($currentStatus, $daysInStatus, $workflow, $workflowTimeBreakdown);

                /**
                 * Met à jour les firstInProgressDate et doneDate
                 */
                // S'il s'agit du premier passage à En cours, on le set
                if ($status['toString'] === self::STATUS_IN_PROGRESS && $issue->getFirstInProgressDate(false) === null) {
                    $issue->setFirstInProgressDate($transitionDate);
                }
                // S'il s'agit du dernier passage à Done (pour prendre en compte les éventuels allers-retours de status)
                if (in_array($status['toString'], $workflow['done_statuses'], true)) {
                    $issue->setDoneDate($transitionDate);
                }

                // Mise à jour du status courant pour la prochaine boucle
                $currentStatus = $status['toString'];
                $currentStatusCreatedDate = $transitionDate;
            }
        }

        /**
         * On traite le statut actuel (jusqu'à date du Done, ou date du jour si ticket pas encore résolu)
         * TODO: à vérifier
         */
        $endDate = $issue->getResolutionDateTime();
        $daysInStatus = (int) $currentStatusCreatedDate->diff($endDate)->days;

        if (!isset($timeByStatus[$currentStatus])) {
            $timeByStatus[$currentStatus] = 0;
        }
        $timeByStatus[$currentStatus] += $daysInStatus;

        $this->updateWorkflowTimeBreakdown($currentStatus, $daysInStatus, $workflow, $workflowTimeBreakdown);

        // Suppression des statuts Done
        foreach ($workflow['done_statuses'] as $doneStatus) {
            unset($timeByStatus[$doneStatus]);
        }

        //Set le résultat
        $issue->setTimeByStatus($timeByStatus);
        $issue->setWorkflowTimeBreakdown($workflowTimeBreakdown);
    }

    /**
     * buildWorkflowTimeBreakdown
     *
     * @param  mixed $currentStatus
     * @param  mixed $daysInStatus
     * @param  mixed $workflow
     * @param  mixed $workflowTimeBreakdown
     * @return void
     */
    public function updateWorkflowTimeBreakdown(string $currentStatus, int $daysInStatus, array $workflow, array &$workflowTimeBreakdown)
    {
        switch ($currentStatus) {
            case in_array($currentStatus, $workflow['refinement_statuses'], true):
                $workflowTimeBreakdown['refinement'] += $daysInStatus;
                break;
            case in_array($currentStatus, $workflow['sprint_statuses'], true):
                $workflowTimeBreakdown['sprint'] += $daysInStatus;
                break;
            case in_array($currentStatus, $workflow['done_statuses'], true):
                //Ignore Done statuses
                break;
            default:
                $workflowTimeBreakdown['other'] += $daysInStatus;
                break;
        }
    }
}
