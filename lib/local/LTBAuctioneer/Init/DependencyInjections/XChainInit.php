<?php

namespace LTBAuctioneer\Init\DependencyInjections;


use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* XChainInit
*/
class XChainInit {

    public static function init($app) {
        self::initXChainConfig($app);
        self::initXChainClient($app);
        self::initXChainWebhookReceiver($app);
    }

    public static function initXChainConfig($app) {
        $app['xchain.webhook_endpoint_path'] = '/_xchain_receive';
        $app['xchain.webhook_endpoint'] = $app['config']['xchain.localWebhookHost'].$app['xchain.webhook_endpoint_path'];
    }

    public static function initXChainClient($app) {
        $app['xchain.client'] = function($app) {
            return new \Tokenly\XChainClient\Client($app['config']['xchain.connectionUrl'], $app['config']['xchain.apiToken'], $app['config']['xchain.apiKey']);
        };
    }

    public static function initXChainWebhookReceiver($app) {
        $app['xchain.webhook.receiver'] = function($app) {
            return new \Tokenly\XChainClient\WebHookReceiver($app['config']['xchain.apiToken'], $app['config']['xchain.apiKey']);
        };
    }




}

