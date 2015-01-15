<?php

use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\AuctioneerDaemon\AuctioneerDaemonNotificationHandler;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class AuctioneerBlocksTest extends SiteTestCase
{

    public function testAuctioneerDaemonProcessBlock() {
        $app = Environment::initEnvironment('test');

        // handle the daemon mocks
        $mock_auctioneer_handler = new AuctioneerDaemonNotificationHandler($this, $app);

        // now handle block 6001
        $sent_data = $mock_auctioneer_handler->processNativeBlock(6001);

        $block_dir = $app['directory']('Block');


        // there should be only 1 block
        PHPUnit::assertCount(1, iterator_to_array($block_dir->findAll()));

        // remaining tx is not a mempool transaction
        $block = $block_dir->findOne([]);
        PHPUnit::assertEquals(6001, $block['blockId']);
        PHPUnit::assertEquals('0000000000000000000000000000000000000000000000000000000000006001', $block['blockHash']);
        PHPUnit::assertGreaterThan(1388556000, $block['blockDate']);
    } 

    

    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    

}
