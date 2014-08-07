<?php

// use LTBAuctioneer\Init\Environment;


error_reporting(E_ALL | E_STRICT);

define('BASE_PATH', dirname(__DIR__));
define('TEST_PATH', __DIR__);

require BASE_PATH.'/lib/vendor/autoload.php';
// $GLOBAL['silex_app'] = Environment::initApp('test');

