<?php

use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Test\Auction\AuctionUtil;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class AuctionPayoutTest extends SiteTestCase
{

    public function testAuctionPayout() {
        $app = Environment::initEnvironment('test');

        // set up mocks
        $mock_xcpd_client = $this->getMockBuilder('\Utipd\XCPDClient\Client')->disableOriginalConstructor()->getMock();
        $app['xcpd.client'] = $mock_xcpd_client;

        $mock_native_client = $this->getMockBuilder('\Nbobtc\Bitcoind\Bitcoind')->disableOriginalConstructor()->getMock();
        $app['native.client'] = $mock_native_client;

        // Configure the mocks
        $importprivkey_called = 0;
        $mock_native_client->method('importprivkey')->will($this->returnCallback(function($key, $address, $scan) use (&$importprivkey_called)  {
            ++$importprivkey_called;
            return;
        }));

        $send_from_data = [];
        // $mock_native_client->method('sendfrom')->will($this->returnCallback(function($from, $to, $amount) use (&$send_from_data)  {
        //     $send_from_data = ['from' => $from, 'to' => $to, 'amount' => $amount];
        //     return 'faketxid';
        // }));
        $mock_native_client->method('createrawtransaction')->will($this->returnCallback(function($inputs, $outputs) use (&$send_from_data)  {
            foreach ($outputs as $to => $amount) {
                $send_from_data = ['to' => $to, 'amount' => $amount];
                break;
            }
            return 'faketxid';
        }));

        $mock_native_client->method('signrawtransaction')->will($this->returnCallback(function($raw_tx, $opts, $keys)  {
            return ['hex' => 'abcdef', 'complete' => true];
        }));


        // run a scenario
        $auction = AuctionStateUtil::runAuctionScenario($app, 5);

        $payer = $app['auction.payer'];
        $auction = $payer->payoutAuction($auction);

        // check receipts
        $auction = $auction->reload();
        $receipts = $auction['payoutReceipts'];
        PHPUnit::assertNotEmpty($receipts);
        PHPUnit::assertCount(4, $receipts);
#        Debug::trace("\$receipts=\n".json_encode($receipts, 192),__FILE__,__LINE__,$this);


        // check payouts through xcpd and btc clients
#        Debug::trace("\$send_from_data=\n".json_encode($send_from_data, 192),__FILE__,__LINE__,$this);
        // PHPUnit::assertEquals($auction['auctionAddress'], $send_from_data['from']);
        PHPUnit::assertEquals($auction['platformAddress'], $send_from_data['to']);

    } 

}

