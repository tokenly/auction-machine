<?php

namespace LTBAuctioneer\Init\DependencyInjections;


use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* ControllersInit
*/
class ControllersInit {

    public static function init($app) {
        self::initControllers($app);
    }

    public static function initControllers($app) {

        $app['controller.auction.admin'] = function($app) {
            return new \LTBAuctioneer\Controller\Site\Auction\CreateAuctionController($app, $app['auction.manager'], $app['xcpd.follower']);
        };

        $app['controller.auction.public'] = function($app) {
            return new \LTBAuctioneer\Controller\Site\Auction\PublicAuctionController($app, $app['auction.manager'], $app['xcpd.follower']);
        };

        $app['controller.admin'] = function($app) {
            return new \LTBAuctioneer\Controller\Site\Admin\AdminController($app, $app['directory']('EventLog'), $app['directory']('Auction'));
        };

    }






}

