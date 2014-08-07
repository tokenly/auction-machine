<?php

namespace LTBAuctioneer\Init\DependencyInjections;


use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* XCPDInit
*/
class XCPDInit {

    public static function init($app) {
        self::initXCPD($app);
        self::initXCPDFollower($app);
        self::initNative($app);
        self::initNativeFollower($app);
        self::initAddresses($app);
    }

    public static function initXCPD($app) {

        $app['xcpd.connectionString'] = function($app) {
            return "{$app['config']['xcpd.scheme']}://{$app['config']['xcpd.host']}:{$app['config']['xcpd.port']}";

        };

        $app['xcpd.client'] = function($app) {
            return new \Utipd\XCPDClient\Client($app['xcpd.connectionString'], $app['config']['xcpd.rpcUser'], $app['config']['xcpd.rpcPassword']);
        };


    }

    public static function initXCPDFollower($app) {
        $app['mysql.xcpd.databaseName'] = function($app) {
            $prefix = $app['config']['mysqldb.prefix'] ?: 'utipd';
            return $prefix.'_xcpd_'.$app['config']['env'];
        };

        $app['xcpd.followerSetup'] = function($app) {
            return new \Utipd\CounterpartyFollower\FollowerSetup($app['mysql.client'], $app['mysql.xcpd.databaseName']);
        };

        $app['xcpd.follower'] = function($app) {
            $pdo = $app['mysql.client'];
            $pdo->query("use `".$app['mysql.xcpd.databaseName']."`");
            return new \Utipd\CounterpartyFollower\Follower($app['xcpd.client'], $pdo);
        };


    }

    public static function initNative($app) {

        $app['native.connectionString'] = function($app) {
            return "{$app['config']['native.scheme']}://{$app['config']['native.rpcUser']}:{$app['config']['native.rpcPassword']}@{$app['config']['native.host']}:{$app['config']['native.port']}";
        };

        $app['native.client'] = function($app) {
            return new \Nbobtc\Bitcoind\Bitcoind(new \Nbobtc\Bitcoind\Client($app['native.connectionString']));
        };


    }


    public static function initNativeFollower($app) {
        $app['mysql.native.databaseName'] = function($app) {
            $prefix = $app['config']['mysqldb.prefix'] ?: 'utipd';
            return $prefix.'_native_'.$app['config']['env'];
        };

        $app['native.followerSetup'] = function($app) {
            return new \Utipd\NativeFollower\FollowerSetup($app['mysql.client'], $app['mysql.native.databaseName']);
        };

        $app['native.follower'] = function($app) {
            $pdo = $app['mysql.client'];
            $pdo->query("use `".$app['mysql.native.databaseName']."`");
            return new \Utipd\NativeFollower\Follower($app['native.client'], $pdo);
        };


    }


    public static function initAddresses($app) {

        $app['bitcoin.addressGenerator'] = function($app) {
            return new \LTBAuctioneer\Bitcoin\BitcoinAddressGenerator($app['config']['bitcoin.masterKey']);
        };


    }


}

