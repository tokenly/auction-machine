<?php

namespace LTBAuctioneer\Init\DependencyInjections;

use Exception;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Controller\ControllerResolver;
use Symfony\Component\HttpFoundation\Response;

/*
* MonitorInit
*/
class MonitorInit {

    public static function init($app) {
        self::initMonitor($app);
    }

    public static function initMonitor($app) {
#        Debug::trace("initMonitor",__FILE__,__LINE__);

        $app['monitor.daemon'] = function($app) {
            return new \LTBAuctioneer\Checker\MonitorDaemon($app['xcpd.follower'], $app['native.follower'], $app['simpleDaemon']);
        };

    }


}

