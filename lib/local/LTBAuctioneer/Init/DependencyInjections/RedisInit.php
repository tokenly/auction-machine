<?php

namespace LTBAuctioneer\Init\DependencyInjections;

use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* RedisInit
*/
class RedisInit {

    public static function init($app) {
        self::initRedis($app);
    }

    public static function initRedis($app) {
#        Debug::trace("initAuctioneer",__FILE__,__LINE__);

        $app['redis'] = function($app) {
            return new \Predis\Client([
                'scheme' => 'tcp',
                'host'   => $app['config']['redis.host'],
                'port'   => $app['config']['redis.port'],
            ]);
        };




    }

}

