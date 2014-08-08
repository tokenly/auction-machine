#!/usr/local/bin/php
<?php 

use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Util\DB\DBUpdater;
use LTBAuctioneer\Util\DB\TestDBUpdater;
use LTBAuctioneer\Util\Twig\TwigUtil;

define('BASE_PATH', realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


// specify the spec as human readable text and run validation and help:
$values = CLIOpts\CLIOpts::run("
  Usage: 
  -e, --environment <environment> specify an environment
  -d, --drop-all drop all tables DANGER!
  -f, --force force for production environment
  -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;

$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

// drop tables
if (isset($values['d'])) {
    if ($app['config']['env'] == 'prod' AND !isset($values['f'])) {
        echo "Force flag required for environment {$app['config']['env']}\n";
        exit(1);
    }
    TestDBUpdater::prepCleanDatabase($app);

    // erase the xcpd/native database
    $app['xcpd.followerSetup']->eraseDatabase();
    $app['native.followerSetup']->eraseDatabase();
}

// update SQL tables
DBUpdater::bringDatabaseUpToDate($app);

// update the xcpd/native database
$app['xcpd.followerSetup']->InitializeDatabase();
$app['native.followerSetup']->InitializeDatabase();

echo "done\n";