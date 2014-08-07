#!/usr/local/bin/php
<?php 


use LTBAuctioneer\Init\Environment;
use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BitcoinLib;


define(BASE_PATH, realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


$app_env = isset($values['e']) ? $values['e'] : null;
$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

// throw new AdapterException();

