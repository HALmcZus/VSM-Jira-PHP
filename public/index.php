<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\Container;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Load environment variables FIRST
 */
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

/**
 * Normalize env variables
 */
$_ENV['IS_DEMO'] = filter_var(
    $_ENV['IS_DEMO'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);

/**
 * Create container & app
 */
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

/**
 * Register routes
 */
(require __DIR__ . '/../src/routes.php')($app);

/**
 * Run app
 */
$app->run();
