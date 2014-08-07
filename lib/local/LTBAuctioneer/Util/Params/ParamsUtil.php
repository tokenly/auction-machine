<?php

namespace LTBAuctioneer\Util\Params;

use Exception;
use LTBAuctioneer\Debug\Debug;
use Symfony\Component\Yaml\Yaml;

/*
* ParamsUtil
*/
class ParamsUtil
{

    ////////////////////////////////////////////////////////////////////////

    public static function interpretJSONOrYaml($raw_in) {
        $data = @json_decode($raw_in, true);
        if ($data !== null) { return $data; }

        $data = Yaml::parse($raw_in);
        if ($data !== null) { return $data; }

        return null;
    }

    ////////////////////////////////////////////////////////////////////////

}

