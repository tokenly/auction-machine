<?php

namespace LTBAuctioneer\Util\Twig;

use Exception;
use LTBAuctioneer\Debug\Debug;
use Twig_Loader_String;
use Twig_Environment;

/*
* TwigUtil
*/
class TwigUtil
{

    ////////////////////////////////////////////////////////////////////////

    public static function renderTwigText($text, $twig_vars=[]) {
        $twig = new Twig_Environment(new Twig_Loader_String());
        return $twig->render($text, $twig_vars);
    }

    ////////////////////////////////////////////////////////////////////////

}

