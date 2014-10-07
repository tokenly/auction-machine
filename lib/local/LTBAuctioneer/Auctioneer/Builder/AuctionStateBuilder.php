<?php

namespace LTBAuctioneer\Auctioneer\Builder;

use Exception;
use LTBAuctioneer\Auctioneer\AuctionState;
use LTBAuctioneer\Auctioneer\Builder\Auctioneer;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;

/*
* AuctionStateBuilder
* Builds the auction state from a series of transactions in order
*/
class AuctionStateBuilder
{

    protected $meta_info = [];


    ////////////////////////////////////////////////////////////////////////

    public function __construct() {
    }


    // we assume transactions were provided in order
    public function buildAuctionStateFromTransactions($transactions, $auction, $meta_info) {
        // clear state
        $this->auction_state = null;
        $this->auctioneer = null;
        
        $this->setAuction($auction);
        $this->setMetaInfo($meta_info);

        foreach($transactions as $transaction) {
            $this->applyTransaction($transaction);
        }

        return $this->serializeState($this->finalizeState($this->getState()));
    }

    ////////////////////////////////////////////////////////////////////////

    protected function setMetaInfo($meta_info) {
        $this->meta_info = $meta_info;
    }

    protected function now() {
        if (isset($this->meta_info['now'])) { return $this->meta_info['now']; }
        return time();
    }

    protected function hasStarted($timestamp) {
        return ($timestamp >= $this->auction['startDate']);
    }

    protected function hasEnded($timestamp) {
        return ($timestamp >= $this->auction['endDate']);
    }

    protected function buildAuctionTimePhase($timestamp) {
#        Debug::trace("hasEnded() == ".Debug::desc($this->hasEnded())."",__FILE__,__LINE__,$this);
        if (!$this->hasStarted($timestamp)) { return 'prebid'; }
        if ($this->hasEnded($timestamp)) { return 'ended'; }
        return 'live';
    }

    protected function setAuction($auction) {
        $this->auction = $auction;
    }


    ////////////////////////////////////////////////////////////////////////

    protected function applyTransaction($transaction) {
        if ($transaction['classification'] == 'incoming') { return $this->applyIncomingTransactionToState($transaction); }
        if ($transaction['classification'] == 'outgoing') { return $this->applyOutgoingTransactionToState($transaction); }
        throw new Exception("Invalid transaction classification: ".Debug::desc($transaction['classification'])."", 1);
    }

    protected function applyIncomingTransactionToState($transaction) {
#        Debug::trace("\$transaction: ".$transaction['blockId'],__FILE__,__LINE__,$this);
        switch (true) {
            case $this->isBTCTransaction($transaction):
                # incoming BTC transaction
                $this->handleIncomingBTC($transaction);
                break;

            case $this->hasBiddingAsset($transaction):
                # incoming bidding asset transaction
                $this->handleIncomingBiddingAsset($transaction);
                break;
            
            default:
                # other, probably a prize token
                $this->handleUknownIncomingAsset($transaction);
                break;
        }

    }

    ////////////////////////////////////////////////////////////////////////
    // Bidding Asset

    protected function handleIncomingBiddingAsset($transaction) {
#       Debug::trace("handleIncomingBiddingAsset $transaction=".json_encode($transaction, 192),__FILE__,__LINE__,$this);
        $state = $this->getStateAtBlockAndTime($transaction['blockId'], $transaction['timestamp'], $transaction['isMempool']);
        $token_amount = $transaction['quantity'];

        $state->addLog("Received ".CurrencyUtil::satoshisToNumber($token_amount)." {$transaction['asset']}");

        if (!$state['bidTokenFeeSatisfied']) {
#            Debug::trace("applyToBidTokenFee",__FILE__,__LINE__,$this);
            $token_amount = $this->applyToBidTokenFee($token_amount, $state);
#            Debug::trace("after applyToBidTokenFee token_amount: $token_amount",__FILE__,__LINE__,$this);
            $this->refreshActiveStatus($state);
        }

        // bug here - if token amount is lowered, the whole bid is applied

        if ($token_amount > 0) {
#            Debug::trace("applyBid with token amount: $token_amount",__FILE__,__LINE__,$this);
            $this->applyBid($transaction, $token_amount);
        }

    }

