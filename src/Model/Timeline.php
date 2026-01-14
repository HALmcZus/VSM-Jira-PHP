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
     * getTimelineByStatus
     *
     * @param  mixed $timelineByStatus
     * @param  mixed $splitOtherStatuses
     * @return array
     */
    public function getTimelineByStatus(array $timelineByStatus, $splitOtherStatuses = true): array
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
            $status = mb_strtolower($status, 'UTF-8');
            if (isset($timelineByStatus[$status]) && $timelineByStatus[$status] > 0) {
                $sortedTimeline[$status] = mb_strtolower($timelineByStatus[$status], 'UTF-8');
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
                $otherStatuses[mb_strtolower($status, 'UTF-8')] = $days;
            }
        }

        return $splitOtherStatuses 
        ? ['workflowStatuses' => $sortedTimeline, 'otherStatuses' => $otherStatuses] 
        : array_merge($sortedTimeline, $otherStatuses);
    }
}