<?php

namespace LTBAuctioneer\Auctioneer;

use Exception;
use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\EventLog\EventLog;

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
        $count = 0;

        $loop_function = function() use (&$count) {
            $this->runOneIteration();
            ++$count;

            if ($count > 120) { throw new Exception("Debug: forcing process restart", 250); }
        };

        $error_handler = function($e) {
            EventLog::logError('daemon.error', $e);
            if ($e->getCode() == 250) {
                // force restart
                throw $e;
            }
        };

        $daemon = $f($loop_function, $error_handler);
        $daemon->run();

        EventLog::logEvent('daemon.shutdown', []);
    }

    public function runOneIteration() {
        $this->native_follower->processOneNewBlock();

        $this->xcpd_follower->processOneNewBlock();

        // and check for any starting or expired auctions
        $this->checkForAuctionTimePhaseChanges();

        // and check for any auctions that need to be paid out
        $this->checkForPayouts();
    }


    public function updateAuctionWithNewTransaction($send_data, $auction, $classification) {
        $new_transaction = $this->blockchain_tx_directory->createAndSave([
            'auctionId'      => $auction['id'],
            'transactionId'  => $send_data['tx_index'],
            'blockId'        => $send_data['block_index'],

            'classification' => $classification,

            'source'         => $send_data['source'],
            'destination'    => $send_data['destination'],
            'asset'          => $send_data['asset'],
            'quantity'       => $send_data['quantity'],
            'status'         => $send_data['status'],
            'tx_hash'        => $send_data['tx_hash'],

            'timestamp'      => time(),
        ]);

        // update the auction state
        $this->updateAuction($auction, $new_transaction['blockId']);
    }

    public function updateAuction($auction, $block_height) {
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
    }

    protected function setupXCPDFollowerCallbacks() {
        $this->xcpd_follower->handleNewBlock(function($block_id) {
#            Debug::trace("\$block_id=".Debug::desc($block_id)."",__FILE__,__LINE__,$this);
            EventLog::logEvent('xcpd.block.found', ['blockId' => $block_id]);

            // publish all auction states to update the last block seen
            // (we really should separate this to its own socket)
            foreach ($this->auction_manager->allAuctions() as $auction) {
                $this->data_publisher->publishAuctionState($auction, $block_id);
            }
        });

        $this->xcpd_follower->handleNewSend(function($send_data, $block_id) {
#            Debug::trace("handleNewSend received \$send_data=".Debug::desc($send_data)."",__FILE__,__LINE__,$this);
            // we have a new send from XCPD
            
            // find all auctions
            foreach ($this->auction_manager->allAuctions() as $auction) {
                $address = $auction['auctionAddress'];
                if (!$address) { continue; }

                if ($address == $send_data['source']) {
                    // this is a send by the auction
                    // save the transaction
                    EventLog::logEvent('tx.outgoing', ['auctionId' => $auction['id'], 'tx' => $send_data]);
                    if (!$send_data['assetInfo']['divisible']) {
                        $send_data['quantity'] =  CurrencyUtil::numberToSatoshis($send_data['quantity']);
                    }
                    $this->updateAuctionWithNewTransaction($send_data, $auction, 'outgoing');
                }
                if ($address == $send_data['destination']) {
                    // this is an incoming transaction
                    //   we will process this
                    EventLog::logEvent('tx.incoming', ['auctionId' => $auction['id'], 'tx' => $send_data]);
                    if (!$send_data['assetInfo']['divisible']) {
                        $send_data['quantity'] = CurrencyUtil::numberToSatoshis($send_data['quantity']);
                    }
                    $this->updateAuctionWithNewTransaction($send_data, $auction, 'incoming');
                }
            }
        });
    }

    protected function setupNativeFollowerCallbacks() {
        $this->native_follower->handleNewBlock(function($block_id) {
            // a new block was found...
            //   just smile and be happy
            EventLog::logEvent('native.block.found', ['blockId' => $block_id]);
        });

        $this->native_follower->handleNewTransaction(function($transaction, $block_id) {
            // a new transaction
            // txid: cc91db2f18b908903cb7c7a4474695016e12afd816f66a209e80b7511b29bba9
            // outputs:
            //     - amount: 100000
            //       address: "1aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

            $auction_addresses_map = $this->allAuctionsByAddress();

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

                    $this->updateAuctionWithNewTransaction($btc_send_data, $auction, 'incoming');
                }
            }
        });

        $this->native_follower->handleOrphanedBlock(function($orphaned_block_id) {
            EventLog::logEvent('block.orphan', ['blockId' => $orphaned_block_id]);

            // get all auctions affected
            $entries = $this->blockchain_tx_directory->find(['blockId' => $orphaned_block_id]);
            $auction_ids = [];
            foreach($entries as $entry) {
                $auction_ids[$entry['auctionId']] = true;
            }

            // delete transactions
            $this->blockchain_tx_directory->deleteWhere(['blockId' => $orphaned_block_id]);

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

    //     "block_index" => 313360,
    //     "tx_index"    => 100000,
    //     "source"      => "1NFeBp9s5aQ1iZ26uWyiK2AYUXHxs7bFmB",
    //     "destination" => "1B7FpKyJ4LtcqfxqR2zUtquSupcPnLuZpk",
    //     "asset"       => "XCP",
    //     "status"      => "valid",
    //     "quantity"    => 490000000,
    //     "tx_hash"     => "278afe117b744fc9e23c0198e9555a129b3b72f974503e81d6fb5df4bc453688",

}

