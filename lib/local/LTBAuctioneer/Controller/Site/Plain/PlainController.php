<?php

namespace LTBAuctioneer\Controller\Site\Plain;

use Exception;
use LTBAuctioneer\Controller\Site\Base\BaseSiteController;
use LTBAuctioneer\Debug\Debug;

/*
* PlainController
*/
class PlainController extends BaseSiteController
{

    ////////////////////////////////////////////////////////////////////////

    public function renderPlainTemplate($template, $twig_vars=[]) {
        return $this->renderTwig($template, $twig_vars);
    }

    ////////////////////////////////////////////////////////////////////////

}

