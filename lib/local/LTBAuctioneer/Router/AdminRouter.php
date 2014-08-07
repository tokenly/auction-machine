<?php

namespace LTBAuctioneer\Router;

use Exception;
use LTBAuctioneer\Controller\Exception\WebsiteException;
use LTBAuctioneer\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*
* AdminRouter
*/
class AdminRouter
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($app) {
        $this->app = $app;
    }


    public function route() {

        $http_auth = function() {
            if (!isset($_SERVER['PHP_AUTH_USER']))
            {
                // return $app->json(array('Message' => 'Not Authorized'), 401);
                return new Response('Not Authorized', 401, array('WWW-Authenticate' => 'Basic realm="LTB Auctioneer"'));
            }
            else
            {
                //once the user has provided some details, check them
                $users = array(
                    'ltbadmin' => 'drAg6E8knoaR',
                );

                if($users[$_SERVER['PHP_AUTH_USER']] !== $_SERVER['PHP_AUTH_PW']) {
                    return new Response('Not Authorized', 401, array('WWW-Authenticate' => 'Basic realm="LTB Auctioneer"'));
                }

            }
        };

        $this->app->match('/admin/logs', function(Request $request) use($app) {
            return $this->app['controller.admin']->logsAction($request);
        })->method('GET|POST')->before($http_auth);

        $this->app->match('/admin/auctions', function(Request $request) use($app) {
            return $this->app['controller.admin']->auctionsAction($request);
        })->method('GET|POST')->before($http_auth);

    }

    ////////////////////////////////////////////////////////////////////////



}

