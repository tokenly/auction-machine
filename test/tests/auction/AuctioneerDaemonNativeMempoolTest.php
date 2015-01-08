<?php

use LTBAuctioneer\Auctioneer\AuctioneerDaemon;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Test\Auction\AuctionUtil;
use LTBAuctioneer\Test\AuctioneerDaemon\AuctioneerDaemonNotificationHandler;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use LTBAuctioneer\Test\XChain\XChainUtil;
use Utipd\CurrencyLib\CurrencyUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class AuctioneerDaemonNativeMempoolTest extends SiteTestCase
{

    public function testAuctioneerDaemonNativeMempoolTXNormal() {
        $app = Environment::initEnvironment('test');
        XChainUtil::installXChainMockClientIfNeeded($app, $this);

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonNotificationHandler($this, $app);

        // create an auction
        $auction = AuctionUtil::createNewAuction($app);

        // insert a sample native transaction
        $sent_tx_vars = $mock_auctioneer_handler->sendMockNativeTransaction($auction, ['amount' => 0.002, 'blockId' => 6000]);

        // insert a sample mempool transaction
        $sent_tx_vars = $mock_auctioneer_handler->sendMockNativeTransaction($auction, ['amount' => 0.002, 'is_mempool' => true], 6000);


        // make sure the mempool transaction is in the db
        $blockchain_tx_dir = $app['directory']('BlockchainTransaction');
        PHPUnit::assertCount(2, iterator_to_array($blockchain_tx_dir->findAll()));


        // now handle block 6001
        $sent_data = $mock_auctioneer_handler->processNativeBlock(6001);

        // there should be only 1 blockchain transaction now
        PHPUnit::assertCount(1, iterator_to_array($blockchain_tx_dir->findAll()));

        // remaining tx is not a mempool transaction
        $blockchain_tx = $blockchain_tx_dir->findOne([]);
        PHPUnit::assertEquals(0, $blockchain_tx['isMempool']);
    } 

    // new block will always be called first,
    //   but test what happens just in case...
    public function testAuctioneerDaemonNativeMempoolTXWhenNotCleared() {
        $app = Environment::initEnvironment('test');
        XChainUtil::installXChainMockClientIfNeeded($app, $this);

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonNotificationHandler($this, $app);

        // create an auction
        $auction = AuctionUtil::createNewAuction($app);

        // insert a sample native transaction
        $sent_tx_vars = $mock_auctioneer_handler->sendMockNativeTransaction($auction, ['amount' => 0.002, 'blockId' => 6000]);

        // insert a sample mempool transaction
        $sent_tx_vars = $mock_auctioneer_handler->sendMockNativeTransaction($auction, ['amount' => 0.002, 'txid' => 'mmyhash', 'is_mempool' => true]);

        // insert a sample native transaction (with same tx as mempool tx)
        $sent_tx_vars = $mock_auctioneer_handler->sendMockNativeTransaction($auction, ['amount' => 0.003, 'txid' => 'mmyhash', 'blockId' => 6001]);

        // there should be only 2 blockchain transactions now
        $blockchain_tx_dir = $app['directory']('BlockchainTransaction');
        PHPUnit::assertCount(2, iterator_to_array($blockchain_tx_dir->findAll()));

        // remaining tx is not a mempool transaction
        $blockchain_tx = $blockchain_tx_dir->findOne(['tx_hash' => 'mmyhash']);
        PHPUnit::assertEquals(0, $blockchain_tx['isMempool']);
        PHPUnit::assertEquals(300000, $blockchain_tx['quantity']);
    } 



    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    

}
