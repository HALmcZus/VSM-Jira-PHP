<?php

namespace App\Model;

use App\Service\GovApiService;

/**
 * Config
 */
class Config
{
    const CONFIG_FILES_DIR = "config_files";
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
     * Retourne la liste des dates de jours fériés pour les 5 années précédentes
     * et l'année en cours, issues de l'API du gouvernement français.
     *
     * Algorithme :
     * 1. Récupère tous les jours fériés disponibles via GovApiService (un seul appel HTTP)
     * 2. Filtre sur la plage d'années [année_courante - 5 ; année_courante]
     * 3. Retourne un tableau plat de dates au format Y-m-d
     *
     * @return string[]  Ex: ['2025-01-01', '2025-05-01', ...]
     *
     * @throws \RuntimeException Si l'API est inaccessible
     */
    public function getNonWorkingDays(): array
    {
        $currentYear = (int) date('Y');
        $minYear     = $currentYear - 5;

        // Récupération (avec cache statique interne à GovApiService)
        $govApi      = new GovApiService();
        $allHolidays = $govApi->fetchAllHolidays();

        // Filtre sur la plage d'années voulue, et retourne les dates (clés du tableau)
        return array_values(
            array_filter(
                array_keys($allHolidays),
                static function (string $date) use ($minYear, $currentYear): bool {
                    $year = (int) substr($date, 0, 4);
                    return $year >= $minYear && $year <= $currentYear;
                }
            )
        );
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
