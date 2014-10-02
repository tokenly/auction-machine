#!/usr/local/bin/php
<?php 

declare(ticks=1);

use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Util\DB\DBUpdater;
use LTBAuctioneer\Util\DB\TestDBUpdater;
use LTBAuctioneer\Util\Twig\TwigUtil;

define('BASE_PATH', realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


// specify the spec as human readable text and run validation and help:
//   -e, --environment <environment> specify an environment

$values = CLIOpts\CLIOpts::run("
  Usage: 
  -s, --seed <seed> seed 

  -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;

$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

$seed = isset($values['s']) ? $values['s'] : md5(uniqid());
$key = \BitWasp\BitcoinLib\BIP32::master_key($seed);
echo "\$key:\n".json_encode($key, 192)."\n";
