<?php

namespace LTBAuctioneer\Models\Directory;

use Utipd\MysqlModel\BaseDocumentMysqlDirectory;
use Exception;

/*
* BlockchainTransactionDirectory
*/
class BlockchainTransactionDirectory extends BaseDocumentMysqlDirectory
{

    protected $column_names = ['auctionId','blockId','tx_hash','isMempool','isNative',];

    public function findByAuctionId($auction_id, $sort=null) {
        if ($sort === null) { $sort = ['isMempool' => 1, 'blockId' => 1, 'id' => 1]; }
        return $this->find(['auctionId' => $auction_id], $sort);
    }

}
