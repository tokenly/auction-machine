<?php

namespace LTBAuctioneer\Models\Directory;

use Utipd\MysqlModel\BaseDocumentMysqlDirectory;
use Exception;

/*
* BlockchainTransactionDirectory
*/
class BlockchainTransactionDirectory extends BaseDocumentMysqlDirectory
{

    protected $column_names = ['auctionId','blockId','transactionId'];

    public function findByAuctionId($auction_id, $sort=null) {
        if ($sort === null) { $sort = ['blockId' => 1, 'id' => 1]; }
        return $this->find(['auctionId' => $auction_id], $sort);
    }

}
