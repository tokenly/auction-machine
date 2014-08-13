#!/usr/local/bin/php
<?php 


use LTBAuctioneer\Init\Environment;


define('BASE_PATH', realpath(__DIR__.'/../..'));
require BASE_PATH.'/lib/vendor/autoload.php';


$app_env = isset($values['e']) ? $values['e'] : null;
$app = Environment::initEnvironment($app_env);
echo "Environment: ".$app['config']['env']."\n";


$values = CLIOpts\CLIOpts::run("
  Usage:
  -a, --auction-slug <slug> auction slug (required)
  -h, --help show this help
");


$auction = $app['directory']('Auction')->findBySlug($values['auction-slug']);
if (!$auction) { throw new Exception("Auction not found for slug: {$values['auction-slug']}", 1); }

$wif_private_key = $app['bitcoin.addressGenerator']->WIFPrivateKey($auction['keyToken']);

echo $wif_private_key."\n";
