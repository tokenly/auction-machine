<?php

namespace LTBAuctioneer\Test\Auction;

use Exception;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* AuctionStateUtil
*/
class AuctionStateUtil
{

    ////////////////////////////////////////////////////////////////////////

    // runs a scenario and saves the auction to disk
    // returns the new auction
    public static function runAuctionScenario($app, $scenario=16) {
        if ($scenario === false) { $scenario = 1; }
        $scenario_name = 'auction-scenario'.sprintf('%02d', $scenario).'.yml';

#        Debug::trace("buildValidAuctionStateFromScenario: $scenario_name",__FILE__,__LINE__);
        $scenario_data = self::loadAuctionStateScenarioData($scenario_name);

        // dir
        $auction_directory = $app['directory']('Auction');

        // create an auction (through the manager)
        $auction_manager = $app['auction.manager'];
        $auction = $auction_manager->newAuction($scenario_data['auction']);

        // create transactions
        $transactions = [];
        $xcp_tx_dir = $app['directory']('BlockchainTransaction');
        $fake_id = 100;
        foreach ($scenario_data['transactions'] as $transaction_entry) {
            // create a unique tx has to satisfy the MySQL table unique key constraint
            $transaction = $xcp_tx_dir->createAndSave($transaction_entry);

            // create a faux id
            $transaction['id'] = ++$fake_id;

            $transactions[] = $transaction;
        }

        // now build the state
        $meta_info = $scenario_data['meta'];
        $auction_state_vars = $app['auction.stateBuilder']->buildAuctionStateFromTransactions($transactions, $auction, $meta_info);

        // validate
        self::assertAuctionStateEquals($scenario_data['expectedState'], $auction_state_vars, $scenario_name);

        // save the state
        $auction_directory->update($auction, ['state' => $auction_state_vars]);

        return $auction_directory->reload($auction);

    }


    public static function buildValidAuctionStateFromScenario($app, $scenario_name) {
#        Debug::trace("buildValidAuctionStateFromScenario: $scenario_name",__FILE__,__LINE__);
        $scenario_data = self::loadAuctionStateScenarioData($scenario_name);

        // create an auction
        $auction = $app['directory']('Auction')->create($scenario_data['auction']);

        // create transactions
        $transactions = [];
        $xcp_tx_dir = $app['directory']('BlockchainTransaction');
        $fake_id = 100;
        foreach ($scenario_data['transactions'] as $transaction_entry) {
            $transaction = $xcp_tx_dir->create($transaction_entry);

            // create a faux id
            $transaction['id'] = ++$fake_id;

            $transactions[] = $transaction;
        }
#       Debug::trace("\$transactions=\n".json_encode($transactions, 192),__FILE__,__LINE__);

        // now build the state
        $meta_info = $scenario_data['meta'];
        $auction_state_vars = $app['auction.stateBuilder']->buildAuctionStateFromTransactions($transactions, $auction, $meta_info);
#        Debug::trace("\$auction_state_vars=\n".json_encode($auction_state_vars, 192),__FILE__,__LINE__);

        // validate
        self::assertAuctionStateEquals($scenario_data['expectedState'], $auction_state_vars, $scenario_name);

        return $auction_state_vars;
    }

    public static function loadAuctionStateScenarioData($scenario_name) {
        $raw_data = yaml_parse_file(TEST_PATH.'/etc/'.$scenario_name);
        return self::preprocessRawScenarioData($raw_data);
    }

    ////////////////////////////////////////////////////////////////////////

    public static function assertAuctionStateEquals($expected_state_vars, $auction_state_vars, $scenario_name) {
        $auction_state_vars_for_comparison = (array)$auction_state_vars;

        // state var fields to ignore
        foreach (['logs','bidsAndAccounts','blockId', 'hasMempoolTransactions', 'payoutHashes'] as $field) {
            if (!isset($expected_state_vars[$field])) { unset($auction_state_vars_for_comparison[$field]); }
        }


        PHPUnit::assertEquals($expected_state_vars, $auction_state_vars_for_comparison, "Failed running scenario $scenario_name");
    }