    protected function applyToBidTokenFee($token_amount, AuctionState $state) {
        $amount_left = $this->auction['bidTokenFeeRequired'] - $state['bidTokenFeeApplied'];
        $amount_applied = min($amount_left, $token_amount);

        if ($amount_applied > 0) {
            $state->addLog("Applied ".CurrencyUtil::satoshisToNumber($amount_applied)." {$this->auction['bidTokenType']} to requirement of ".CurrencyUtil::satoshisToNumber($this->auction['bidTokenFeeRequired']));

            $state['bidTokenFeeApplied'] = $state['bidTokenFeeApplied'] + $amount_applied;

            if ($state['bidTokenFeeApplied'] >= $this->auction['bidTokenFeeRequired']) {
                $state['bidTokenFeeSatisfied'] = true;
                $state->addLog("{$this->auction['bidTokenType']} fee was paid");
            } else {
                $state->addLog("{$this->auction['bidTokenType']} fee remaining: ".CurrencyUtil::satoshisToNumber($this->auction['bidTokenFeeRequired'] - $state['bidTokenFeeApplied']));
            }
        }


        return $token_amount - $amount_applied;
    }


    ////////////////////////////////////////////////////////////////////////
    // BTC

    protected function handleIncomingBTC($transaction) {
        $state = $this->getStateAtBlockAndTime($transaction['blockId'], $transaction['timestamp'], $transaction['isMempool']);
        $token_amount = $transaction['quantity'];

        $state->addLog("Received ".CurrencyUtil::satoshisToNumber($token_amount)." BTC");

        if (!$state['btcFeeSatisfied']) {
            $token_amount = $this->applyToBTCFee($token_amount, $state);
            $this->refreshActiveStatus($state);
        } else {
            $state->addLog("Did not apply BTC to fee, because it was already paid");
        }
    }

    protected function applyToBTCFee($token_amount, AuctionState $state) {
        $amount_left = $this->auction['btcFeeRequired'] - $state['btcFeeApplied'];
        $amount_applied = min($amount_left, $token_amount);
        $state['btcFeeApplied'] = $state['btcFeeApplied'] + $amount_applied;

        $state->addLog("Applied ".CurrencyUtil::satoshisToNumber($amount_applied)." to BTC fee requirement of ".CurrencyUtil::satoshisToNumber($this->auction['btcFeeRequired']));

        if ($state['btcFeeApplied'] >= $this->auction['btcFeeRequired']) {
            $state['btcFeeSatisfied'] = true;
            $state->addLog("BTC fee was paid");
        } else {
            $state->addLog("BTC fee remaining: ".CurrencyUtil::satoshisToNumber($this->auction['btcFeeRequired'] - $state['btcFeeApplied']));
        }

        return $token_amount - $amount_applied;
    }

    ////////////////////////////////////////////////////////////////////////
    // Prize Token

    protected function handleIncomingPrizeToken($transaction) {
        $state = $this->getStateAtBlockAndTime($transaction['blockId'], $transaction['timestamp'], $transaction['isMempool']);
        $token_amount = $transaction['quantity'];

        $state->addLog("Received ".CurrencyUtil::satoshisToNumber($token_amount)." {$transaction['asset']} prize token");

        // apply
        // Debug::trace("\$state['prizeTokensSatisfied']=".Debug::desc($state['prizeTokensSatisfied'])."",__FILE__,__LINE__,$this);
        if (!$state['prizeTokensSatisfied']) {
            // prizeTokensApplied
            $prize_token_info = $this->auction->findPrizeTokenRequiredInfo($transaction['asset']);
            // echo "\$prize_token_info:\n".json_encode($prize_token_info, 192)."\n";
            $token_amount = $this->applyPrizeToken($token_amount, $prize_token_info, $state);

            // refresh the auction is now active
            $this->refreshActiveStatus($state);
            
        }

        if ($token_amount) {
            // extra prize token... hmmm...
            $state->addLog("Received ".CurrencyUtil::satoshisToNumber($token_amount)." {$transaction['asset']} extra prize token that I don't know what to do with.");
        }

    }

