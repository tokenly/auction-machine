<?php

namespace LTBAuctioneer\Controller\Base;

use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* BaseController
*/
class BaseController
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct(\Silex\Application $app = null) {
        if ($app !== null) { $this->app = $app; }
    }

    ////////////////////////////////////////////////////////////////////////

}

