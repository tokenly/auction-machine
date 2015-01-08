#!/usr/local/bin/php
<?php 


use LTBAuctioneer\Init\Environment;
use Tokenly\XChainClient\Client;

define('BASE_PATH', realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


$app_env = 'dev';
$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

print $app['config']['xchain.connectionUrl']."\n";

$client = $app['xchain.client'];
$address_info = $client->newPaymentAddress();

echo "\$address_info:\n".json_encode($address_info, 192)."\n";

