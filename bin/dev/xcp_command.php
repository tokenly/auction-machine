#!/usr/local/bin/php
<?php 

declare(ticks=1);

use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Util\DB\DBUpdater;
use LTBAuctioneer\Util\DB\TestDBUpdater;
use LTBAuctioneer\Util\Twig\TwigUtil;

define('BASE_PATH', realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


// specify the spec as human readable text and run validation and help:
$values = CLIOpts\CLIOpts::run("
  Usage: 
  -c <command> [get_info] (required)
  -p <params> yaml encoded params
  -h, --help show this help
");

$app = Environment::initEnvironment();

if (isset($values['p'])) {
    $params = ParamsUtil::interpretJSONOrYaml($values['p']);
} else {
    $params = [];
}


// run the follower daemon
$xcp_client = $app['xcpd.client'];
$result = $xcp_client->__call($values['c'], $params);
echo json_encode($result, 192)."\n";


