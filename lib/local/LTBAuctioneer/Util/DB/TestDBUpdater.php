<?php

namespace LTBAuctioneer\Util\DB;

use Exception;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Util\Twig\TwigUtil;

/*
* TestDBUpdater
*/
class TestDBUpdater
{

    ////////////////////////////////////////////////////////////////////////

    public static function prepCleanDatabase($app) {
        $dropped = self::dropDatabaseIfNeeded($app);
        if (!$dropped) {
            TestDBUpdater::clearMySQLDBs($app);
        }

        DBUpdater::bringDatabaseUpToDate($app);
    }

    public static function clearMySQLDBs($app) {
        $result = $app['mysql.db']->query("SHOW TABLES");
        while ($row = $result->fetch(\PDO::FETCH_NUM)) {
            $table = $row[0];
            if ($table == 'schema_version') { continue; }

            $app['mysql.db']->exec('TRUNCATE TABLE `'.$table.'`');
            $app['mysql.db']->exec('ALTER TABLE `'.$table.'` AUTO_INCREMENT=101');
        }
    }

    public static function dropDatabaseIfNeeded($app) {
        $dropped = false;

        $db_schema_version = null;
        try {
            $result = $app['mysql.db']->query("SELECT * FROM schema_version WHERE 1");
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            if ($row) { $db_schema_version = $row['version']; }
        } catch (Exception $e) {
            Debug::errorTrace("ERROR: ".$e->getMessage(),__FILE__,__LINE__);            
        }

        // get the file on disk md5
        $disk_version = md5_file(BASE_PATH.'/etc/sql/tables.mysql');

        if ($disk_version != $db_schema_version) {
#            Debug::trace("\$disk_version=$disk_version \$db_schema_version=$db_schema_version",__FILE__,__LINE__);
            $dropped = true;
            self::dropDatabase($app);

            DBUpdater::bringDatabaseUpToDate($app);

            $app['mysql.db']->prepare("INSERT INTO schema_version VALUES (?, ?)")->execute([1, $disk_version]);
        }

        return $dropped;
    }

    public static function dropDatabase($app) {
        $app['mysql.client']->exec("DROP DATABASE IF EXISTS `{$app['mysql.databaseName']}`");
    }

    ////////////////////////////////////////////////////////////////////////

}

