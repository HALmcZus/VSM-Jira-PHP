<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/VsmController.php';

$controller = new VsmController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->checkCredentials();
} else {
    $controller->home();
}