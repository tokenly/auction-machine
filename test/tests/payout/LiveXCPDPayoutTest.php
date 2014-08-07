<?php

use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Test\Auction\AuctionUtil;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class LiveXCPDPayoutTest extends SiteTestCase
{

    public function testSignLiveSendTransaction() {
        $this->markTestIncomplete();
        $app = Environment::initEnvironment('test');

        $source      = getenv('TEST_TX_SOURCE');
        $dest        = getenv('TEST_TX_DEST');
        $asset       = getenv('TEST_TX_ASSET');
        $quantity    = getenv('TEST_TX_QUANTITY');
        $private_key = getenv('TEST_TX_PRIVATE_KEY');


        // make sure bitcoind has the addres in the wallet
        $result = $app['native.client']->importprivkey($private_key, "My Test One", false);
        // echo "importprivkey \$result:\n".json_encode($result, 192)."\n";

        // create_send(source, destination, asset, quantity, encoding='multisig', pubkey=null)
        $xcpd = $app['xcpd.client'];
        $send_vars = [
            'source'      => $source,
            'destination' => $dest,
            'asset'       => $asset,
            // 'quantity'    => CurrencyUtil::numberToSatoshis($quantity),
            'quantity'    => intval($quantity),
        ];
        // echo "\$send_vars:\n".json_encode($send_vars, 192)."\n";
        $raw_tx = $xcpd->create_send($send_vars);
        // echo "\$raw_tx:\n".json_encode($raw_tx, 192)."\n";


        // sign the transaction
        $signed_tx = $xcpd->sign_tx(["unsigned_tx_hex" => $raw_tx]);

        // broadcast the transaction
        $broadcast_result = $xcpd->broadcast_tx(["signed_tx_hex" => $signed_tx]);
        // echo "\$broadcast_result:\n".json_encode($broadcast_result, 192)."\n";


    } 

    public function testSignLiveSendBTCTransaction() {
        $this->markTestIncomplete();

        $app = Environment::initEnvironment('test');
        $bitcoind = $app['native.client'];

        $source      = getenv('TEST_TX_SOURCE');
        $dest        = getenv('TEST_TX_DEST');
        $private_key = getenv('TEST_TX_PRIVATE_KEY');

        // 
        $result = $app['native.client']->importprivkey($private_key, $source, false);


        // get all funds (use blockr)
        // http://btc.blockr.io/api/v1/address/unspent/xxxxxx
        $client = new \GuzzleHttp\Client(['base_url' => 'http://btc.blockr.io',]);
        $response = $client->get('/api/v1/address/unspent/'.$source);
        $json_data = $response->json();
        // echo "\$json_data:\n".json_encode($json_data, 192)."\n";
        $amount = 0;
        foreach ($json_data['data']['unspent'] as $unspent) {
            if ($unspent['amount'] < 0.0001) {
                // assume xcp transaction
            }
            $amount += $unspent['amount'];
        }
        $float_balance = $amount;

        // send
        $fee = 0.0001;
        while ($fee < 0.0010) {
            try {
                $result = $bitcoind->sendfrom($source, $dest, ($float_balance - $fee));
            } catch (Exception $e) {
                if ($e->getCode() == -4) {
                    // This transaction requires a transaction fee of at least 0.0001 because of its amount, complexity, or use of recently received funds!
                    $fee += 0.0001;
                    continue;
                }

                throw $e;
            }
            if (!$result) { throw new Exception("Could not send with fee up to $fee", 1); }
        }
    } 


}

