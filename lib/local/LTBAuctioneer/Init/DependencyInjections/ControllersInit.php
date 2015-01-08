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
        $app['controller.admin.auction'] = function($app) {
            return new \LTBAuctioneer\Controller\Site\Admin\AdminAuctionEditController($app, $app['auction.manager']);
        };

        $app['controller.plain'] = function($app) {
            return new \LTBAuctioneer\Controller\Site\Plain\PlainController($app);
        };

        $app['controller.webhook'] = function($app) {
            return new \LTBAuctioneer\Controller\Site\Webhook\ReceiveWebhookController($app, $app['xchain.webhook.receiver']);
        };

    }






}

