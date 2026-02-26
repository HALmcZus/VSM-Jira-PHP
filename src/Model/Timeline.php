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
    const WAIT_LABEL_KEYWORD = 'attente';

    private Config $config;

    /**
     * __construct
     */
    public function __construct()
    {
        $this->config = new Config();
    }

    /**
     * Détermine dans quelle tranche horaire se trouve une DateTime.
     *
     * Tranches :
     * - 1 : 00h00 → 09h59 (début matinée)
     * - 2 : 10h00 → 12h59 (fin matinée)
     * - 3 : 13h00 → 15h59 (début après-midi)
     * - 4 : 16h00 → 23h59 (fin après-midi)
     *
     * @param \DateTime $dateTime
     * @return int Numéro de tranche (1-4)
     */
    protected function getTimeSlot(\DateTime $dateTime): int
    {
        $hour = (int) $dateTime->format('H');

        if ($hour < 10) {
            return 1;
        } elseif ($hour < 13) {
            return 2;
        } elseif ($hour < 16) {
            return 3;
        } else {
            return 4;
        }
    }

    /**
     * Vérifie si deux DateTime ont des heures similaires (tolérance en minutes).
     *
     * Exemples :
     * - 11h00 vs 11h00 (diff = 0) → true
     * - 10h30 vs 11h00 (diff = 30) → true
     * - 11h00 vs 11h30 (diff = 30) → true
     * - 10h00 vs 11h00 (diff = 60) → false
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @param int $toleranceMinutes Tolérance en minutes (défaut 30)
     * @return bool
     */
    private function isSimilarTime(\DateTime $start, \DateTime $end, int $toleranceMinutes = 30): bool
    {
        $startMinutes = ((int) $start->format('H') * 60) + (int) $start->format('i');
        $endMinutes = ((int) $end->format('H') * 60) + (int) $end->format('i');

        $diff = abs($endMinutes - $startMinutes);

        return $diff <= $toleranceMinutes;
    }

    /**
     * Compte le nombre de jours ouvrés calendaires entre deux dates.
     *
     * Utilisé pour le cas spécial "même heure ±30min".
     * Compte les jours ouvrés du jour après start au jour de end (inclus).
     *
     * Exemple : Lun → Mar retourne 1 (seulement le Mar, car Lun est le jour de départ)
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @return int Nombre de jours ouvrés
     */
    private function countBusinessDaysBetween(\DateTime $start, \DateTime $end): int
    {
        $nonWorkingDays = $this->config->getNonWorkingDays();
        $businessDays = 0;

        $current = clone $start;
        $current->modify('+1 day')->setTime(0, 0, 0);

        $endDate = clone $end;
        $endDate->setTime(0, 0, 0);

        while ($current <= $endDate) {
            $dayOfWeek = (int) $current->format('N');
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
     * Calcule le nombre de jours par logique de tranches horaires.
     *
     * Chaque jour ouvré est divisé en 4 tranches de 0.25 jour :
     * - Tranche 1 (00h-10h) : 0.25
     * - Tranche 2 (10h-13h) : 0.25
     * - Tranche 3 (13h-16h) : 0.25
     * - Tranche 4 (16h-24h) : 0.25
     *
     * @param \DateTime $start
     * @param \DateTime $end
     * @return float
     */
    private function calculateByTimeSlots(\DateTime $start, \DateTime $end): float
    {
        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');
        $nonWorkingDays = $this->config->getNonWorkingDays();

        $totalSlots = 0;

        // Cas 1 : Même jour calendaire
        if ($startDate === $endDate) {
            // Vérifier si jour ouvré
            $dayOfWeek = (int) $start->format('N');
            $isWeekend = ($dayOfWeek >= 6);
            $isHoliday = in_array($startDate, $nonWorkingDays, true);

            if ($isWeekend || $isHoliday) {
                return 0;
            }

            $startSlot = $this->getTimeSlot($start);
            $endSlot = $this->getTimeSlot($end);

            // Compter les tranches incluses
            $totalSlots = $endSlot - $startSlot + 1;

            return $totalSlots * 0.25;
        }

        // Cas 2 : Jours différents

        // Jour de départ : compter de la tranche de start jusqu'à la tranche 4
        $startDayOfWeek = (int) $start->format('N');
        $isStartWeekend = ($startDayOfWeek >= 6);
        $isStartHoliday = in_array($startDate, $nonWorkingDays, true);

        if (!$isStartWeekend && !$isStartHoliday) {
            $startSlot = $this->getTimeSlot($start);
            $totalSlots += (4 - $startSlot + 1); // De startSlot à 4 inclus
        }

        // Jours intermédiaires (complets)
        $current = (clone $start)->modify('+1 day')->setTime(0, 0, 0);
        $lastDay = (clone $end)->setTime(0, 0, 0);

        while ($current < $lastDay) {
            $dayOfWeek = (int) $current->format('N');
            $currentDate = $current->format('Y-m-d');

            $isWeekend = ($dayOfWeek >= 6);
            $isHoliday = in_array($currentDate, $nonWorkingDays, true);

            if (!$isWeekend && !$isHoliday) {
                $totalSlots += 4; // Journée complète = 4 tranches
            }

            $current->modify('+1 day');
        }

        // Jour d'arrivée : compter de la tranche 1 jusqu'à la tranche de end
        $endDayOfWeek = (int) $end->format('N');
        $isEndWeekend = ($endDayOfWeek >= 6);
        $isEndHoliday = in_array($endDate, $nonWorkingDays, true);

        if (!$isEndWeekend && !$isEndHoliday) {
            $endSlot = $this->getTimeSlot($end);
            $totalSlots += $endSlot; // De 1 à endSlot inclus
        }

        return $totalSlots * 0.25;
    }

    /**
     * Calcule le nombre de jours ouvrés entre deux dates.
     *
     * Logique hybride :
     * 1. Cas spécial : si jours différents + même heure (±30min)
     *    → Retourne le nombre de jours calendaires ouvrés
     *    Exemple : Lun 11h → Mar 11h = 1 jour ouvré (le mardi)
     *
     * 2. Sinon : logique par tranches horaires
     *    → 4 tranches de 0.25 jour par jour ouvré
     *    Exemple : Lun 8h → Lun 15h30 = 0.75 jour (3 tranches)
     *
     * @param \DateTime $start Date/heure de début
     * @param \DateTime $end Date/heure de fin
     * @return float Nombre de jours ouvrés
     */
    public function calculateBusinessDays(\DateTime $start, \DateTime $end): float
    {
        if ($start > $end) {
            return 0;
        }

        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');

        // Cas spécial : jours différents + même heure (tolérance ±30min)
        if ($startDate !== $endDate && $this->isSimilarTime($start, $end, 30)) {
            return $this->countBusinessDaysBetween($start, $end);
        }

        // Logique par tranches
        return $this->calculateByTimeSlots($start, $end);
    }

    /**
     * getSortedTimelineByStatus : ordonnance les status Jira suivant le workflow configuré
     *
     * @param  array $timelineByStatus
     * @param  bool $splitOtherStatuses
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
     * Reconstruit la timeline de l'issue à partir du changelog Jira
     * et calcule le temps cumulé par status et par status category.
     *
     * Cette méthode est appelée une seule fois à l'initialisation
     * de l'objet Issue.
     *
     * @param Issue $issue
     * @return void
     */
    public function buildStatusTimeline($issue)
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
     * @param  string $currentStatus
     * @param  float $daysInStatus
     * @param  array $workflow
     * @param  array $workflowTimeBreakdown
     * @return void
     */
    public function updateWorkflowTimeBreakdown(string $currentStatus, float $daysInStatus, array $workflow, array &$workflowTimeBreakdown)
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

    /**
     * Calculate the waiting times, based an Jira labels "*attente*"
     */
    public function buildWaitingTimes(Issue $issue)
    {
        $history = $issue->getHistory();
        if (empty($history)) {
            return;
        }

        $waitingTimes = [];

        foreach ($history as $historyItem) {
            foreach ($historyItem['items'] as $item) {
                // Ignore les éléments d'historique ne concernant pas les étiquettes (champs labels)
                if ($item['field'] !== 'labels') {
                    continue;
                }

                //Extrait les labels contenant "attente" dans les champs fromString et toString
                foreach (['fromString', 'toString'] as $field) {
                    $labels = strtolower($item[$field] ?? '');
                    $waitingTimes = array_merge(
                        $waitingTimes,
                        $this->extractWaitingLabelsFromItem($labels)
                    );
                }
            }
        }

        $waitingTimes = array_unique($waitingTimes);

        if ($waitingTimes) {
            echo "<pre>waitingTimes :".print_r($waitingTimes,true)."</pre>";
            die();
        }

        $issue->setWaitingTimes($waitingTimes);
    }

    /**
     * Search for waiting label in a given labels string, and return it as array
     *
     * @param  string $string
     * @return array
     */
    protected function extractWaitingLabelsFromItem(string $string): array
    {
        $result = [];
        $labels = explode(' ', $string);
        foreach ($labels as $label) {
            if (str_contains($label, self::WAIT_LABEL_KEYWORD)) {
                $result[] = $label;
            }
        }
        return $result;
    }
}
