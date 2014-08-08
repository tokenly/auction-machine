<?php 

use LTBAuctioneer\Init\Environment;

define('BASE_PATH', realpath(__DIR__.'/..'));

require __DIR__.'/../lib/vendor/autoload.php';

$app = Environment::initEnvironment();

// route
$app['router.site']->route();
$app['router.admin']->route();

// run
$app->run(); 