    protected function applyPrizeToken($token_amount, $prize_token_info, AuctionState $state) {
        $token = $prize_token_info['token'];
        $amount_left = $prize_token_info['amount'] - $state->getPrizeTokenAppliedAmount($token);
        $amount_applied = min($amount_left, $token_amount);
#        Debug::trace("\$amount_left=".Debug::desc($amount_left)." \$amount_applied=".Debug::desc($amount_applied)."",__FILE__,__LINE__,$this);

        if ($amount_applied > 0) {
            $state->addLog("Applied ".CurrencyUtil::satoshisToNumber($amount_applied)." {$token} to requirement of ".CurrencyUtil::satoshisToNumber($prize_token_info['amount']));

            $state->setPrizeTokenAppliedAmount($token, $state->getPrizeTokenAppliedAmount($token) + $amount_applied);

            if ($state->getPrizeTokenAppliedAmount($token) >= $prize_token_info['amount']) {
                $state->addLog("Sufficient amount of {$token} was received");

                if ($state->prizeTokensAreSatisfied($this->auction['prizeTokensRequired'])) {
                    $state['prizeTokensSatisfied'] = true;
                    $state->addLog("All prize tokens received");
                } else {
                    $state->addLog("There are still prize tokens needed");
                }
            } else {
                $state->addLog("Still need to receive ".CurrencyUtil::satoshisToNumber($prize_token_info['amount'] - $state->getPrizeTokenAppliedAmount($token))." {$token}");
            }
        }


        return $token_amount - $amount_applied;
    }


    ////////////////////////////////////////////////////////////////////////
    // active status

    protected function refreshActiveStatus(AuctionState $state) {
        $previous_active_status = $state['active'];
        $active_status = ($state['bidTokenFeeSatisfied'] AND $state['btcFeeSatisfied'] AND $state['prizeTokensSatisfied']);
#        Debug::trace("\$active_status=".Debug::desc($active_status)."",__FILE__,__LINE__,$this);

        if ($previous_active_status != $active_status) {
            $state->addLog("State active flag updated to ".Debug::desc($active_status)."");
        }

        $state['active'] = $active_status;
    }
    

    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    // Bidding

    protected function applyBid($transaction, $token_amount) {
        $state = $this->getStateAtBlockAndTime($transaction['blockId'], $transaction['timestamp'], $transaction['isMempool']);

        // convert any prebids if we are ready before processing bids
        $state = $this->getAuctioneer()->convertPrebidsIfReady($state);

        $state->ensureAccountAt($transaction['source'], $transaction['id']);
        $account = $state->getAccount($transaction['source']);
        $bid_status = $this->determineBidStatus($transaction);
        $account->addBalance($token_amount, $transaction['asset'], $bid_status);

        $state->addLog("Added {$bid_status} bid of ".CurrencyUtil::satoshisToNumber($token_amount)." for {$account['address']} to {$state['timePhase']} auction");

        $state = $this->getAuctioneer()->updateAllBids($state);
    }

    protected function determineBidStatus($transaction) {
        switch (true) {
            case ($transaction['timestamp'] < $this->auction['startDate']):
                return 'prebid';
                break;

            // case ($this->auction['active'] == false):
            //     // if the auction is not active, treat all bids like a prebid
            //     return 'prebid';
            //     break;
            
            case ($transaction['timestamp'] >= $this->auction['endDate']):
                return 'late';
                break;
        }

        return 'live';
    }

    protected function getAuctioneer() {
        if (!isset($this->auctioneer)) {
            $this->auctioneer = new Auctioneer($this->auction);
        }
        return $this->auctioneer;
    }

    /////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////

    protected function applyOutgoingTransactionToState($transaction) {
        // throw new Exception("Unimplemented", 1);
        // nothing yet
    }    

    protected function hasBiddingAsset($transaction) {
        return $transaction['asset'] == $this->auction['bidTokenType'];
    }
    
    protected function isBTCTransaction($transaction) {
        return $transaction['asset'] == 'BTC';
    }
    
