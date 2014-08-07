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
  -s <scenario_number> load a scenario number (required)
  -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;

$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

// clear the db
TestDBUpdater::prepCleanDatabase($app);

// load scenario data
define('TEST_PATH', BASE_PATH.'/test');
$scenario_data = AuctionStateUtil::loadAuctionStateScenarioData('auction-scenario'.sprintf('%02d', $values['s']).'.yml');
// echo "\$scenario_data:\n".json_encode($scenario_data, 192)."\n";

// create an auction
$auction = $app['directory']('Auction')->createAndSave($scenario_data['auction']);

// create transactions
$transactions = [];
$xcp_tx_dir = $app['directory']('BlockchainTransaction');
foreach ($scenario_data['transactions'] as $transaction_entry) {
    $transactions[] = $xcp_tx_dir->createAndSave($transaction_entry);
}

// now build the state
$meta_info = $scenario_data['meta'];
$auction_state_vars = $app['auction.stateBuilder']->buildAuctionStateFromTransactions($transactions, $auction, $meta_info);

$auction->getDirectory()->update($auction, [
    'state'     => $auction_state_vars,
    'timePhase' => $auction_state_vars['timePhase'],
]);

print "\$auction is {$auction['slug']}\n";
