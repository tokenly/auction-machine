<?php

namespace LTBAuctioneer\Router;

use Exception;
use LTBAuctioneer\Controller\Exception\WebsiteException;
use LTBAuctioneer\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;

/*
* SiteRouter
*/
class SiteRouter
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($app) {
        $this->app = $app;
    }

    public function route() {
        // home
        $this->app->match('/', function(Request $request) {
            // return $this->app['twig']->render('home/home.twig', ['error' => $error]);
            return $this->app['controller.auction.public']->homeAction($request, $slug);
        })->method('GET|POST')->bind('home');

        // Auction Admin
        $this->app->match('/create/auction/new', function(Request $request) {
            return $this->app['controller.auction.admin']->newAuctionAction($request);
        })->method('GET|POST')->bind('create-auction');

        $this->app->match('/create/auction/{auctionRefId}', function(Request $request, $auctionRefId) {
            return $this->app['controller.auction.admin']->confirmAuctionAction($request, $auctionRefId);
        })->method('GET|POST')->bind('create-auction-confirm');


        $this->app->match('/auction/{slug}', function(Request $request, $slug) {
            return $this->app['controller.auction.public']->viewAuctionAction($request, $slug);
        })->method('GET|POST')->bind('public-auction');



        // default error handler
        $this->app->error(function (Exception $e, $code) {
            // use debug mode
            if ($this->app['debug']) { return; }

            $error = null;
            if ($e instanceof WebsiteException) {
                $error = $e->getDisplayErrorsAsHTML();
            }

            return $this->app['twig']->render('error/error.twig', ['error' => $error]);
        });

    }

    ////////////////////////////////////////////////////////////////////////

}

