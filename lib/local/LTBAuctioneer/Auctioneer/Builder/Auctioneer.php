<?php

namespace LTBAuctioneer\Auctioneer\Builder;

use Exception;
use LTBAuctioneer\Auctioneer\AuctionState;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;

/*
* Auctioneer
*/
class Auctioneer
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($auction) {
        $this->auction = $auction;
        $this->bid_token = $auction['bidTokenType'];
    }

    public function updateAllBids(AuctionState $state) {
        //
#        Debug::trace("\$state['status']=".Debug::desc($state['status'])."",__FILE__,__LINE__,$this);
        if ($state['timePhase'] == 'prebid' OR !$state['active']) {
            // just organize prebids
            $state = $this->buildAllPrebids($state);

        } else {
            // current top bid (if any)
            $top_bid = $state->getTopBid();

            // check for a new leading bidder (if any)
            list($new_bidder_account, $bid, $bounty) = $this->findNewTopBidder($state, $top_bid);
            if ($new_bidder_account) {
                $state = $this->applyNewTopBidToState($new_bidder_account, $bid, $bounty, $top_bid, $state);
            }

            // reorganize the bids, leaving the top bid unchanged
            $state = $this->rebuildAllBids($state);

            // update the new bounty
            if ($top_bid = $state->getTopBid()) {
                $state['bounty'] = $this->calculateBounty($top_bid['amount']);
#               Debug::trace("\$state['bounty']=".CurrencyUtil::satoshisToNumber($state['bounty'])."",__FILE__,__LINE__,$this);
            }
        }

        return $state;
    }

    // if there are any early prebids, convert them to live now
    public function convertPrebidsIfReady(AuctionState $state) {
        if ($state['timePhase'] != 'prebid' AND $state['active']) {
            if ($state->hasEarlyBids()) {
                // apply all early bids
                $state = $this->convertEarlyBidsToLiveBids($state);
            }
        }
        return $state;
    }

    public function updateAllPayouts(AuctionState $state, $current_block_height) {
        $state->resetAllPayouts();

        if ($state['btcFeeSatisfied']) {
            $this->buildWinnerPayouts($state);
            $this->buildAccountPayouts($state);
            $this->buildSellerPayout($state);
            $this->buildPlatformPayouts($state);
            $this->authorizePayouts($state, $current_block_height);
            $this->hashPayouts($state);
        } 
    }
    

    ////////////////////////////////////////////////////////////////////////

    protected function findNewTopBidder($state, $top_bid) {
        $starting_address = $top_bid ? $top_bid['address'] : null;

        if ($top_bid) {
            $minimum_bid = $top_bid['amount'] + $this->auction['minBidIncrement'];
            $bounty = $this->calculateBounty($top_bid['amount']);
        } else {
            $minimum_bid = $this->auction['minStartingBid'];
            // We start with 0 bounty
            $bounty = 0;
        }
        $minimum_payment_required = $minimum_bid + $bounty;

        $new_top_payment = 0;
        $new_top_bid_amount = null;
        $new_top_bidder_account = null;
        foreach ($this->getAccountsByMostAmount($state, $starting_address) as $account) {
            $amount = $account->getBalance($this->bid_token);
            if ($amount >= $minimum_payment_required AND $amount > $new_top_payment) {
                // we can't outbid ourselves
                if ($account['address'] == $starting_address) { throw new Exception("Attempted to outbid oneself", 1); }

                // save the top bid and bidder
                $new_top_payment = $amount;
                $new_top_bidder_account = $account;
                $new_top_bid_amount = $amount - $bounty;
            }
        }

        // see if we found a new bidder
        if ($new_top_bidder_account) {
            return [$new_top_bidder_account, $new_top_bid_amount, $bounty];
        }

        // no eligible new bids
        return [null,null,null];
    }

    // finds all addresses starting with the one after the starting_address, but never the starting address
    protected function getAccountsByMostAmount($state, $starting_address=null, $balance_type='live') {
        $addresses = $this->addressesInOrderAfterAddress($state, $starting_address);

        // get all accounts
        $accounts = [];
        foreach($addresses as $address) {
            $accounts[] = $state->getAccount($address);
        }

        // sort
        $accounts = $this->sortAccountsByDescendingAmount($accounts, $balance_type);

        return $accounts;
    }

    protected function addressesInOrderAfterAddress($state, $starting_address=null) {
        $addresses = $state->getAccountAddresses();
        $total_length = count($addresses);

        $addresses_out = [];
        if ($total_length > 0) {

            // start at 0, or at the offset of the starting_address
            $starting_offset = 0;
            if ($starting_address !== null) {
                $starting_offset = array_search($starting_address, $addresses);
                $starting_offset = ($starting_offset + 1) % $total_length;
            } else {
                $starting_offset = 0;
            }
            $ending_offset = $starting_offset;

            // iterate through the addresses
            $working_offset = $starting_offset;
            while (true) {
                $address = $addresses[$working_offset];
                if ($address == $starting_address) { break; }

                $addresses_out[] = $address;

                $working_offset = ($working_offset + 1) % $total_length;
                if ($working_offset == $ending_offset) { break; }
            }
        }

        return $addresses_out;
    }

    protected function sortAccountsByDescendingAmount($accounts, $balance_type) {
        usort($accounts, function($a, $b) use ($balance_type) {
            $b_amount = $b->getBalance($this->bid_token, $balance_type);
            $a_amount = $a->getBalance($this->bid_token, $balance_type);

            // sort by amount unless the amounts are the same
            if ($a_amount < $b_amount) { return 1; }
            if ($a_amount > $b_amount) { return -1; }

            // if they have the same amount, sort by order of creation
            if ($a->getDefaultSortOrder() > $b->getDefaultSortOrder()) { return 1; }
            if ($a->getDefaultSortOrder() < $b->getDefaultSortOrder()) { return -1; }

            // they are the same in every way
            return 0;
        });

        return $accounts;
    }


    protected function applyNewTopBidToState($account, $bid, $bounty, $previous_top_bid, $state) {
        $new_bid = [
            'address' => $account['address'],
            'amount'  => $bid,
            'token'   => $this->bid_token,
            'status'  => 'active',
            'rank'    => 0,
        ];
        $state->addLog("new top bid applied: {$account['address']} at ".CurrencyUtil::satoshisToNumber($new_bid['amount'])."");

        // update the old bid
        if ($state['bids'] AND $state['bids'][0]) {
            $state['bids'][0]['status'] = 'outbid';
        }

        // insert in the front of the bids
        array_unshift($state['bids'], $new_bid);

        // pay the bounty to the previous top bidder
        // Debug::trace("\$previous_top_bid=".Debug::desc($previous_top_bid)."",__FILE__,__LINE__,$this);
        if ($previous_top_bid) {
            $previous_top_bid_account = $state->getAccount($previous_top_bid['address']);

            $account->subtractBalance($bounty, $this->bid_token);
            $previous_top_bid_account->addBalance($bounty, $this->bid_token);
            $state->addLog("Bounty paid: {$account['address']} paid {$previous_top_bid_account['address']} ".CurrencyUtil::satoshisToNumber($bounty)."");
        }

        return $state;
    }

    protected function rebuildAllBids($state) {
        if (!$state['bids']) { return $state; }

        $first_bid = $state['bids'][0];
        $new_bids = [$first_bid];

        $rank = 1;
        foreach ($this->getAccountsByMostAmount($state) as $account) {
            // the leading bid may not actually be the one with the most in the account
            //   but it still should be first
            //   Just update the amount and leave it at rank 0
            if ($first_bid AND $account['address'] == $first_bid['address']) {
                // don't add to the list, but do update the amount
                $new_bids[0]['amount'] = $account->getBalance($this->bid_token);
                continue;
            }

            $status = 'outbid';
            $amount = $account->getBalance($this->bid_token, 'live');

            // build a new secondary bid
            $new_bid = [
                'address' => $account['address'],
                'amount'  => $account->getBalance($this->bid_token),
                'token'   => $this->bid_token,
                'status'  => $status,
                'rank'    => $rank,
            ];
            $new_bids[] = $new_bid;

            ++$rank;
        }

        $state['bids'] = $new_bids;
        return $state;
    }

    protected function buildAllPrebids($state) {
        $new_bids = [];

        $rank = 0;
        foreach ($this->getAccountsByMostAmount($state, null, 'prebid') as $account) {
            $amount = $account->getBalance($this->bid_token, 'prebid');

            // build a new secondary bid
            $new_bid = [
                'address' => $account['address'],
                'amount'  => $account->getBalance($this->bid_token, 'prebid'),
                'token'   => $this->bid_token,
                'status'  => 'prebid',
                'rank'    => $rank,
            ];
            $new_bids[] = $new_bid;

            ++$rank;
        }
#        Debug::trace("buildAllPrebids \$new_bids=".json_encode($new_bids, 192),__FILE__,__LINE__,$this);

        $state['bids'] = $new_bids;
        return $state;
    }

    protected function convertEarlyBidsToLiveBids($state) {
        $state->addLog("begin converting prebid bids to live");
        $new_bids = [];

        $rank = 0;
        foreach ($this->getAccountsByMostAmount($state, null, 'prebid') as $account) {
            // build a new live bid
            $amount = $account->getBalance($this->bid_token, 'prebid');
            $new_bid = [
                'address' => $account['address'],
                'amount'  => $amount,
                'token'   => $this->bid_token,
                'status'  => ($rank === 0 AND $amount >= $this->auction['minStartingBid']) ? 'active' : 'outbid',
                'rank'    => $rank,
            ];
            $new_bids[] = $new_bid;

            ++$rank;

            // convert any prebid account balance to a live balance
            $account->subtractBalance($amount, $this->bid_token, 'prebid');
            $account->addBalance($amount, $this->bid_token, 'live');

            $state->addLog("bid {$account['address']} converted ".CurrencyUtil::satoshisToNumber($amount)." prebid to live bid");
        }

        // assign the bids
        $state['bids'] = $new_bids;

        // update the new bounty
        if ($top_bid = $state->getTopBid()) {
            $state['bounty'] = $this->calculateBounty($top_bid['amount']);
#           Debug::trace("\$state['bounty']=".CurrencyUtil::satoshisToNumber($state['bounty'])."",__FILE__,__LINE__,$this);
            $state->addLog("new bounty is ".CurrencyUtil::satoshisToNumber($state['bounty']));
        }

        $state->addLog("done converting prebid bids to live.  Top bidder is ".($new_bids ? ($new_bids['0']['address']) : "no one"));
        return $state;
    }

    protected function calculateBounty($amount) {
        $bounty_pct = $this->auction['bountyPercent'];
        return intval(floor(($amount * $bounty_pct) / ($this->auction['minBidIncrement'] * $bounty_pct)) * ($this->auction['minBidIncrement'] * $bounty_pct));
    }

    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////

    protected function buildWinnerPayouts($state) {
        $top_bid = $state->getTopBid();
        if (!$top_bid OR $top_bid['status'] != 'active') {
            $this->refundWinningTokensToSeller($state);
            return;
        }
        $top_bidder_address = $top_bid['address'];

        foreach ($this->auction['prizeTokensRequired'] as $prize) {
            $payout = $state->findOrCreatePayout($top_bidder_address, $prize['token']);
            $payout['amount'] = $payout['amount'] + $prize['amount'];

            $state->addLog("Top bidder gets winning payout of ".$payout);
        }
    }
    
    protected function buildAccountPayouts($state) {
        $top_bid = $state->getTopBid();
        $top_bidder_address = ($top_bid AND $top_bid['status'] == 'active') ? $top_bid['address'] : null;

        if (!$top_bid OR $top_bid['status'] != 'active') {
            $this->refundBidTokenFeeToSeller($state);
            $top_bidder_address = null;
        }


        $bid_token = $this->bid_token;
        foreach ($state['bids'] as $bid) {
            $payout = $state->findOrCreatePayout($bid['address'], $bid_token);
            $account = $state->getAccount($bid['address']);

            if ($bid['address'] == $top_bidder_address) {
                // for the top bidder,
                //   the payout is only the token fee
                $amount = $state['bidTokenFeeApplied'];

                // also return any late bids from the winner, because we are nice
                $amount = $amount + $account->getBalance($bid_token, 'late');
                $type_desc = 'Winner';
            } else {
                // everyone else gets their entire bid back and any late bids
                $amount = $account->getBalance($bid_token, 'live');
                $amount = $amount + $account->getBalance($bid_token, 'late');
                $amount = $amount + $account->getBalance($bid_token, 'prebid'); // this will never actually get sent, because all prebids are converted before an auction ever would end
                $type_desc = 'Refund';
            }

#            Debug::trace("adding ".CurrencyUtil::satoshisToNumber($amount)." to payout for {$bid['address']}.  previous amount was: ".CurrencyUtil::satoshisToNumber($payout['amount']),__FILE__,__LINE__,$this);
            $payout['amount'] = $payout['amount'] + $amount;

            $state->addLog("{$type_desc} ".$payout);
        }
    }

    protected function buildSellerPayout($state) {
        $top_bid = $state->getTopBid();
        if (!$top_bid OR $top_bid['status'] != 'active') { return; }

        $payout = $state->findOrCreatePayout($this->auction['sellerAddress'], $this->bid_token);
        $payout['amount'] = $payout['amount'] + $top_bid['amount'];
    }

    protected function buildPlatformPayouts($state) {
        // sweep BTC
        if ($state['btcFeeSatisfied']) {
            $payout = $state->findOrCreatePayout($this->auction['platformAddress'], 'BTC');
            $payout['amount'] = $this->auction['btcFeeRequired'];
            $payout['sweep'] = true;
            $state->addLog("Sweep BTC of ".$payout);

        }
    }

    protected function refundWinningTokensToSeller($state) {
        foreach ($this->auction['prizeTokensRequired'] as $prize) {
            $amount = $state->getPrizeTokenAppliedAmount($prize['token']);
            if ($amount > 0) {
                $payout = $state->findOrCreatePayout($this->auction['sellerAddress'], $prize['token']);
                $payout['amount'] = $payout['amount'] + $amount;
                $state->addLog("Refund winning token with ".$payout);
            }
        }
    }

    protected function refundBidTokenFeeToSeller($state) {
        $amount = $state['bidTokenFeeApplied'];
        if ($state['btcFeeSatisfied'] AND $amount > 0) {
            $payout = $state->findOrCreatePayout($this->auction['sellerAddress'], $this->bid_token);
            $payout['amount'] = $payout['amount'] + $amount;

            $state->addLog("Refund bid token fee with ".$payout);
        }
    }

    protected function authorizePayouts($state, $current_block_height) {
        $max_block_height_allowed = ($current_block_height - $this->auction['confirmationsRequired']);
#        Debug::trace("\$state->getBlockId()=".Debug::desc($state->getBlockId())."  \$max_block_height_allowed=".Debug::desc($max_block_height_allowed)."",__FILE__,__LINE__,$this);
        $authorized = ($state->getBlockId() <= $max_block_height_allowed);
#        Debug::trace("\$authorized=".Debug::desc($authorized)."",__FILE__,__LINE__,$this);

        foreach ($state->getAllPayouts() as $payout) {
            $payout['authorized'] = $authorized;
        }

    }

    protected function hashPayouts($state) {
        $payout_hashes = [];

        foreach ($state->getAllPayouts() as $payout) {
            $hash = $this->hashPayout($payout);
            $payout_hashes[$hash] = $payout;
        }

        $state['payoutHashes'] = $payout_hashes;

    }

    protected function hashPayout($payout) {
        return md5(json_encode((array)$payout));
    }

}


