<?php

namespace LTBAuctioneer\Auctioneer;

use Exception;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\EventLog\EventLog;
use Utipd\CurrencyLib\CurrencyUtil;

/*
* AuctioneerDaemon
*/
class AuctioneerDaemon
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($xcpd_follower, $native_follower, $simple_daemon_factory, $auction_manager, $auction_updater, $auction_payer, $data_publisher, $blockchain_tx_directory) {
        $this->xcpd_follower           = $xcpd_follower;
        $this->native_follower         = $native_follower;
        $this->simple_daemon_factory   = $simple_daemon_factory;
        $this->auction_manager         = $auction_manager;
        $this->auction_updater         = $auction_updater;
        $this->auction_payer           = $auction_payer;
        $this->data_publisher          = $data_publisher;
        $this->blockchain_tx_directory = $blockchain_tx_directory;
    }

    public function setupAndRun() {
        // genesis blocks
        

        $this->setup();
        $this->run();
    }

    public function setup() {
        $this->setupXCPDFollowerCallbacks();
        $this->setupNativeFollowerCallbacks();
    }

    public function run() {
        EventLog::logEvent('daemon.start', []);

        $f = $this->simple_daemon_factory;
        $iteration_count = 0;

        $loop_function = function() use (&$iteration_count) {
            $this->runOneIteration();
            ++$iteration_count;

            // restart about every 5 minutes
            if ($iteration_count > 60) { throw new Exception("forcing process restart", 250); }
        };

        $error_handler = function($e) use (&$iteration_count) {
            if ($e->getCode() == 250) {
                // force restart
                throw $e;
            }

            EventLog::logError('daemon.error', $e);

            // restart about every 5 minutes
            ++$iteration_count;
            if ($iteration_count > 60) { throw new Exception("forcing process restart", 250); }
        };


        $daemon = $f($loop_function, $error_handler);

        try {
            $daemon->run();
        } catch (Exception $e) {
            if ($e->getCode() == 250) {
                EventLog::logEvent('daemon.shutdown', ['reason' => $e->getMessage()]);
            } else { 
                EventLog::logError('daemon.error.final', $e);
                EventLog::logEvent('daemon.shutdown', []);
            }
        }

    }

    public function runOneIteration() {
        $this->native_follower->processOneNewBlock();

        $this->xcpd_follower->processOneNewBlock();

        // and check for any starting or expired auctions
        $this->checkForAuctionTimePhaseChanges();

        // and check for any auctions that need to be paid out
        $this->checkForPayouts();
    }


    // this can return a null or a new transaction
    public function createNewTransaction($send_data, $auction, $classification, $is_native, $is_mempool) {
        // if this is a mempool transaction, be sure not to delete a transaction
        if ($is_mempool) {
            $existing_live_blockchain_tx_entry = $this->blockchain_tx_directory->findOne(['tx_hash' => $send_data['tx_hash'], 'isNative' => $is_native ? 1 : 0, 'isMempool' => 0]);

            if ($existing_live_blockchain_tx_entry) {
                // this is a mempool transaction arrive AFTER a live version of the transaction has already been recorded
                //   we never want to erase a live transaction with its mempool equivalent
                //   so ignore this
                EventLog::logEvent('tx.ignored', ['tx_hash' => $send_data['tx_hash'], 'isNative' => $is_native, 'isMempool' => $is_mempool]);
                return;
            }
        }

        // delete any existing transactions with this tx_hash
        $this->blockchain_tx_directory->deleteWhere(['tx_hash' => $send_data['tx_hash'], 'isNative' => $is_native ? 1 : 0]);


        // create a new transaction
        $new_transaction = $this->blockchain_tx_directory->createAndSave([
            'auctionId'      => $auction['id'],
            'transactionId'  => $is_mempool ? $send_data['tx_hash'] : $send_data['tx_index'],
            'blockId'        => $is_mempool ? 0 : $send_data['block_index'],

            'classification' => $classification,

            'source'         => $send_data['source'],
            'destination'    => $send_data['destination'],
            'asset'          => $send_data['asset'],
            'quantity'       => $send_data['quantity'],
            'status'         => $is_mempool ? 'mempool' : $send_data['status'],
            'tx_hash'        => $send_data['tx_hash'],

            'isNative'       => $is_native,
            'isMempool'      => $is_mempool,

            'timestamp'      => time(),
        ]);
        return $new_transaction;

// {
//     "source": "13UxmTs2Ad2CpMGvLJu3tSV2YVuiNcVkvn",
//     "destination": "1KbbyhT3dPAMEGfVx9siDtKATLpk9vjQkW",
//     "asset": "SLVAGOAAAAAAA",
//     "quantity": 10,
//     "tx_hash": "c324e62d0ba17f42a774b9b28114217c777914a4b6dd0d41811217cffb8c40a6"
// }

    
        
    }

    public function updateAuction($auction, $block_height) {
        if ($block_height === null OR $block_height == 0) {
            $block_height = $this->getCurrentBlockHeight();
        }

        $auction = $this->auction_updater->updateAuctionState($auction, $block_height);
        $this->data_publisher->publishAuctionState($auction, $block_height);
    }


    ////////////////////////////////////////////////////////////////////////


    protected function checkForAuctionTimePhaseChanges() {
        $current_block_height = null;
#        Debug::trace("calling findAuctionsThatNeedTimePhaseUpdate",__FILE__,__LINE__,$this);
        foreach ($this->auction_manager->findAuctionsThatNeedTimePhaseUpdate() as $auction) {
#           Debug::trace("findAuctionsThatNeedTimePhaseUpdate auction=".Debug::desc($auction)."",__FILE__,__LINE__,$this);
            if ($current_block_height === null) { $current_block_height = $this->getCurrentBlockHeight(); }
            $this->updateAuction($auction, $current_block_height);
        } 
    }

    protected function checkForPayouts() {
        $current_block_height = $this->getCurrentBlockHeight();
        foreach ($this->auction_manager->findAuctionsPendingPayout() as $auction) {

            // check for manual payouts
            if (isset($auction['manualPayoutsToTrigger']) AND $auction['manualPayoutsToTrigger']) {
                try {
                    $this->auction_payer->payoutAuction($auction, $auction['manualPayoutsToTrigger']);
                } catch (Exception $e) {
                    Debug::errorTrace("ERROR: ".$e->getMessage(),__FILE__,__LINE__,$this);                    
                }

                // never process automatic payouts for auctions that are manually paid out
                continue;
            }

            // check confirmations
#          Debug::trace("\$current_block_height=".Debug::desc($current_block_height)." auction blockId=".Debug::desc($auction['state']['blockId'])."  confirmations: ".Debug::desc($current_block_height - $auction['state']['blockId'])."",__FILE__,__LINE__,$this);
            if (($current_block_height - $auction['state']['blockId']) >= $auction['confirmationsRequired']) {
                // do payout
                try {
#                    Debug::trace("payoutAuction",__FILE__,__LINE__,$this);
                    $this->auction_payer->payoutAuction($auction);
                } catch (Exception $e) {
                    Debug::errorTrace("ERROR: ".$e->getMessage(),__FILE__,__LINE__,$this);                    
                }
            }
        } 


        // TODO: confirm auction payouts
        // foreach ($this->auction_manager->findAuctionsPendingPayoutConfirmation() as $auction) {
        // } 
    }

    protected function setupXCPDFollowerCallbacks() {
        $this->xcpd_follower->handleNewBlock(function($block_id) {
#            Debug::trace("\$block_id=".Debug::desc($block_id)."",__FILE__,__LINE__,$this);
            EventLog::logEvent('xcpd.block.found', ['blockId' => $block_id]);

            // clear all mempool transactions
            $this->clearAllMempoolTransactions($native=false);

            // publish all auction states to update the last block seen
            // (we really should separate this to its own socket)
            foreach ($this->auction_manager->allAuctions() as $auction) {
                // update the auction because the pending status may have changed
                $this->updateAuction($auction, $block_id);
            }
        });

        $this->xcpd_follower->handleNewSend(function($send_data, $block_id, $is_mempool) {
#           Debug::trace("handleNewSend received \$send_data=".Debug::desc($send_data)."",__FILE__,__LINE__,$this);
            // we have a new send from XCPD
            
            // find all auctions
            foreach ($this->auction_manager->allAuctions() as $auction) {
                $address = $auction['auctionAddress'];
                if (!$address) { continue; }

                if ($address == $send_data['source']) {
                    // this is a send by the auction
                    // save the transaction
                    EventLog::logEvent('tx.outgoing', ['auctionId' => $auction['id'], 'tx' => $send_data, 'mempool' => $is_mempool]);
                    if (!$send_data['assetInfo']['divisible']) {
                        $send_data['quantity'] =  CurrencyUtil::numberToSatoshis($send_data['quantity']);
                    }

                    $new_transaction = $this->createNewTransaction($send_data, $auction, 'outgoing', false, $is_mempool);
                    if ($new_transaction) {
                        $this->updateAuction($auction, $new_transaction['blockId']);
                    }
                }
                if ($address == $send_data['destination']) {
                    // this is an incoming transaction
                    //   we will process this
                    EventLog::logEvent('tx.incoming', ['auctionId' => $auction['id'], 'tx' => $send_data, 'mempool' => $is_mempool]);
                    if (!$send_data['assetInfo']['divisible']) {
                        $send_data['quantity'] = CurrencyUtil::numberToSatoshis($send_data['quantity']);
                    }
                    $new_transaction = $this->createNewTransaction($send_data, $auction, 'incoming', false, $is_mempool);
                    if ($new_transaction) {
                        $this->updateAuction($auction, $new_transaction['blockId']);
                    }
                }
            }
        });
    }

    protected function setupNativeFollowerCallbacks() {
        $this->native_follower->handleNewBlock(function($block_id) {
            // a new block was found...
            //   just smile and be happy
            EventLog::logEvent('native.block.found', ['blockId' => $block_id]);

            // clear all mempool transactions
            $this->clearAllMempoolTransactions($native=true);
        });

        $this->native_follower->handleNewTransaction(function($transaction, $block_id, $is_mempool) {
            // a new transaction
            // txid: cc91db2f18b908903cb7c7a4474695016e12afd816f66a209e80b7511b29bba9
            // outputs:
            //     - amount: 100000
            //       address: "1aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

            $auction_addresses_map = $this->allAuctionsByAddress();

#            Debug::trace("\$block_id=".Debug::desc($block_id)."",__FILE__,__LINE__,$this);
#            Debug::trace("\$is_mempool=".Debug::desc($is_mempool)."",__FILE__,__LINE__,$this);
#            Debug::trace("\$transaction=".json_encode($transaction, 192),__FILE__,__LINE__);
            foreach ($transaction['outputs'] as $output) {
                if (!$output['address']) { continue; }

                $destination_address = $output['address'];
                if (isset($auction_addresses_map[$destination_address])) {
                    $auction = $auction_addresses_map[$destination_address];

                    // this is an interesting blockChaing transaction
                    $btc_send_data = [];
                    $btc_send_data['tx_index']    = $transaction['txid'];
                    $btc_send_data['block_index'] = $block_id;
                    $btc_send_data['source']      = ''; // not tracked
                    $btc_send_data['destination'] = $destination_address;
                    $btc_send_data['asset']       = 'BTC';
                    $btc_send_data['quantity']    = $output['amount']; // already in satoshis
                    $btc_send_data['status']      = 'valid';
                    $btc_send_data['tx_hash']     = $transaction['txid'];

                    $new_transaction = $this->createNewTransaction($btc_send_data, $auction, 'incoming', true, $is_mempool);
                    if ($new_transaction) {
                        $this->updateAuction($auction, $new_transaction['blockId']);
                    }
                }
            }
        });

        $this->native_follower->handleOrphanedBlock(function($orphaned_block_id) {
#           Debug::trace("handleOrphanedBlock \$orphaned_block_id=".Debug::desc($orphaned_block_id)."",__FILE__,__LINE__,$this);
            EventLog::logEvent('block.orphan', ['blockId' => $orphaned_block_id]);

            // get all auctions affected
            $entries = $this->blockchain_tx_directory->find(['blockId' => $orphaned_block_id]);
            $auction_ids = [];
            foreach($entries as $entry) {
                $auction_ids[$entry['auctionId']] = true;
            }

            // delete transactions
            $this->blockchain_tx_directory->deleteWhere(['blockId' => $orphaned_block_id]);

            // inform the counterparty follower that a block has been orphaned
            $this->xcpd_follower->orphanBlock($orphaned_block_id);

            // update and republish all affected auctions
            foreach($auction_ids as $auction_id => $_nothin) {
                if ($auction = $this->auction_manager->findById($auction_id)) {
                    $this->updateAuction($auction, $orphaned_block_id - 1);
                }
            }
        });
    }

    protected function allAuctionsByAddress() {
        $addresses_map = [];
        foreach ($this->auction_manager->allAuctions() as $auction) {
            $address = $auction['auctionAddress'];
            if (!$address) { continue; }
            $addresses_map[$address] = $auction;
        }
        return $addresses_map;
    }

    protected function getCurrentBlockHeight() {
        // $tx = $this->blockchain_tx_directory->findOne([], ['blockId' => -1]);
        // if ($tx) {
        //     return $tx['blockId'];
        // }
        // return 0;

        return $this->xcpd_follower->getLastProcessedBlock();
    }

    protected function clearAllMempoolTransactions($is_native) {
        $this->blockchain_tx_directory->deleteRaw("DELETE FROM {$this->blockchain_tx_directory->getTableName()} WHERE isMempool = ? AND isNative = ?", [1, intval($is_native)]);
    }

    //     "block_index" => 313360,
    //     "tx_index"    => 100000,
    //     "source"      => "1NFeBp9s5aQ1iZ26uWyiK2AYUXHxs7bFmB",
    //     "destination" => "1B7FpKyJ4LtcqfxqR2zUtquSupcPnLuZpk",
    //     "asset"       => "XCP",
    //     "status"      => "valid",
    //     "quantity"    => 490000000,
    //     "tx_hash"     => "278afe117b744fc9e23c0198e9555a129b3b72f974503e81d6fb5df4bc453688",

}

