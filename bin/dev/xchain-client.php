#!/usr/local/bin/php
<?php 


use LTBAuctioneer\Init\Environment;
use Tokenly\XChainClient\Client;

define('BASE_PATH', realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


$app_env = 'dev';
$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

$client = new Client('http://xchain.tokenly.dev:8036', 'key', 'secret');
$client->newPaymentAddress();

