<?php

namespace LTBAuctioneer\Auctioneer\Publisher;

use LTBAuctioneer\Debug\Debug;
use Exception;

/*
* AuctionDataPublisher
*/
class AuctionDataPublisher
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($pusher, $block_directory) {
        $this->pusher           = $pusher;
        $this->block_directory = $block_directory;
    }

    public function publishAuctionState($auction, $last_block_seen=null) {
        $public_auction_data = [
            'slug'            => $auction['slug'],
            'name'            => $auction['name'],
            'description'     => $auction['description'],
            'startDate'       => $auction['startDate'],
            'endDate'         => $auction['endDate'],
            'minStartingBid'  => $auction['minStartingBid'],
            'bidTokenType'    => $auction['bidTokenType'],
            'minBidIncrement' => $auction['minBidIncrement'],
            'auctionAddress'  => $auction['auctionAddress'],
            'payoutReceipts'  => $this->sanitizePayoutReceipts($auction['payoutReceipts']),
        ];

        $private_auction_data = array_merge($public_auction_data, [
            'refId'               => $auction['refId'],
            'bidTokenFeeRequired' => $auction['bidTokenFeeRequired'],
            'btcFeeRequired'      => $auction['btcFeeRequired'],
            'prizeTokensRequired' => $auction['prizeTokensRequired'],
        ]);

        $state = (array)$auction['state'];

        $public_state_data = [
            'timePhase'              => $state['timePhase'],
            'active'                 => $state['active'],
            'bids'                   => $state['bidsAndAccounts'],
            'bounty'                 => $state['bounty'],
            'blockId'                => $state['blockId'],
            'hasMempoolTransactions' => $state['hasMempoolTransactions'],
        ];
        $private_state_data = array_merge($public_state_data, [
            'btcFeeSatisfied'      => $state['btcFeeSatisfied'],
            'btcFeeApplied'        => $state['btcFeeApplied'],
            'bidTokenFeeSatisfied' => $state['bidTokenFeeSatisfied'],
            'bidTokenFeeApplied'   => $state['bidTokenFeeApplied'],
            'prizeTokensSatisfied' => $state['prizeTokensSatisfied'],
            'prizeTokensApplied'   => $state['prizeTokensApplied'],
            'logs'                 => isset($state['logs']) ? $state['logs'] : [],
            // 'accounts'          => $state['accounts'],
        ]);

        unset($public_state_data['logs']);

        // meta
        if ($last_block_seen === null) {
            // $last_block_seen = $this->xcpd_follower->getLastProcessedBlock();
            $last_block_seen = -1;

            $block = $this->block_directory->getBlockModelAtBestHeight();
            if ($block) { $last_block_seen = $block['blockId']; }
        }
        $meta = [
            'lastBlockSeen' => $last_block_seen,
        ];

        // public
        $public_data = ['auction' => $public_auction_data, 'state' => $public_state_data, 'meta' => $meta];
        Debug::trace("sending data to ".'auction_'.$auction['slug'],__FILE__,__LINE__,$this);
        $this->pusher->send('/auction_'.$auction['slug'], $public_data);

        // private
        $private_data = ['auction' => $private_auction_data, 'state' => $private_state_data, 'meta' => $meta];
        $this->pusher->send('/auction_'.$auction['refId'], $private_data);

    }

    ////////////////////////////////////////////////////////////////////////

    protected function sanitizePayoutReceipts($payout_receipts) {
        $sanitized_receipts = [];
        foreach($payout_receipts as $receipt) {
            $sanitized_receipt['amountSent'] = isset($receipt['amountSent']) ? $receipt['amountSent'] : $receipt['payout']['amount'];
            $sanitized_receipt['transactionId'] = $receipt['transactionId'];
            $sanitized_receipt['payout'] = [
                'amount' => $receipt['payout']['amount'],
                'token' => $receipt['payout']['token'],
                'address' => $receipt['payout']['address'],
            ];
            $sanitized_receipts[] = $sanitized_receipt;
        }
        return $sanitized_receipts;
    }
}

// <span data-receipt-field="amountSent" data-currency>{{ receipt.amountSent is defined ? receipt.amountSent|to_currency : receipt.payout.amount|to_currency }}</span>
// <span <span data-payout-field="token">{{ receipt.payout.token }}</span>
// to 
// <span class="address addressSmall" data-payout-field="address">{{ receipt.payout.address }}</span>.  
// <span class="transaction-link right"><a href="https://blockchain.info/tx/{{receipt.transactionId}}" target="_blank" data-receipt-field="transactionLink">View Transaction <i class="fa fa-external-link"></i></a></span>

