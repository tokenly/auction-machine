<?php

namespace LTBAuctioneer\Auctioneer;

use ArrayObject;
use Exception;
use LTBAuctioneer\Auctioneer\Account\Account;
use LTBAuctioneer\Auctioneer\Payout\AuctionPayout;
use LTBAuctioneer\Debug\Debug;

/*
* AuctionState
*/
class AuctionState extends ArrayObject
{

    protected $type;
    protected $block_id;

    public static function initialState() {
        return [
            'btcFeeSatisfied'      => false,
            'btcFeeApplied'        => 0,
            'bidTokenFeeSatisfied' => false,
            'bidTokenFeeApplied'   => 0,
            'prizeTokensSatisfied' => false,
            'prizeTokensApplied'   => [],
            'active'               => false,
            'timePhase'            => 'prebid', // prebid, live, ended

            'bounty'               => 0,
            'blockId'              => 0,

            'accounts'             => [],
            'bids'                 => [],

            'payouts'              => [],
        ];

    }

    public static function serializedInitialState() {
        $vars = self::initialState();
        $vars['bidsAndAccounts'] = [];
        return $vars;
    }

    ////////////////////////////////////////////////////////////////////////

    public function __construct($data=[]) {
        $data = array_replace_recursive(self::initialState(), $data);
        parent::__construct($data);
    }

    ////////////////////////////////////////////////////////////////////////

    // this is the most recent block id
    public function getBlockId() {
        return $this->block_id;
    }

    // most recent block id
    public function setBlockId($block_id) {
        $this->block_id = $block_id;
        return $this;
    }

    public function ensureAccountAt($address, $order_id) {
        if (!isset($this['accounts'][$address])) {
            $this['accounts'][$address] = new Account($address);
            $this['accounts'][$address]['order'] = $order_id;
        }

        return $this->getAccount($address);
    }

    public function getAccount($address) {
        if (!isset($this['accounts'][$address])) {
            $this['accounts'][$address] = new Account($address);
        }

        return $this['accounts'][$address];
    }

    public function getAccountAddresses() {
        return array_keys($this['accounts']);
    }

    public function getTopBid() {
        if (!isset($this['bids']) OR !$this['bids']) { return null; }
        return $this['bids'][0];
    }

    public function hasEarlyBids() {
        foreach ($this['bids'] as $bid) {
            if ($bid['status'] == 'prebid') { return true; }
        }
        return false;
    }

    public function serialize() {
        $out = (array)$this;
        $accounts = [];
        foreach ($this['accounts'] as $account) {
            $accounts[$account['address']] = (array)$account;
            unset($accounts[$account['address']]['order']);
        }
        $out['accounts'] = $accounts;
        $out['bidsAndAccounts'] = $this->bidsWithAccountData();
        $out['blockId'] = $this->block_id;

        $out['payouts'] = [];
        foreach ($this['payouts'] as $address => $payouts_by_token) {
            foreach($payouts_by_token as $token => $payout) {
                $out['payouts'][] = (array)$payout;
            }
        }

        return $out;
    }

    public function addLog($log_entry) {
       // Debug::trace("$log_entry",__FILE__,__LINE__,$this);
        if (!isset($this['logs'])) { $this['logs'] = []; }
        $this['logs'][] = $log_entry;
        return $this;
    }

    public function getPrizeTokenAppliedAmount($token) {
        return isset($this['prizeTokensApplied'][$token]) ? $this['prizeTokensApplied'][$token] : 0;
    }

    public function setPrizeTokenAppliedAmount($token, $amount) {
        return $this['prizeTokensApplied'][$token] = $amount;
    }

    public function prizeTokensAreSatisfied($required_prize_tokens_info) {
        foreach($required_prize_tokens_info as $required_info) {
            if ($this->getPrizeTokenAppliedAmount($required_info['token']) < $required_info['amount']) {
                return false;
            }
        }
        return true;
    }

    public function bidsWithAccountData() {
#        Debug::trace("bidsWithAccountData starting with this['bids']",$this['bids'],__FILE__,__LINE__,$this);
        $bids = [];

        foreach ($this['bids'] as $bid) {
            $account = $this->getAccount($bid['address']);
            $bid['account'] = [
                'prebid' => $account->getBalance($bid['token'], 'prebid'),
                'live'   => $account->getBalance($bid['token'], 'live'),
                'late'   => $account->getBalance($bid['token'], 'late'),
            ];
            $bids[] = $bid;
        }

#        Debug::trace("bidsWithAccountData=",$bids,__FILE__,__LINE__,$this);
        return $bids;
    }

    public function resetAllPayouts() {
        $this['payouts'] = [];
    }

    public function getAllPayouts() {
        $all_payouts = [];
        foreach ($this['payouts'] as $address => $payouts) {
            foreach($payouts as $token => $payout) {
                $all_payouts[] = $payout;
            }
        }
        return $all_payouts;
    }

    public function getPayoutsByAddress($address) {
        if (isset($this['payouts'][$address])) { return $this['payouts'][$address]; }
        return [];
    }

    public function findOrCreatePayout($address, $token) {
        if (!isset($this['payouts'][$address])) { $this['payouts'][$address] = []; }
        if (!isset($this['payouts'][$address][$token])) { $this['payouts'][$address][$token] = new AuctionPayout($address, $token); }

        return $this['payouts'][$address][$token];
    }

    ////////////////////////////////////////////////////////////////////////


}

