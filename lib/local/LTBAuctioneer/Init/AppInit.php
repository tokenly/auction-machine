<?php

namespace LTBAuctioneer\Init;

use Exception;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\EventLog\EventLog;
use LTBAuctioneer\Init\DependencyInjections\ApplicationInit;
use LTBAuctioneer\Init\DependencyInjections\AuctioneerInit;
use LTBAuctioneer\Init\DependencyInjections\ControllersInit;
use LTBAuctioneer\Init\DependencyInjections\ManagersInit;
use LTBAuctioneer\Init\DependencyInjections\MysqlInit;
use LTBAuctioneer\Init\DependencyInjections\PusherInit;
use LTBAuctioneer\Init\DependencyInjections\RedisInit;
use LTBAuctioneer\Init\DependencyInjections\UtilsInit;
use LTBAuctioneer\Init\DependencyInjections\XCPDInit;
use LTBAuctioneer\Init\DependencyInjections\XChainInit;

/*
* AppInit
*/
class AppInit
{

    public static function initApp($app_env=null, $config_location=null) {
        // init environment
        if ($app_env === null) { $app_env = getenv('APP_ENV') ?: 'prod'; }

        // build the silex app
        $app = new \LTBAuctioneer\Application\Application();

        // environment
        $app['env'] = $app_env;

        // various dependency injections
        ApplicationInit::init($app);
        MysqlInit::init($app);
        ControllersInit::init($app);
        XCPDInit::init($app);
        ManagersInit::init($app);
        AuctioneerInit::init($app);
        UtilsInit::init($app);
        RedisInit::init($app);
        XChainInit::init($app);
        PusherInit::init($app);


        // special case for application-wide event log
        $app['event.log'] = $app->share(function($app) {
            return new EventLog($app['directory']('EventLog'), $app['config']['log.debug']);
        });
        EventLog::init($app['event.log']);


        return $app;
    }

}

