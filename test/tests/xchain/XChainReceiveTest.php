<?php

use LTBAuctioneer\Auctioneer\AuctioneerDaemon;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\AuctioneerDaemon\AuctioneerDaemonMockBuilder;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class XChainReceiveTest extends SiteTestCase
{

    public function testXChainReceiveNewBlock() {
        $app = Environment::initEnvironment('test');

        // install mocks
        AuctioneerDaemonMockBuilder::installMockAuctioneerDaemon($this, $app);

        // send a new block notification
        
        
    } 



    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    

}
