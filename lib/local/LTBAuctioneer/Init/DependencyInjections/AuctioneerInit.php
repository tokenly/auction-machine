<?php

namespace LTBAuctioneer\Init\DependencyInjections;

use Exception;
use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Controller\ControllerResolver;
use Symfony\Component\HttpFoundation\Response;

/*
* AuctioneerInit
*/
class AuctioneerInit {

    public static function init($app) {
        self::initAuctioneer($app);
    }

    public static function initAuctioneer($app) {
#        Debug::trace("initAuctioneer",__FILE__,__LINE__);

        $app['auctioneer'] = function($app) {
            return new \LTBAuctioneer\Auctioneer\Builder\Auctioneer();
        };

        $app['auctioneer.daemon'] = function($app) {
            return new \LTBAuctioneer\Auctioneer\AuctioneerDaemon($app['xcpd.follower'], $app['native.follower'], $app['simpleDaemon'], $app['auction.manager'], $app['auction.updater'], $app['auction.payer'], $app['auction.publisher'], $app['directory']('BlockchainTransaction'));
        };

        $app['auction.stateBuilder'] = function($app) {
            return new \LTBAuctioneer\Auctioneer\Builder\AuctionStateBuilder();
        };

        $app['simpleDaemon'] = function($app) {
            return function($loop_function, Callable $error_handler=null) use ($app) {
                return new \Utipd\SimpleDaemon\Daemon($loop_function, $error_handler, $app['monolog']);
            };
        };

        $app['auction.updater'] = function($app) {
            return new \LTBAuctioneer\Auctioneer\Updater\AuctionUpdater($app['auction.manager'], $app['auction.stateBuilder'], $app['directory']('BlockchainTransaction'));
        };

        $app['auction.payer'] = function($app) {
            return new \LTBAuctioneer\Auctioneer\Payer\AuctionPayer($app['auction.manager'], $app['xcpd.client'], $app['native.client'], $app['bitcoin.addressGenerator'], $app['config']['xcp.payout'], $app['config']['payouts.debug']);
        };

        $app['auction.publisher'] = function($app) {
            return new \LTBAuctioneer\Auctioneer\Publisher\AuctionDataPublisher($app['redis'], $app['xcpd.follower']);
        };


    }


}

