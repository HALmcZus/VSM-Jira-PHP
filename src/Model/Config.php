<?php

namespace App\Model;

/**
 * Config
 */
class Config 
{
    const CONFIG_FILES_DIR = "config_files";
    const NON_WORKING_DAYS_FILE = "non_working_days.json";
    const JIRA_WORKFLOW_FILE = "jira_workflow.json";
    
    /**
     * getFileContent
     *
     * @param  mixed $fileName
     * @return array
     */
    protected function getFileContent(string $fileName): array
    {
        $filePath = __DIR__ . '/../../' . self::CONFIG_FILES_DIR . '/' . $fileName;
        if (!file_exists($filePath)) {
            return [];
        }

        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);

        return $data ?? [];
    }
    
    /**
     * getNonWorkingDays from config file
     *
     * @return array
     */
    public function getNonWorkingDays(): array
    {
        $result = $this->getFileContent(self::NON_WORKING_DAYS_FILE);

        return $result['non_working_days'] ?? [];
    }
    
    /**
     * getJiraWorkflow from config file
     *
     * @return array
     */
    public function getJiraWorkflow(): array
    {
        return $this->getFileContent(self::JIRA_WORKFLOW_FILE);
    }  
}