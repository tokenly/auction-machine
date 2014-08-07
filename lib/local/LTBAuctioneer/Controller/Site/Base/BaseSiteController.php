<?php

namespace LTBAuctioneer\Controller\Site\Base;

use Exception;
use LTBAuctioneer\Controller\Base\BaseController;
use LTBAuctioneer\Debug\Debug;

/*
* BaseSiteController
*/
class BaseSiteController extends BaseController
{

    ////////////////////////////////////////////////////////////////////////


    public function renderTwig($template, $twig_vars=[]) {
        return $this->app['twig']->render($template, array_merge($this->globalTwigVars(), $twig_vars));
    }

    ////////////////////////////////////////////////////////////////////////

    protected function globalTwigVars() {
        $config = $this->app['config'];
        return [
            'env'  => $config['env'],
        ];
    }
}

