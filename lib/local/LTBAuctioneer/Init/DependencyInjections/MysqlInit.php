<?php

namespace LTBAuctioneer\Init\DependencyInjections;


use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* MysqlInit
*/
class MysqlInit {

    public static function init($app) {
        self::initMysql($app);
        self::initModelFactories($app);
    }



    public static function initMysql($app) {
        // mysql
        $app['mysql.databaseName'] = function($app) {
            $prefix = $app['config']['mysqldb.prefix'] ?: 'utipd';
            return $prefix.'_'.$app['config']['env'];
        };
        $app['mysql.connection'] = function($app) {
            return "mysql:host={$app['config']['mysql.host']};port={$app['config']['mysql.port']}";
        };
        $app['mysql.client'] = function($app) {
            $pdo = new \Utipd\MysqlModel\NestedPDO($app['mysql.connection'], $app['config']['mysql.username'], $app['config']['mysql.password']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo;
        };
        $app['mysql.db'] = $app->share(function($app) {
            $pdo = new \Utipd\MysqlModel\NestedPDO($app['mysql.connection'].';dbname='.$app['mysql.databaseName'], $app['config']['mysql.username'], $app['config']['mysql.password']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo;
        });

        // not shared
        $app['mysql.newDb'] = function($app) {
            $pdo = new \Utipd\MysqlModel\NestedPDO($app['mysql.connection'].';dbname='.$app['mysql.databaseName'], $app['config']['mysql.username'], $app['config']['mysql.password']);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo;
        };

        $app['mysql.transactionHandler'] = function($app) {
            return new \Utipd\MysqlModel\MysqlTransaction($app['mysql.db']);
        };
    }

    ////////////////////////////////////////////////////////////////////////

    public static function initModelFactories($app) {
        $app['directory'] = function($app) {
            return function($directory_name) use ($app) {
                $class = "LTBAuctioneer\\Models\\Directory\\{$directory_name}Directory";
                return new $class($app['mysql.db']);
            };
        };
        $app['modelFactory'] = function($app) {
            return function($model_name, $create_vars=[]) use ($app) {
                $class = "\\LTBAuctioneer\\Models\\Model\\{$model_name}Model";
                return new $class($app['directory']($model_name), $create_vars);
            };
        };
    }

    ////////////////////////////////////////////////////////////////////////


}

