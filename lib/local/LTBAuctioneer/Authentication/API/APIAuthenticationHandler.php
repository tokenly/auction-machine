<?php

namespace LTBAuctioneer\Authentication\API;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use LTBAuctioneer\Debug\Debug;
use Utipd\HmacAuth\Exception\AuthorizationException;
use Utipd\HmacAuth\Validator;

/*
* APIAuthenticationHandler
*/
class APIAuthenticationHandler
{

    ////////////////////////////////////////////////////////////////////////


    public function __construct($platform_application_directory, $delegated_platform_authorization_directory) {
        $this->platform_application_directory = $platform_application_directory;
        $this->delegated_platform_authorization_directory = $delegated_platform_authorization_directory;
    }

    public function requirePlatformAuthentication(Request $request) {
        return $this->requireAuthentication($request, 'getPlatformByAPIToken');
    }

    public function requireDelegatedPlatformAuthentication(Request $request) {
        return $this->requireAuthentication($request, 'getPlatformByDelegatedAPIToken');
    }

    ////////////////////////////////////////////////////////////////////////

    protected function requireAuthentication(Request $request, $platform_lookup_method) {
        try {
            $api_token = $this->requireAPITokenFromRequest($request);
            $platform = $this->{$platform_lookup_method}($api_token, $request);
#            Debug::trace("\$platform=".Debug::desc((array)$platform)."",__FILE__,__LINE__,$this);
            $is_valid = $this->buildValidator($platform)->validateFromRequest($request);

        } catch (AuthorizationException $e) {
            Debug::errorTrace("ERROR: ".$e->getMessage(),__FILE__,__LINE__,$this);
            $is_valid = false;
        }

        if ($is_valid) {
            // attach the platform id to the request for later use
            $request->headers->set('X-Utipd-Authorized-Platform-Id', $platform['id']);

            // null means the application continues
            return null;
        }

        // authorization failed, so clear the platform id header
        $request->headers->set('X-Utipd-Authorized-Platform-Id', false);

        // validation failed
        return new Response("Authentication Failed", 403);
    }

    protected function buildValidator($platform) {
        return new Validator(function($api_token) use ($platform) {
            return $platform['apiSecret'];
        });
    }

    protected function getPlatformByAPIToken($api_token) {
        return $this->platform_application_directory->findByAPIToken($api_token);
    }

    protected function getPlatformByDelegatedAPIToken($api_token, Request $request) {
        $user_ref_id = $request->attributes->get('userId');
#        Debug::trace("\$user_ref_id=".Debug::desc($user_ref_id)."",__FILE__,__LINE__,$this);
        if (!$user_ref_id) { throw new AuthorizationException("No user id specified."); }

        $authorization = $this->delegated_platform_authorization_directory->findByAPIToken($api_token);
#        Debug::trace("\$api_token=".Debug::desc($api_token)." \$authorization=".Debug::desc((array)$authorization)."",__FILE__,__LINE__,$this);
        if ($authorization) {
            // make sure the user_ref_id matches the authorization's user_refId
            if ($authorization['user_refId'] != $user_ref_id) {
                // this is an attempt of one user to modify something by using another user's apiToken
                throw new AuthorizationException("This action is not authorized.", "Authorization failure: mismatched user ids");
            }

            return $this->platform_application_directory->findByID($authorization['platformApplication_id']);
        }

        throw new AuthorizationException("This action is not authorized.", "Authorization failure: authorization not found");
    }

    protected function requireAPITokenFromRequest(Request $request) {
        $api_token = $request->headers->get('X-Utipd-Auth-Api-Token');
        if (!$api_token) { throw new AuthorizationException("Missing api_token"); }
        return $api_token;
    }

}

