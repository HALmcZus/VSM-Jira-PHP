<?php
use Slim\App;
use App\Controller\VSMController;

return function(App $app) {
    $app->get('/', [VSMController::class, 'index']);
    $app->post('/vsm', [VSMController::class, 'process']);
};
