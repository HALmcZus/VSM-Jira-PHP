<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/JiraClient.php';

class VsmController
{
    public function home(): void
    {
        $message = null;
        require __DIR__ . '/../views/home.php';
    }

    public function checkCredentials(): void
    {
        $jira = new JiraClient();
        $result = $jira->testCredentials();

        $message = $result['message'];
        require __DIR__ . '/../views/home.php';
    }
}