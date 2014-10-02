<?php

namespace LTBAuctioneer\Auctioneer\Payout;

use ArrayObject;
use Exception;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;

/*
* AuctionPayout
*/
class AuctionPayout extends ArrayObject
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($address, $token, $data=[]) {
        $data = array_replace_recursive($this->defaults(), ['address' => $address, 'token' => $token,], $data);

        parent::__construct($data);
    }


    public function __toString() {
        // return ($this['authorized'] ? "authorized" : "unauthorized")." payment of ".CurrencyUtil::satoshisToNumber($this['amount'])." ".$this['token']." to ".$this['address'];
        return "payment of ".CurrencyUtil::satoshisToNumber($this['amount'])." ".$this['token']." to ".$this['address'];
    }

    ////////////////////////////////////////////////////////////////////////

    protected function defaults() {
        return [
            'authorized' => false,
            'sweep'      => false,
            'address'    => '',
            'token'      => '',
            'amount'     => 0,
        ];
    }


}

