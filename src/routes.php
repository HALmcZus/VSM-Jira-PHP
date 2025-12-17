<?php
use Slim\App;
use App\Controller\VsmController;

return function(App $app) {
    $app->get('/', [VsmController::class, 'index']);
    $app->post('/vsm', [VsmController::class, 'process']);
};
