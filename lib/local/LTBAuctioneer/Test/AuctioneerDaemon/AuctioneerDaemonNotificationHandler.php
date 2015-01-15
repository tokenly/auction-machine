<?php

namespace LTBAuctioneer\Test\AuctioneerDaemon;


use Exception;
use LTBAuctioneer\Auctioneer\AuctioneerDaemon;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;

/*
* AuctioneerDaemonNotificationHandler
*/
class AuctioneerDaemonNotificationHandler
{

    protected $mock_tx_index = 90000;
    protected $mock_native_tx_index = 1;
    protected $last_processed_native_block = 6000;

    public function __construct($test_case, $app) {
        $this->app = $app;
        $this->test_case = $test_case;
    }

    ////////////////////////////////////////////////////////////////////////

    public function setupDaemon() {
        if (!isset($this->daemon)) {
            $app = $this->app;
            $this->daemon = new AuctioneerDaemon($app['simpleDaemon'], $app['auction.manager'], $app['auction.updater'], $this->getAuctionPayer(), $app['auction.publisher'], $app['directory']('BlockchainTransaction'), $app['directory']('Block'));
            $this->daemon->setup();
        }
        return $this->daemon;
    }

    public function sendMockCounterpartyTransaction($auction, $send_data_overrides=[]) {
        if (!isset($this->daemon)) { $this->setupDaemon(); }

        // insert a sample counterparty transaction
        $send_data['tx_index']    = $this->mock_tx_index++;
        $send_data['block_index'] = '6000';
        $send_data['source']      = 'sender01';
        $send_data['destination'] = $auction['auctionAddress'];
        $send_data['asset']       = 'LTBCOIN';
        $send_data['quantity']    = CurrencyUtil::numberToSatoshis(43);
        $send_data['status']      = 'valid';
        $send_data['tx_hash']     = md5('myhash'.$this->mock_tx_index);
        $send_data['assetInfo']   = ['divisible' => true,];
        $send_data['blockSeq']    = 1;
        $send_data['timestamp']   = time();

        $send_data = array_merge($send_data, $send_data_overrides);

        $is_mempool = false;
        $confirmed_block_hash = str_pad($send_data['block_index'], 64, '0', STR_PAD_LEFT);
        $this->daemon->handleNewXCPSend($send_data, $is_mempool, $confirmed_block_hash);

        return $send_data;
    }

    public function sendMockCounterpartyMempoolTransaction($auction, $send_data_overrides=[]) {
        if (!isset($this->daemon)) { $this->setupDaemon(); }

        // insert a sample counterparty transaction
        $send_data['source']      = 'msender01';
        $send_data['destination'] = $auction['auctionAddress'];
        $send_data['asset']       = 'LTBCOIN';
        $send_data['quantity']    = CurrencyUtil::numberToSatoshis(43);
        $send_data['tx_hash']     = 'mmyhash';
        $send_data['assetInfo']   = ['divisible' => true,];
        $send_data['timestamp']   = time();

        $send_data = array_merge($send_data, $send_data_overrides);

        $is_mempool = true;
        $block_height = $this->last_processed_native_block;
        $this->daemon->handleNewXCPSend($send_data, $is_mempool, null);

        return $send_data;
    }

    public function processNewCounterpartyBlock($block_id, $block_hash=null) {
        if ($block_hash === null) { $block_hash = str_pad($block_id, 64, '0', STR_PAD_LEFT); }
        if (!isset($this->daemon)) { $this->setupDaemon(); }
        $this->daemon->handleNewBlock($block_id, $block_hash);
    }

    public function processMultipleNativeBlocks($start_block_id, $end_block_id, $block_hash_prefix='block') {
        for ($block_id=$start_block_id; $block_id <= $end_block_id; $block_id++) { 
            // $this->processNativeBlock($block_id, $block_hash_prefix.$block_id);
            $this->processNativeBlock($block_id);
        }
    }

    public function processNativeBlock($block_id, $block_hash=null) {
        if ($block_hash === null) { $block_hash = str_pad($block_id, 64, '0', STR_PAD_LEFT); }
        if (!isset($this->daemon)) { $this->setupDaemon(); }

        $this->last_processed_native_block = $block_id;
        $this->daemon->handleNewBlock($block_id, $block_hash);
    }


    public function sendMockNativeTransaction($auction, $info=[], $current_block_height=null) {
        if (!isset($this->daemon)) { $this->setupDaemon(); }

        $is_mempool = isset($info['is_mempool']) ? $info['is_mempool'] : false;

            // a new transaction
            // txid: cc91db2f18b908903cb7c7a4474695016e12afd816f66a209e80b7511b29bba9
            // outputs:
            //     - amount: 100000
            //       address: "1aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

        $transaction = [
            'txid' => isset($info['txid']) ? $info['txid'] : md5($this->mock_native_tx_index++),
            'outputs' => [
                [
                    'amount' => CurrencyUtil::numberToSatoshis(isset($info['amount']) ? $info['amount'] : 0.001),
                    'address' => $auction['auctionAddress'],
                ]
            ]
        ];

        if ($is_mempool) {
            $block_height = ($current_block_height === null ? $this->last_processed_native_block : $current_block_height);
        } else {
            $block_height = isset($info['blockId']) ? $info['blockId'] : 6000;
        }

        $confirmed_block_hash = str_pad($block_height, 64, '0', STR_PAD_LEFT);
        $block_seq = 1;
        $timestamp = time();
        $this->daemon->handleNewBTCTransaction($transaction, $is_mempool, $confirmed_block_hash, $block_seq, $block_height, $timestamp);

        return $transaction;
    }


    public function orphanBlock($block_id) {
        if (!isset($this->daemon)) { $this->setupDaemon(); }

        $this->daemon->handleOrphanedBlock($block_id);
    }

    public function getLastProcessedBlockHeight() {
        $block_dir = $this->app['directory']('Block');
        $block = $block_dir->getBlockModelAtBestHeight();
        return $block['blockId'];
    }

    ////////////////////////////////////////////////////////////////////////





    protected function getAuctionPayer() {
        if (!isset($this->auction_payer)) {
            $builder = $this->test_case->getMockBuilder('\LTBAuctioneer\Auctioneer\Payer\AuctionPayer');
            $builder->setMethods(['doPayout']);
            $builder->setConstructorArgs([$this->app['auction.manager'], null, null, null, $this->app['bitcoin.addressGenerator'], $this->app['config']['xcp.payout'], null, $this->app['config']['payouts.debug']]);
            $payer = $builder->getMock();

            // doPayout()
            $payer->method('doPayout')->will($this->test_case->returnCallback(function($payout, $auction) {
                return [
                    'transactionId' => 'fooid',
                    'timestamp'     => time(),
                    'payout'        => (array)$payout,
                ];
            }));
            $this->auction_payer = $payer;
        }

        return $this->auction_payer;
    }
}

