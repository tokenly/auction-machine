<?php

namespace LTBAuctioneer\Test\AuctioneerDaemon;

use Exception;
use LTBAuctioneer\Auctioneer\AuctioneerDaemon;
use LTBAuctioneer\Debug\Debug;

/*
* AuctioneerDaemonMockBuilder
*/
class AuctioneerDaemonMockBuilder
{

    public static function installMockAuctioneerDaemon($test_case, $app) {
        $method_recorder = new \stdClass();
        $method_recorder->calls = [];

        // methods we will record
        $methods = ['handleNewBlock'];

        // init mock
        $auctioneer_daemon = $test_case->getMockBuilder('\LTBAuctioneer\Auctioneer\AuctioneerDaemon')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();

        // override all methods
        foreach($methods as $method) {
            $auctioneer_daemon->method($method)->will($test_case->returnCallback(function() use ($method, $method_recorder) {
                // store the method for test verification
                $method_recorder->calls[] = [
                    'method' => $method,
                    'args'   => (array)func_get_args(),
                ];
                return;
            }));
        }

        // install the daemon into the DI container
        $app['auctioneer.daemon'] = $auctioneer_daemon;

        return $method_recorder;
    }

    ////////////////////////////////////////////////////////////////////////


}

