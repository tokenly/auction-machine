<?php

namespace LTBAuctioneer\Init\DependencyInjections;

use Exception;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Controller\ControllerResolver;
use Symfony\Component\HttpFoundation\Response;

/*
* ApplicationInit
*/
class ApplicationInit {

    public static function init($app) {
        self::initApplication($app);
    }

    public static function initApplication($app) {
        // config
        $app['config'] = $app->share(function($app) {
            $debug = $app['env'] == 'prod' ? false : true;
            $loader = new \Utipd\Config\ConfigLoader(BASE_PATH.'/etc/app-config', BASE_PATH.'/var/cache/app-config', $debug);
            return $loader->loadYamlFile($app['env'].'.yml');
        });


        // debug setting
        $app['debug'] = function($app) { return $app['config']['app.debug']; };


        // monolog
        $app->register(new \Silex\Provider\MonologServiceProvider(), [
            'monolog.logfile' => BASE_PATH.'/var/log/trace.log',
            'monolog.name'    => 'ltba',
            'monolog.level'   => \Monolog\Logger::DEBUG,
        ]);
        $app['monolog.handler'] = function () use ($app) {
            $level = \Silex\Provider\MonologServiceProvider::translateLevel($app['monolog.level']);
            $stream = new \Monolog\Handler\StreamHandler($app['monolog.logfile'], $level);
            $stream->setFormatter(new \Monolog\Formatter\LineFormatter("[%datetime%] %channel%.%level_name% %message%\n", "Y-m-d H:i:s", true));
            return $stream;
        };

        // routing
        $app['router.site'] = function($app) {
            return new \LTBAuctioneer\Router\SiteRouter($app);
        };
        $app['router.admin'] = function($app) {
            return new \LTBAuctioneer\Router\AdminRouter($app);
        };

        // twig
        $app->register(new \Silex\Provider\TwigServiceProvider(), array(
            'twig.path' => BASE_PATH.'/twig',
        ));
        $app['twig.path'] = BASE_PATH.'/twig/html';
        $app["twig"] = $app->share($app->extend("twig", function (\Twig_Environment $twig, \Silex\Application $app) {
            return CurrencyUtil::addTwigFilters($twig);
        }));

        $app->register(new \Silex\Provider\UrlGeneratorServiceProvider());

    }






}

