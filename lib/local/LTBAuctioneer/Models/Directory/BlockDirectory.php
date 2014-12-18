<?php

namespace LTBAuctioneer\Models\Directory;

use Utipd\MysqlModel\BaseDocumentMysqlDirectory;
use Exception;

/*
* BlockDirectory
*/
class BlockDirectory extends BaseDocumentMysqlDirectory
{

    protected $column_names = ['blockId','blockHash','blockDate',];

    public function getBestHeightBlock() {
        $block_model = $this->findOne([], ['blockId' => -1, 'id' => -1]);
        return $block_model;
    }
}