    protected function handleUknownIncomingAsset($transaction) {
#       Debug::trace("handleUknownIncomingAsset",__FILE__,__LINE__,$this);
        if ($prize_token_info = $this->auction->findPrizeTokenRequiredInfo($transaction['asset'])) {
            $this->handleIncomingPrizeToken($transaction, $prize_token_info);
        } else {
            $state = $this->getStateAtBlockAndTime($transaction['blockId'], $transaction['timestamp'], $transaction['isMempool']);
            $state->addLog("Received ".CurrencyUtil::satoshisToNumber($transaction['quantity'])." of {$transaction['asset']}.  This token is not recognized by this auction.");
        }
    }

    protected function isPrizeToken($token) {
        return ($this->auction->findPrizeTokenRequiredInfo($token) ? true : false);
    }


    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    // State


    protected function getState() {
        if (!isset($this->auction_state)) {
            $this->auction_state = new AuctionState();
        }
        return $this->auction_state;
    }

    protected function getStateAtBlockAndTime($block_id, $timestamp, $is_mempool) {
        $state = $this->getState();
        $state['hasMempoolTransactions'] = false;

        if ($is_mempool) {
            if ($block_id AND $block_id > 0) { throw new Exception("Unexpected block_id of ".Debug::desc($block_id)." for mempool transaction", 1); }
            // this is a mempool transaction
            $block_id = $this->meta_info['blockHeight'];
            $state['hasMempoolTransactions'] = true;
            $timestamp = $this->now();

#            Debug::trace("\$state['hasMempoolTransactions']=".Debug::desc($state['hasMempoolTransactions'])."",__FILE__,__LINE__,$this);
        } else {
#            Debug::trace("\$state['hasMempoolTransactions']=".Debug::desc($state['hasMempoolTransactions'])."",__FILE__,__LINE__,$this);
            if (!$block_id) { throw new Exception("Unexpected block_id of ".Debug::desc($block_id)." for non-mempool transaction", 1); }
        }

        $state->setBlockId($block_id);
        $state['timePhase'] = $this->buildAuctionTimePhase($timestamp);
        return $state;
    }



    protected function finalizeState($state) {
        if (!$state) { return $state; }
        // update the state status based on the current time (not the last transaction)
        $state['timePhase'] = $this->buildAuctionTimePhase($this->now());
#       Debug::trace("finalizeState this->now()=".Debug::desc(date("Y-m-d H:i:s", $this->now()))." \$state['timePhase']=".Debug::desc($state['timePhase'])."",__FILE__,__LINE__,$this);

        // convert any prebids if the auction ended with prebids
        $state = $this->getAuctioneer()->convertPrebidsIfReady($state);

        // bring bids up to date
        $this->getAuctioneer()->updateAllBids($state);

        // bring payouts up to date
        $this->getAuctioneer()->updateAllPayouts($state, $this->meta_info['blockHeight']);


        return $state;
    }

    protected function serializeState(AuctionState $state=null) {
        if ($state === null) { return null; }
        return $state->serialize();
    }

}

// ########################################################################
// auction:
//   id                   : 101
//   name                 : "Auction One"
//   slug                 : "auction-one"
//   description          : "Best auction ever"
//   create            : 2014-07-31
//   startDate            : 2014-08-01 00:00:00
//   endDate              : 2014-08-05 00:00:00
//   minStartingBid       : 1000
//   bidTokenType         : "LTBCOIN"
//   minBidIncrement      : 1000
//   # startingBounty     : 0

//   bidTokenFeeRequired  : 1000
//   btcFeeRequired       : 0.05

//   winningTokenQuantity : 1
//   winningTokenType     : "SPONSOR"

//   auctionAddress   : "1AUCTION01"


// ########################################################################
// transactions:
//   -
//     auctionId      : 101
//     transactionId  : 5001
//     blockId        : 6001

//     classification : incoming

//     source         : 1OWNER01__________________________
//     destination    : 1AUCTION01
//     asset          : LTBCOIN
//     quantity       : 1000
//     status         : valid
//     tx_hash        : HASH01________________________________
    // timestamp      : 2014-08-04 00:00:00

