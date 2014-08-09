<?php

use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionStateUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class AuctionStateBuilderTest extends \PHPUnit_Framework_TestCase
{

    public function testSingleScenarioAuctionStateBuilder() {
        $app = Environment::initEnvironment('test');

        $state = getenv('SCENARIO');

        if ($state === false) { $state = 1; }
        $state_vars = AuctionStateUtil::buildValidAuctionStateFromScenario($app, 'auction-scenario'.sprintf('%02d', $state).'.yml');
        // echo "\$state_vars:\n".json_encode($state_vars, 192)."\n";
    } 

    public function testAllAuctionScenarios() {
        $app = Environment::initEnvironment('test');

        // do all state tests in directory
        $state_count = count(glob(TEST_PATH.'/etc/*.yml'));
        PHPUnit::assertGreaterThan(1, $state_count);
        for ($i=1; $i <= $state_count; $i++) { 
            $filename = "auction-scenario".sprintf('%02d', $i).".yml";
#           Debug::trace("$filename",__FILE__,__LINE__,$this);
            $state_vars = AuctionStateUtil::buildValidAuctionStateFromScenario($app, $filename);
        }
    }


    

}
