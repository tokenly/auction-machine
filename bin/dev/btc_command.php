#!/usr/local/bin/php
<?php 

declare(ticks=1);

use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Util\Params\ParamsUtil;

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
    $params = null;
}
echo "\$params:\n".json_encode($params, 192)."\n";

// run the follower daemon
$native_client = $app['native.client'];
$result = $native_client->sendRequest($values['c'], $params);
echo json_encode($result, 192)."\n";

