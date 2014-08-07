<?php

namespace LTBAuctioneer\Test\Util;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use LTBAuctioneer\Debug\Debug;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* RequestUtil
*/
class RequestUtil
{

    ////////////////////////////////////////////////////////////////////////


    public static function assertValidResponse($app, $method, $relative_path, $params=[]) {
        $response = self::getResponse($app, $method, $relative_path, $params);

        PHPUnit::assertEquals(200, $response->getStatusCode(), "Invalid response for $method $relative_path\n".$response);

        return $response->getContent();
    }

    public static function assertResponseWithStatusCode($app, $method, $relative_path, $params=[], $expected_http_code=500, $expected_content=null) {
        $response = self::getResponse($app, $method, $relative_path, $params);

        PHPUnit::assertEquals($expected_http_code, $response->getStatusCode(), "Invalid response for $method $relative_path\n".$response->headers."\n".$response->getContent());
        if ($expected_content !== null) {
            PHPUnit::assertContains($expected_content, $response->getContent());
        }

        return $response;
    }

    public static function getResponse($app, $method, $relative_path, $params) {
        $request = Request::create("http://localhost".$relative_path, $method, $params, $cookies = [], $files = [], $server = [], $content = null);

        // route
        $app['router.site']->route();

        // handle
        $response = $app->handle($request);

        // terminate
        $app->terminate($request, $response);
        return $response;
    }




    ////////////////////////////////////////////////////////////////////////

}

