<?php

namespace LTBAuctioneer\Authentication\API\Mock;


use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use LTBAuctioneer\Authentication\API\APIAuthenticationHandler;
use LTBAuctioneer\Debug\Debug;
use Utipd\HmacAuth\Exception\AuthorizationException;
use Utipd\HmacAuth\Validator;

/*
* MockAPIAuthenticationHandler
*/
class MockAPIAuthenticationHandler extends APIAuthenticationHandler
{

    ////////////////////////////////////////////////////////////////////////



    public function requirePlatformAuthentication(Request $request) {
        if ($this->isMockAPIToken($request)) {
            // always valid
            return null;
        }

        return parent::requirePlatformAuthentication($request);
    }

    ////////////////////////////////////////////////////////////////////////


    protected function isMockAPIToken(Request $request) {
        return ('test-env-api-token' === $request->headers->get('X-Utipd-Auth-Api-Token'));
    }

}

