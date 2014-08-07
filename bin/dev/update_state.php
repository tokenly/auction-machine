#!/usr/local/bin/php
<?php 

declare(ticks=1);

use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Util\DB\DBUpdater;
use LTBAuctioneer\Util\DB\TestDBUpdater;
use LTBAuctioneer\Util\Params\ParamsUtil;
use LTBAuctioneer\Util\Twig\TwigUtil;

define(BASE_PATH, realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


// specify the spec as human readable text and run validation and help:
//   -e, --environment <environment> specify an environment

$values = CLIOpts\CLIOpts::run("
  Usage: <update_yaml>
  -a, --auction-slug <slug> auction slug (required)

  -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;

$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

try {
  
    $auction = $app['directory']('Auction')->findBySlug($values['auction-slug']);
    if (!$auction) { throw new Exception("Auction not found for slug: {$values['auction-slug']}", 1); }

    $update_vars = ParamsUtil::interpretJSONOrYaml($values['update_yaml']);
    if (!$update_vars) { throw new Exception("Unable to decod update vars JSON", 1); }

    $state = $auction['state'];
    $state = array_merge($state, $update_vars);
    $db_update_vars = ['state' => $state];

    if (isset($update_vars['timePhase'])) {
      $db_update_vars['timePhase'] = $update_vars['timePhase'];
    }

    $auction->getDirectory()->update($auction, $db_update_vars);
    $auction = $auction->reload();



    // publish the state
    $tx = $app['directory']('BlockchainTransaction')->findOne([], ['blockId' => -1]);
    $app['auction.publisher']->publishAuctionState($auction);


} catch (\Exception $e) {
  echo "ERROR: ".$e->getMessage()."\n";
}
