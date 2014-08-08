#!/usr/local/bin/php
<?php 

declare(ticks=1);

use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
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
  -s, --source <source> source (required)

  -b, --block <block_index> Block index (6006)
  -n, --now <time> Time of transaction (auction start time)
  -c, --classification <classification> Classification (incoming)
  -t, --token <token> Classification (LTBCOIN)
  -q, --quantity <quantity> quantity (1000)
  -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;

$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";


$auction = $app['directory']('Auction')->findBySlug($values['auction-slug']);
if (!$auction) { throw new Exception("Auction not found for slug: {$values['auction-slug']}", 1); }
print "\$auction is {$auction['slug']}\n";

$send_data = [
    'classification' => isset($values['c']) ? $values['c'] : 'incoming',
    'block_index'    => isset($values['b']) ? $values['b'] : '6006',
    'asset'          => isset($values['t']) ? $values['t'] : 'LTBCOIN',
    'quantity'       => CurrencyUtil::numberToSatoshis(isset($values['q']) ? $values['q'] : '1000'),

    'source'         => $values['s'],
];

// add a new transaction
$new_transaction = $app['directory']('BlockchainTransaction')->createAndSave([
    'auctionId'      => $auction['id'],
    'transactionId'  => md5(uniqid()),
    'blockId'        => $send_data['block_index'],

    'classification' => $send_data['classification'],

    'source'         => $send_data['source'],
    'destination'    => $auction['auctionAddress'],
    'asset'          => $send_data['asset'],
    'quantity'       => $send_data['quantity'],
    'status'         => 'valid',
    'tx_hash'        => md5(uniqid()),

    'timestamp'      => isset($values['n']) ? strtotime($values['n']) : $auction['startDate'],
]);



$auction = $app['auction.updater']->updateAuctionState($auction, $new_transaction['blockId']);
Debug::trace("\$auction state:",$auction['state'],__FILE__,__LINE__,$this);
$app['auction.publisher']->publishAuctionState($auction, $new_transaction['blockId']);

