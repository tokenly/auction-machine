<?php

namespace LTBAuctioneer\Init\DependencyInjections;

use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* PusherInit
*/
class PusherInit {

    public static function init($app) {
        self::initPusher($app);
    }

    public static function initPusher($app) {
        $app['pusherClient'] = function($app) {
            $faye_client = new \Nc\FayeClient\Client(new \Nc\FayeClient\Adapter\CurlAdapter(), $app['config']['pusher.serverUrl'].'/public');
            return new \Tokenly\PusherClient\Client($faye_client, $app['config']['pusher.password']);
        };

    }


}

