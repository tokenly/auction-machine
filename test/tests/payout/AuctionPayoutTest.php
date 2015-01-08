<?php

use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Test\Auction\AuctionUtil;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use LTBAuctioneer\Test\XChain\XChainUtil;
use \PHPUnit_Framework_Assert as PHPUnit;
use Utipd\CurrencyLib\CurrencyUtil;

/*
* 
*/
class AuctionPayoutTest extends SiteTestCase
{

    public function testAuctionPayout() {
        $app = Environment::initEnvironment('test');

        // install mocks
        $xchain_recorder = XChainUtil::installXChainMockClientIfNeeded($app, $this);

        // run a scenario
        $auction = AuctionStateUtil::runAuctionScenario($app, 5);

        // payout
        $payer = $app['auction.payer'];
        $auction = $payer->payoutAuction($auction);

        // check receipts
        $auction = $auction->reload();
        $receipts = $auction['payoutReceipts'];
        PHPUnit::assertNotEmpty($receipts);
        PHPUnit::assertCount(4, $receipts);
#        Debug::trace("\$receipts=\n".json_encode($receipts, 192),__FILE__,__LINE__,$this);
        // echo "\$receipts:\n".json_encode($receipts, 192)."\n";
        foreach ($receipts as $receipt) {
            $first_receipt = $receipt;
            break;
        }

        // amount sent
        PHPUnit::assertEquals(1, CurrencyUtil::satoshisToNumber($first_receipt['amountSent']));

    } 

}

