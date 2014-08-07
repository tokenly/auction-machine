#!/usr/local/bin/php
<?php 

declare(ticks=1);

use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Util\DB\DBUpdater;
use LTBAuctioneer\Util\DB\TestDBUpdater;
use LTBAuctioneer\Util\Twig\TwigUtil;

define(BASE_PATH, realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


// specify the spec as human readable text and run validation and help:
$values = CLIOpts\CLIOpts::run("
  Usage: 
  -e, --environment <environment> specify an environment
  -c <command> [get_running_info] (required)
  -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;

$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";


// run the follower daemon
$xcp_client = $app['xcpd.client'];
switch ($values['c']) {
    case 'get_running_info':
        $result = $xcp_client->get_running_info([]);
        break;
    
    default:
        throw new Exception("Unknown command: {$values['c']}", 1);
        
        break;
}
echo json_encode($result, 192)."\n";

