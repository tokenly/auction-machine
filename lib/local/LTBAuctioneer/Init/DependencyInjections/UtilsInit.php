<?php

namespace LTBAuctioneer\Init\DependencyInjections;


use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* UtilsInit
*/
class UtilsInit {

    public static function init($app) {
        self::initUtils($app);
    }

    public static function initUtils($app) {

        $app['token.generator'] = function($app) {
            return new \LTBAuctioneer\Authentication\Token\TokenGenerator();
        };
        $app['slugger'] = function($app) {
            return new \LTBAuctioneer\Util\Slug\Slugger();
        };

    }






}

