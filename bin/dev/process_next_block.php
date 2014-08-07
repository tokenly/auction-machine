#!/usr/local/bin/php
<?php 

declare(ticks=1);

use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Util\DB\DBUpdater;
use LTBAuctioneer\Util\DB\TestDBUpdater;
use LTBAuctioneer\Util\Twig\TwigUtil;

define(BASE_PATH, realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


// specify the spec as human readable text and run validation and help:
$values = CLIOpts\CLIOpts::run("
  Usage: 
  -e, --environment <environment> specify an environment
  -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;
$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

// run the follower daemon for one iteration
$daemon = $app['auctioneer.daemon'];
$daemon->setup();
$daemon->runOneIteration();

echo "done\n";
