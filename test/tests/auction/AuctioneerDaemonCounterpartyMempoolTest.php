<?php

use LTBAuctioneer\Auctioneer\AuctioneerDaemon;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Test\Auction\AuctionUtil;
use LTBAuctioneer\Test\AuctioneerDaemon\AuctioneerDaemonNotificationHandler;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class AuctioneerDaemonCounterpartyMempoolTest extends SiteTestCase
{

    public function testAuctioneerDaemonCounterpartyMempoolTXNormal() {
        $app = Environment::initEnvironment('test');

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonNotificationHandler($this, $app);

        // create an auction
        $auction = AuctionUtil::createNewAuction($app);

        // insert a sample counterparty transaction
        $sent_data = $mock_auctioneer_handler->sendMockCounterpartyTransaction($auction);

        // insert a sample mempool transaction
        $sent_data = $mock_auctioneer_handler->sendMockCounterpartyMempoolTransaction($auction);


        // make sure the mempool transaction is in the db
        $blockchain_tx_dir = $app['directory']('BlockchainTransaction');
        PHPUnit::assertCount(2, iterator_to_array($blockchain_tx_dir->findAll()));


        // now handle block 6001
        $sent_data = $mock_auctioneer_handler->processNewCounterpartyBlock(6001);

        // there should be only 1 blockchain transaction now
        PHPUnit::assertCount(1, iterator_to_array($blockchain_tx_dir->findAll()));

        // remaining tx is not a mempool transaction
        $blockchain_tx = $blockchain_tx_dir->findOne([]);
        PHPUnit::assertEquals(0, $blockchain_tx['isMempool']);
    } 

    // new block will always be called first,
    //   but test what happens just in case...
    public function testAuctioneerDaemonCounterpartyMempoolTXWhenNotCleared() {
        $app = Environment::initEnvironment('test');

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonNotificationHandler($this, $app);

        // create an auction
        $auction = AuctionUtil::createNewAuction($app);

        // insert a sample counterparty transaction
        $sent_data = $mock_auctioneer_handler->sendMockCounterpartyTransaction($auction);

        // insert a sample mempool transaction
        $sent_data = $mock_auctioneer_handler->sendMockCounterpartyMempoolTransaction($auction);

        // insert a sample counterparty transaction
        $sent_data = $mock_auctioneer_handler->sendMockCounterpartyTransaction($auction, ['tx_hash' => 'mmyhash',]);

        // there should be only 2 blockchain transactions now
        $blockchain_tx_dir = $app['directory']('BlockchainTransaction');
        PHPUnit::assertCount(2, iterator_to_array($blockchain_tx_dir->findAll()));

        // remaining tx is not a mempool transaction
        $blockchain_tx = $blockchain_tx_dir->findOne(['tx_hash' => 'mmyhash']);
        PHPUnit::assertEquals(0, $blockchain_tx['isMempool']);
        PHPUnit::assertEquals('sender01', $blockchain_tx['source']);
    } 



    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    

}