    public static function preprocessRawScenarioData($raw_data) {
        // start with raw data
        $out = $raw_data;

        // update auction fields
        $auction = $raw_data['auction'];
        foreach (['create','startDate','endDate'] as $date_field) { $auction[$date_field] = strtotime($auction[$date_field]); }
        foreach (['bidTokenFeeRequired','btcFeeRequired','minStartingBid','minBidIncrement','winningTokenQuantity','startingBounty',] as $amount_field) {
            if (isset($auction[$amount_field])) {
                $auction[$amount_field] = CurrencyUtil::numberToSatoshis($auction[$amount_field]);
            }
        }
        foreach ($auction['prizeTokensRequired'] as $offset => $prize_token_info) {
            $auction['prizeTokensRequired'][$offset]['amount'] = CurrencyUtil::numberToSatoshis($auction['prizeTokensRequired'][$offset]['amount']);
        }
        $out['auction'] = $auction;

        // update transaction fields
        $transactions = [];
        foreach($raw_data['transactions'] as $transaction) {
            foreach (['quantity'] as $amount_field) { $transaction[$amount_field] = CurrencyUtil::numberToSatoshis($transaction[$amount_field]); }
            foreach (['timestamp'] as $date_field) { if (isset($transaction[$date_field])) { $transaction[$date_field] = strtotime($transaction[$date_field]); } }
            // random txId
            $transaction['transactionId'] = md5(uniqid());

            // isMempool defaults to false
            $transaction['isMempool'] = isset($transaction['isMempool']) ? $transaction['isMempool'] : false;

            $transactions[] = $transaction;
        }
        $out['transactions'] = $transactions;

        // update expectedState fields
        $expected_state = $raw_data['expectedState'];

        foreach (['bidTokenFeeApplied','btcFeeApplied',] as $amount_field) { $expected_state[$amount_field] = CurrencyUtil::numberToSatoshis($expected_state[$amount_field]); }

        // process address accounts
        if (isset($expected_state['accounts'])) {
            foreach ($expected_state['accounts'] as $address => $account) {
                if (isset($account['balances']) AND $account['balances']) {
                    foreach ($account['balances'] as $token => $statuses) {
                        foreach ($statuses as $status => $amount) {
                            $expected_state['accounts'][$address]['balances'][$token][$status] = CurrencyUtil::numberToSatoshis($amount);
                        }
                    }
                }
            }
        } 

        // process bids
        if (isset($expected_state['bids'])) {
            foreach ($expected_state['bids'] as $offset => $bid) {
                $expected_state['bids'][$offset]['amount'] = CurrencyUtil::numberToSatoshis($bid['amount']);
            }
        } 

        // prize tokens applied
        if (isset($expected_state['prizeTokensApplied'])) {
            foreach ($expected_state['prizeTokensApplied'] as $token_name => $value) {
                $expected_state['prizeTokensApplied'][$token_name] = CurrencyUtil::numberToSatoshis($value);
            }
        } 

        // payouts applied
        if (isset($expected_state['payouts'])) {
            foreach ($expected_state['payouts'] as $offset => $payout_info) {
                $expected_state['payouts'][$offset]['amount'] = CurrencyUtil::numberToSatoshis($payout_info['amount']);
            }
        } 

        foreach (['bounty',] as $amount_field) {
            if (isset($expected_state[$amount_field])) {
                $expected_state[$amount_field] = CurrencyUtil::numberToSatoshis($expected_state[$amount_field]);
            }
        }



        $out['expectedState'] = $expected_state;

        // meta now
        if (isset($out['meta']['now'])) {
            $out['meta']['now'] = strtotime($out['meta']['now']);
        } else {
            // ended by default
            $out['meta']['now'] = strtotime('+1 day');
        }

        return $out;
    }
}

