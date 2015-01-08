<?php

use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionUtil;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use LTBAuctioneer\Test\Util\RequestUtil;
use LTBAuctioneer\Test\XChain\XChainUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class CreateAuctionXchainTest extends SiteTestCase
{

    public function testNewAuctionCreatesNewAddressAndMonitor() {
        $app = Environment::initEnvironment('test');

        // track xchain calls
        $xchain_recorder = XChainUtil::installXChainMockClient($app, $this);

        $auction = AuctionUtil::createNewAuction($app);
        PHPUnit::assertNotNull($auction);

        // get the results from the mock xchain client
        PHPUnit::assertCount(2, $xchain_recorder->calls);
        PHPUnit::assertEquals('/addresses', $xchain_recorder->calls[0]['path']);
        PHPUnit::assertEquals('/monitors', $xchain_recorder->calls[1]['path']);
        PHPUnit::assertEquals('1oLaf1CoYcVE3aH5n5XeCJcaKPPGTxnxW', $xchain_recorder->calls[1]['data']['address']);
        PHPUnit::assertEquals('receive', $xchain_recorder->calls[1]['data']['monitorType']);

        // make sure the auction address was installed
        PHPUnit::assertEquals('1oLaf1CoYcVE3aH5n5XeCJcaKPPGTxnxW', $auction['auctionAddress']);
        
    }


}

