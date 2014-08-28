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
            return $this->app['controller.auction.public']->homeAction($request, $slug);
        })->method('GET')->bind('home');

        // about
        $this->app->match('/about', function(Request $request) {
            return $this->app['controller.plain']->renderPlainTemplate('about/about.twig');
        })->method('GET')->bind('about');


        $this->app->match('/auctions.json', function(Request $request) {
            return $this->app['controller.auction.public']->auctionsData($request);
        })->method('GET')->bind('auctions-data');

        // Auction Admin
        $this->app->match('/create/auction/new', function(Request $request) {
            return $this->app['controller.auction.admin']->newAuctionAction($request);
        })->method('GET|POST')->bind('create-auction');

        $this->app->match('/create/auction/{auctionRefId}', function(Request $request, $auctionRefId) {
            return $this->app['controller.auction.admin']->confirmAuctionAction($request, $auctionRefId);
        })->method('GET')->bind('create-auction-confirm');


        $this->app->match('/auction/{slug}', function(Request $request, $slug) {
            return $this->app['controller.auction.public']->viewAuctionAction($request, $slug);
        })->method('GET')->bind('public-auction');

        $this->app->match('/faq', function(Request $request) {
            return $this->app['twig']->render('faq/faq.twig', []);
        })->method('GET')->bind('faq');



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

