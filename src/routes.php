<?php

use Slim\App;
use App\Controller\VersionController;

return function (App $app) {
    $app->get('/', [VersionController::class, 'index']);
    $app->post('/vsm', [VersionController::class, 'process']);
};
