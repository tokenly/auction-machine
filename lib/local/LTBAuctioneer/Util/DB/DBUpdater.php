<?php

namespace LTBAuctioneer\Util\DB;

use Exception;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Util\Twig\TwigUtil;

/*
* DBUpdater
*/
class DBUpdater
{

    ////////////////////////////////////////////////////////////////////////


    public static function bringDatabaseUpToDate($app) {
        // update SQL tables
        $dbh = $app['mysql.client'];
        $sql = TwigUtil::renderTwigText(file_get_contents(BASE_PATH.'/etc/sql/tables.mysql'), ['app' => $app]);
        $result = $dbh->exec($sql);

        // also update the counterparty follower tables
        $app['xcpd.followerSetup']->InitializeDatabase();
        $app['native.followerSetup']->InitializeDatabase();

        // create system users
        // $app['user.manager']->createMissingSystemUsers();
    }



    ////////////////////////////////////////////////////////////////////////

}

