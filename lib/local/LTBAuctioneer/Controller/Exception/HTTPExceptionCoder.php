<?php

namespace LTBAuctioneer\Controller\Exception;

use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* HTTPExceptionCoder
*/
interface HTTPExceptionCoder
{

    ////////////////////////////////////////////////////////////////////////

    // public function __construct() {
    // }

    public function setHTTPErrorCode($http_error_code);
    public function getHTTPErrorCode();

    ////////////////////////////////////////////////////////////////////////

}

