<?php

namespace LTBAuctioneer\Auctioneer\Updater;

use LTBAuctioneer\Debug\Debug;
use Exception;

/*
* AuctionUpdater
*/
class AuctionUpdater
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($auction_manager, $auction_state_builder, $blockchain_tx_directory) {
        $this->auction_manager = $auction_manager;
        $this->auction_state_builder = $auction_state_builder;
        $this->blockchain_tx_directory = $blockchain_tx_directory;
    }

    public function updateAuctionState($auction, $block_height) {
        // rebuild the auction state
        $transactions = $this->blockchain_tx_directory->findByAuctionId($auction['id']);
        $now = isset($GLOBALS['_TEST_NOW']) ? $GLOBALS['_TEST_NOW'] : time();
       // Debug::trace("\$now=".Debug::desc(date("Y-m-d H:i:s", $now))."",__FILE__,__LINE__,$this);
        $meta_info = ['blockHeight' => $block_height, 'now' => $now];
        $auction_state_vars = $this->auction_state_builder->buildAuctionStateFromTransactions($transactions, $auction, $meta_info);
        $this->auction_manager->update($auction, [
            'state'     => $auction_state_vars,
            'timePhase' => $auction_state_vars['timePhase'],
        ]);

        // reload
        return $auction->reload();
    }


    ////////////////////////////////////////////////////////////////////////

}

