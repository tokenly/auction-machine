#!/usr/local/bin/php
<?php 


use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Init\Environment;
use Utipd\BitcoinPayer\BitcoinPayer;


define('BASE_PATH', realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


$app_env = isset($values['e']) ? $values['e'] : null;
$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";

// throw new AdapterException();

$private_key = getenv('PRIVATE_KEY');
$src_address = getenv('SRC_ADDRESS');
$dest_address = getenv('DEST_ADDRESS');
$sweeper = new BitcoinPayer($app['native.client']);
$fee = $app['config']['xcp.payout']['fee_per_kb'];
list($transaction_id, $float_balance_sent) = $sweeper->sweepBTC($src_address, $dest_address, $private_key, $fee);
$result = [$transaction_id, CurrencyUtil::numberToSatoshis($float_balance_sent)];
echo "\$result:\n".json_encode($result, 192)."\n";


