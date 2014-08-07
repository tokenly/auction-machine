<?php

namespace LTBAuctioneer\Controller\Util;

use Exception;
use LTBAuctioneer\Debug\Debug;
use Silex\Application;

/*
* JsonUtil
*/
class JsonUtil
{

    public static function jsonErrorResponse(Application $app, $error, $error_code=null) {
        if ($error instanceof Exception) {
            $error_text = $error->getMessage();
            if ($error_code === null) { $error_code = $error->getCode(); }
        } else {
            $error_text = $error;
        }

        $data = [];
        $data['success'] = false;
        $data['error'] = $error_text;
        if ($error_code !== null) { $data['errorCode'] = $error_code; }

        $http_status_code = 500;
        if ($error_code !== null AND $error_code >= 400 AND $error_code < 600) { $http_status_code = $error_code; }

        return $app->json($data, $http_status_code);
    }

    public static function jsonSuccessResponse(Application $app, $data) {

        return $app->json($data);

        // $data_out = [];
        // $data_out['success'] = true;
        // $data_out['data'] = $data;
        // return $app->json($data_out);
    }


}
