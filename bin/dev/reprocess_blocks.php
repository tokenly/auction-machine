#!/usr/local/bin/php
<?php 

declare(ticks=1);

use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Util\DB\DBUpdater;
use LTBAuctioneer\Util\DB\TestDBUpdater;
use LTBAuctioneer\Util\Twig\TwigUtil;

define('BASE_PATH', realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


// specify the spec as human readable text and run validation and help:
$values = CLIOpts\CLIOpts::run("
  Usage: 
  -e, --environment <environment> specify an environment
  -b <block_id> block id to restart from (required)
  -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;
$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

if (!intval($values['b'])) { throw new Exception("block_id must be at least 1", 1); }

$mysql = $app['mysql.client'];
$blockchain_tx_dir = $app['directory']('BlockchainTransaction');

$blockchain_tx_dir->deleteRaw("DELETE FROM {$blockchain_tx_dir->getTableName()} WHERE blockId > ?",[$values['b']]);

$sth = $mysql->prepare("DELETE FROM {$app['mysql.xcpd.databaseName']}.blocks WHERE blockId > ?");
$result = $sth->execute([$values['b']]);

$sth = $mysql->prepare("DELETE FROM {$app['mysql.native.databaseName']}.blocks WHERE blockId > ?");
$result = $sth->execute([$values['b']]);

echo "done\n";