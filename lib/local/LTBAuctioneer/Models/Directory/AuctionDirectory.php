<?php

namespace LTBAuctioneer\Models\Directory;

use Exception;
use Utipd\MysqlModel\BaseDocumentMysqlDirectory;

/*
* AuctionDirectory
*/
class AuctionDirectory extends BaseDocumentMysqlDirectory
{
    protected $column_names = ['refId','slug','startDate','endDate','timePhase','paidOut',];


    public function findByRefID($ref_id) {
        return $this->findOne(['refId' => $ref_id]);
    }
    public function findBySlug($slug) {
        return $this->findOne(['slug' => $slug]);
    }
}
