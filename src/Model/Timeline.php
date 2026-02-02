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
     * Calcule le coefficient du jour de DÉPART selon l'heure.
     *
     * Logique métier : ce qui RESTE à travailler dans ce statut.
     *
     * @param \DateTime $dateTime Date de départ avec horaire
     * @return float 0, 0.5 ou 1
     */
    protected function getStartDayCoefficient(\DateTime $dateTime): float
    {
        $hour = (int) $dateTime->format('H');
        $minute = (int) $dateTime->format('i');
        $totalMinutes = ($hour * 60) + $minute;

        // <= 11h00 (660 minutes)
        if ($totalMinutes <= 660) {
            return 1; // Journée complète reste à travailler
        }

        // > 11h00 et <= 17h00 (1020 minutes)
        if ($totalMinutes <= 1020) {
            return 0.5; // Après-midi seulement
        }

        // > 17h00
        return 0; // Journée terminée
    }

    /**
     * Calcule le coefficient du jour d'ARRIVÉE selon l'heure.
     *
     * Logique métier : ce qui a été TRAVAILLÉ dans ce statut.
     *
     * @param \DateTime $dateTime Date d'arrivée avec horaire
     * @return float 0, 0.5 ou 1
     */
    protected function getEndDayCoefficient(\DateTime $dateTime): float
    {
        $hour = (int) $dateTime->format('H');
        $minute = (int) $dateTime->format('i');
        $totalMinutes = ($hour * 60) + $minute;

        // <= 10h00 (600 minutes)
        if ($totalMinutes <= 600) {
            return 0; // Rien travaillé encore
        }

        // > 10h00 et <= 14h00 (840 minutes)
        if ($totalMinutes <= 840) {
            return 0.5; // Matinée travaillée
        }

        // > 14h00
        return 1; // Journée complète travaillée
    }

    /**
     * Calcule le nombre de jours ouvrés entre deux dates avec gestion des demie-journées.
     *
     * Algorithme :
     * 1. Si même jour : différence entre coefficient arrivée et départ
     * 2. Sinon :
     *    - Compte les jours ouvrés ENTRE start+1 et end-1 (jours pleins)
     *    - Ajoute coefficient du jour de départ
     *    - Ajoute coefficient du jour d'arrivée
     *
     * @param \DateTime $start Date de début avec horaire
     * @param \DateTime $end Date de fin avec horaire
     * @return float Nombre de jours ouvrés (peut être décimal)
     */
    public function calculateBusinessDays(\DateTime $start, \DateTime $end): float
    {
        // Si end < start, retourner 0
        if ($start > $end) {
            return 0;
        }

        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');

        // Cas 1 : Même jour calendaire
        if ($startDate === $endDate) {
            $startCoeff = $this->getStartDayCoefficient($start);
            $endCoeff = $this->getEndDayCoefficient($end);

            // Sur un même jour, on prend le min entre ce qu'on peut faire
            return min($startCoeff, $endCoeff);
        }

        // Cas 2 : Jours différents
        $nonWorkingDays = $this->config->getNonWorkingDays();

        // Compter les jours ouvrés ENTRE start et end (exclus)
        $intermediaryDays = 0;
        $current = (clone $start)->modify('+1 day')->setTime(0, 0, 0);
        $lastDay = (clone $end)->setTime(0, 0, 0);

        while ($current < $lastDay) {
            $dayOfWeek = (int) $current->format('N');
            $currentDate = $current->format('Y-m-d');

            $isWeekend = ($dayOfWeek >= 6);
            $isHoliday = in_array($currentDate, $nonWorkingDays, true);

            if (!$isWeekend && !$isHoliday) {
                $intermediaryDays++;
            }

            $current->modify('+1 day');
        }

        // Vérifier si le jour de départ est ouvré
        $startDayOfWeek = (int) $start->format('N');
        $isStartWeekend = ($startDayOfWeek >= 6);
        $isStartHoliday = in_array($startDate, $nonWorkingDays, true);
        $startCoeff = (!$isStartWeekend && !$isStartHoliday)
            ? $this->getStartDayCoefficient($start)
            : 0;

        // Vérifier si le jour d'arrivée est ouvré
        $endDayOfWeek = (int) $end->format('N');
        $isEndWeekend = ($endDayOfWeek >= 6);
        $isEndHoliday = in_array($endDate, $nonWorkingDays, true);
        $endCoeff = (!$isEndWeekend && !$isEndHoliday)
            ? $this->getEndDayCoefficient($end)
            : 0;

        return $startCoeff + $intermediaryDays + $endCoeff;
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
     * TODO: à vérifier cas par cas, parfois jours incohérents (-1 / +1)
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
                $daysInStatus = $this->calculateBusinessDays($currentStatusCreatedDate, $transitionDate);

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
         */
        $endDate = $issue->getResolutionDateTime();
        $daysInStatus = $this->calculateBusinessDays($currentStatusCreatedDate, $endDate);

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
     * updateWorkflowTimeBreakdown
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
