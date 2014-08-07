<?php

namespace LTBAuctioneer\Models\Model;

use Utipd\MysqlModel\BaseDocumentMysqlModel;
use Exception;

/*
* AuctionModel
*/
class AuctionModel extends BaseDocumentMysqlModel
{

    public function findPrizeTokenRequiredInfo($token_name) {
        if ($this['prizeTokensRequired']) {
            foreach ($this['prizeTokensRequired'] as $info) {
                if ($info['token'] == $token_name) { return $info; }
            }
        }
        return null;
    }

    public function isPaidOut() {
        return isset($this['paidOut']) ? !!$this['paidOut'] : false;
    }

}
