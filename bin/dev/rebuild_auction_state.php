#!/usr/local/bin/php
<?php 

declare(ticks=1);

use LTBAuctioneer\Auctioneer\Updater\AuctionUpdater;
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
    -a, --auction-slug <slug> auction slug (required)

    -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;

$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

try {
    $auction = $app['directory']('Auction')->findBySlug($values['auction-slug']);
    if (!$auction) { throw new Exception("Auction not found for slug: {$values['auction-slug']}", 1); }

    // get the most recent tx
    $tx = $app['directory']('BlockchainTransaction')->findOne(['isMempool' => 0], ['blockId' => -1]);

    // rebuild the auction state
    $auction = $app['auction.updater']->updateAuctionState($auction, $tx['blockId']);

    // publish auction state
    $app['auction.publisher']->publishAuctionState($auction);


} catch (\Exception $e) {
    echo "ERROR: ".$e->getMessage()."\n";
}
