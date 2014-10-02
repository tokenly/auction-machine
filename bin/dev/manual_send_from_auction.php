#!/usr/local/bin/php
<?php 

declare(ticks=1);

use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Util\DB\DBUpdater;
use LTBAuctioneer\Util\DB\TestDBUpdater;
use LTBAuctioneer\Util\Params\ParamsUtil;
use LTBAuctioneer\Util\Twig\TwigUtil;

define('BASE_PATH', realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


// specify the spec as human readable text and run validation and help:
//   -e, --environment <environment> specify an environment

$values = CLIOpts\CLIOpts::run("
    Usage:
    -a, --auction-slug <slug> auction address to send from (required)
    -t, --token <token> token to send [not BTC] (required)
    -q, --quantity <quantity> quantity to send in decimal (not satoshis) (required)
    -d, --destination <destination> address to send to (required)
    -h, --help show this help
");

$app_env = isset($values['e']) ? $values['e'] : null;

$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

try {
            
    $auction = $app['directory']('Auction')->findBySlug($values['auction-slug']);
    if (!$auction) { throw new Exception("Auction not found for slug: {$values['auction-slug']}", 1); }

    print "Updating \$auction {$auction['slug']}\n";

    $xcpd_client = $app['xcpd.client'];

    // find out if it is a divisible asset
    $assets = $xcpd_client->get_asset_info(['assets' => [$token]]);
    $is_divisible = $assets[0]['divisible'];

    // determine quantity
    // quantity is different if divisible
    $quantity = $values['q'];
    $quantity = intval($is_divisible ? CurrencyUtil::numberToSatoshis($quantity) : $quantity);

    $send_vars = [
        'source'             => $auction['auctionAddress'],
        'destination'        => $values['d'],
        'asset'              => $values['t'],

        'quantity'           => $quantity,

        // custom dust size and fees
        'multisig_dust_size' => CurrencyUtil::numberToSatoshis($app['config']['xcp.payout']['multisig_dust_size']),
        'fee_per_kb'         => CurrencyUtil::numberToSatoshis($app['config']['xcp.payout']['fee_per_kb']),
    ];
    if ($app['config']['xcp.payout']['allow_unconfirmed_inputs']) { $send_vars['allow_unconfirmed_inputs'] = true; }
    echo "\$send_vars:\n".json_encode($send_vars, 192)."\n";

    // unlock the wallet with the passphrase
    $app['native.client']->walletpassphrase($app['config']['bitcoin.passphrase'], 60);

    // create a send
    $raw_tx = $xcpd_client->create_send($send_vars);

    // sign the transaction
    $signed_tx = $xcpd_client->sign_tx(["unsigned_tx_hex" => $raw_tx]);

    // broadcast the transaction
    $transaction_id = $xcpd_client->broadcast_tx(["signed_tx_hex" => $signed_tx]);


    echo "\$transaction_id=$transaction_id\n";
    echo "done\n";


} catch (\Exception $e) {
    echo "ERROR: ".$e->getMessage()."\n";
}
