<?php

namespace LTBAuctioneer\Auctioneer\Account;

use Exception;
use ArrayObject;

/*
* Account
*/
class Account extends ArrayObject
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($address, $data=[]) {
        $data = array_replace_recursive($this->defaults(), ['address' => $address], $data);

        parent::__construct($data);
    }

    public function addBalance($amount, $token, $balance_type='live') {
        $balance = $this->getBalance($token, $balance_type);
        $balance = $balance + $amount;
        return $this->setBalance($balance, $token, $balance_type);
    }

    public function subtractBalance($amount, $token, $balance_type='live') {
        $balance = $this->getBalance($token, $balance_type);
        $balance = $balance - $amount;
        if ($balance < 0) { throw new Exception("Negative balance not allowed", 1); }
        return $this->setBalance($balance, $token, $balance_type);
    }

    public function getBalance($token, $balance_type='live') {
        if (!isset($this['balances'][$token])) { $this['balances'][$token] = []; }

        if (isset($this['balances'][$token][$balance_type])) {
            return $this['balances'][$token][$balance_type];
        }

        return 0;
    }

    public function setBalance($balance, $token, $balance_type='live') {
        if (!isset($this['balances'][$token])) { $this['balances'][$token] = []; }

        $this['balances'][$token][$balance_type] = $balance;
        return $this['balances'][$token][$balance_type];
    }

    public function getDefaultSortOrder() {
        if (isset($this['order'])) { return $this['order']; }
        return 0;
    } 

    ////////////////////////////////////////////////////////////////////////


    protected function defaults() {
        return [
            'address'  => '',
            'balances' => [],
        ];
    }



}

