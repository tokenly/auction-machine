<?php

use LTBAuctioneer\Auctioneer\AuctioneerDaemon;
use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use LTBAuctioneer\Test\Auction\AuctionUtil;
use LTBAuctioneer\Test\AuctioneerDaemon\AuctioneerDaemonHandler;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class AuctioneerDaemonTest extends SiteTestCase
{

    public function testBasicAuctioneerDaemon() {
        $app = Environment::initEnvironment('test');

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonHandler($this, $app);

        // create an auction
        $auction = AuctionUtil::createNewAuction($app);

        // insert a sample counterparty transaction
        $sent_data = $mock_auctioneer_handler->sendMockCounterpartyTransaction($auction);

        // load the tx
        $tx_in_db = $app['directory']('BlockchainTransaction')->findOne([]);
        PHPUnit::assertEquals($sent_data['tx_index'], $tx_in_db['transactionId']);
        PHPUnit::assertEquals('incoming', $tx_in_db['classification']);
        PHPUnit::assertEquals($auction['id'], $tx_in_db['auctionId']);
        PHPUnit::greaterThanOrEqual(time() - 300, $tx_in_db['timestamp']);
    } 


    public function testAuctioneerDaemonWithOrphan() {
        $app = Environment::initEnvironment('test');

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonHandler($this, $app);

        // create an auction
        $auction = AuctionUtil::createNewAuction($app);

        // insert 2 sample counterparty transaction
        $sent_data = $mock_auctioneer_handler->sendMockCounterpartyTransaction($auction);
        $sent_data = $mock_auctioneer_handler->sendMockCounterpartyTransaction($auction, ['block_index' => 6001]);


        // load the tx's
        $txs = iterator_to_array($app['directory']('BlockchainTransaction')->findAll());
        PHPUnit::assertCount(2, $txs);

        // now orphan block 6001
        // $native_orphaned_block_function(6001);
        $mock_auctioneer_handler->orphanBlock(6001);


        // only 1 transaction is in the db
        $txs = iterator_to_array($app['directory']('BlockchainTransaction')->findAll());
        PHPUnit::assertCount(1, $txs);

        // check that transaction is 6000
        $tx_in_db = $app['directory']('BlockchainTransaction')->findOne([]);
        PHPUnit::assertEquals(90000, $tx_in_db['transactionId']);
        PHPUnit::assertEquals('incoming', $tx_in_db['classification']);
        PHPUnit::assertEquals(6000, $tx_in_db['blockId']);
        PHPUnit::assertEquals($auction['id'], $tx_in_db['auctionId']);
        PHPUnit::greaterThanOrEqual(time() - 300, $tx_in_db['timestamp']);
    } 


    public function testAuctioneerDaemonTimePhaseChanges() {
        $app = Environment::initEnvironment('test');

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonHandler($this, $app);

        // setup the daemon
        $daemon = $mock_auctioneer_handler->setupDaemon();


        // create an auction
        $now = time();
        $auction = AuctionUtil::createNewAuction($app, [
            'startDate' => date('m.d.Y g:i a', strtotime('+10 minutes')),
            'endDate'   => date('m.d.Y g:i a', strtotime('+1 day +20 minutes')),
        ]);

        // run an iteration
        $daemon->runOneIteration();

        // verify in prebid
        $auction = $app['directory']('Auction')->findOne([]);
        PHPUnit::assertEquals('prebid', $auction['state']['timePhase']);
        PHPUnit::assertEquals('prebid', $auction['timePhase']);

        // move to 15 minutes from now
        $GLOBALS['_TEST_NOW'] = strtotime('+15 minutes');

        // run an iteration
        $daemon->runOneIteration();

        // verify live
        $auction = $app['directory']('Auction')->findOne([]);
        PHPUnit::assertEquals('live', $auction['state']['timePhase']);
        PHPUnit::assertEquals('live', $auction['timePhase']);


        // move to 1 day, 30 minutes from now
        $GLOBALS['_TEST_NOW'] = strtotime('+1 day +30 minutes');

        // run an iteration
        $daemon->runOneIteration();

        // verify ended
        $auction = $app['directory']('Auction')->findOne([]);
        PHPUnit::assertEquals('ended', $auction['state']['timePhase']);
        PHPUnit::assertEquals('ended', $auction['timePhase']);
    } 

    public function testAuctioneerDaemonPayout() {
        $app = Environment::initEnvironment('test');

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonHandler($this, $app);

        // setup the daemon
        $daemon = $mock_auctioneer_handler->setupDaemon();

        // create an auction
        $auction = AuctionStateUtil::runAuctionScenario($app, 16);

        // move to 1 day, 30 minutes from now
        $GLOBALS['_TEST_NOW'] = strtotime('+1 day +30 minutes');

        // run an iteration
        $daemon->runOneIteration();

        // verify ended (not paidOut)
        $auction = $app['directory']('Auction')->findOne([]);
        PHPUnit::assertEquals('ended', $auction['state']['timePhase']);
        PHPUnit::assertEquals('ended', $auction['timePhase']);
        PHPUnit::assertEquals(false, !!$auction['paidOut']);

        // process a couple more native blocks (auction ends at 6006)
        $mock_auctioneer_handler->processNativeBlock(6009);
        
        // run an iteration
        $daemon->runOneIteration();

        $auction = $auction->reload();
        PHPUnit::assertEquals('ended', $auction['state']['timePhase']);
        PHPUnit::assertEquals('ended', $auction['timePhase']);
        PHPUnit::assertEquals(true, !!$auction['paidOut']);
    } 

    public function testAuctioneerBTCTransaction() {
        $app = Environment::initEnvironment('test');

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonHandler($this, $app);

        // setup the daemon
        $daemon = $mock_auctioneer_handler->setupDaemon();

        // create an auction
        $auction = AuctionStateUtil::runAuctionScenario($app, 1);
        PHPUnit::assertFalse($auction['state']['btcFeeSatisfied']);
        PHPUnit::assertEquals(0, $auction['state']['btcFeeApplied']);

        // receive a BTC payment
        $sent_tx_vars = $mock_auctioneer_handler->sendMockNativeTransaction($auction, ['amount' => 0.002, 'blockId' => 6002]);

        // make sure the tx was added
        $tx_in_db = $app['directory']('BlockchainTransaction')->findOne([], ['blockId' => -1]);
        PHPUnit::assertEquals($sent_tx_vars['txid'], $tx_in_db['transactionId']);
        PHPUnit::assertEquals(CurrencyUtil::numberToSatoshis(0.002), $tx_in_db['quantity']);
        PHPUnit::assertEquals(6002, $tx_in_db['blockId']);
        PHPUnit::assertEquals('incoming', $tx_in_db['classification']);
        PHPUnit::assertEquals($auction['id'], $tx_in_db['auctionId']);
        PHPUnit::greaterThanOrEqual(time() - 300, $tx_in_db['timestamp']);

        // run an iteration
        $daemon->runOneIteration();

        // make sure auction amount was updated
        $auction = $auction->reload();
        PHPUnit::assertFalse($auction['state']['btcFeeSatisfied']);
        PHPUnit::assertEquals(CurrencyUtil::numberToSatoshis(0.002), $auction['state']['btcFeeApplied']);


        // receive a BTC payment
        $sent_tx_vars = $mock_auctioneer_handler->sendMockNativeTransaction($auction, ['amount' => 0.003, 'blockId' => 6003]);

        // run an iteration
        $daemon->runOneIteration();

        // make sure auction amount was updated
        $auction = $auction->reload();
        PHPUnit::assertTrue($auction['state']['btcFeeSatisfied']);
        PHPUnit::assertEquals(CurrencyUtil::numberToSatoshis(0.005), $auction['state']['btcFeeApplied']);

    } 

    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    

}
