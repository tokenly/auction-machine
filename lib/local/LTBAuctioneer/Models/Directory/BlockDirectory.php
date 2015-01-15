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

    public function getBlockModelAtBestHeight() {
        $block_model = $this->findOne([], ['blockId' => -1, 'id' => -1]);
        return $block_model;
    }
    public function getBlockModelByBlockHash($block_hash) {
        $block_model = $this->findOne(['blockHash' => $block_hash]);
        return $block_model;
    }
    public function getBestBlockHeight() {
        $block_model = $this->getBlockModelAtBestHeight();
        if (!$block_model) { return null; }
        return $block_model['blockId'];
    }
}
