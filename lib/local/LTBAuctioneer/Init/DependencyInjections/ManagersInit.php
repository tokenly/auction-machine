<?php

namespace LTBAuctioneer\Init\DependencyInjections;


use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* ManagersInit
*/
class ManagersInit {

    public static function init($app) {
        self::initManagers($app);
    }

    public static function initManagers($app) {

        $app['auction.defaults'] = function($app) {
            $defaults = $app['config']['auction.defaults'];
            $defaults['platformAddress'] = $app['config']['xcp.platformAddress'];
            return $defaults;
        };

        $app['auction.manager'] = function($app) {
            if ($app['env'] == 'test') {
                $native_client = null;
            } else {
                $native_client = $app['native.client'];
            }
            return new \LTBAuctioneer\Managers\AuctionManager($app['directory']('Auction'), $app['token.generator'], $app['slugger'], $app['bitcoin.addressGenerator'], $native_client, $app['auction.defaults']);
        };

    }






}

