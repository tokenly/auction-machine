<?php

namespace LTBAuctioneer\Checker;

use LTBAuctioneer\Debug\Debug;
use Exception;

/*
* MonitorDaemon
*/
class MonitorDaemon
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($xcpd_follower, $native_follower, $simple_daemon_factory) {
        $this->xcpd_follower           = $xcpd_follower;
        $this->native_follower         = $native_follower;
        $this->simple_daemon_factory   = $simple_daemon_factory;
    }


    ////////////////////////////////////////////////////////////////////////

}

