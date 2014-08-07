<?php

namespace LTBAuctioneer\Test\AuctioneerDaemon;


use Exception;
use LTBAuctioneer\Auctioneer\AuctioneerDaemon;
use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;

/*
* AuctioneerDaemonHandler
*/
class AuctioneerDaemonHandler
{

    protected $mock_tx_index = 90000;
    protected $mock_native_tx_index = 1;
    protected $last_processed_native_block = 6000;

    public function __construct($test_case, $app) {
        $this->app = $app;
        $this->test_case = $test_case;
    }

    ////////////////////////////////////////////////////////////////////////

    public function getNativeFollower() {
        if (!isset($this->native_follower)) {
            $this->native_follower = $this->test_case->getMockBuilder('\Utipd\NativeFollower\Follower')->disableOriginalConstructor()->getMock();
            $this->initNativeHandleBlockFunction();
            $this->initNativeTransactionFunction();
            $this->initNativeOrphanBlockFunction();
        }
        return $this->native_follower;
        return $this->native_follower;
    }
    public function getCounterpartyFollower() {
        if (!isset($this->counterparty_follower)) {
            $this->counterparty_follower = $this->test_case->getMockBuilder('\Utipd\CounterpartyFollower\Follower')->disableOriginalConstructor()->getMock();
            $this->initXCPFollowerFunction();
            $this->initXCPOtherFunctions();
        }
        return $this->counterparty_follower;
    }

    public function setupDaemon() {
        if (!isset($this->daemon)) {
            $app = $this->app;
            $this->daemon = new AuctioneerDaemon($this->getCounterpartyFollower(), $this->getNativeFollower(), $app['simpleDaemon'], $app['auction.manager'], $app['auction.updater'], $this->getAuctionPayer(), $app['auction.publisher'], $app['directory']('BlockchainTransaction'));
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
        $send_data['tx_hash']     = 'myhash';
        $send_data['assetInfo']   = ['divisible' => true,];

        $send_data = array_merge($send_data, $send_data_overrides);
#        Debug::trace("\$send_data=".Debug::desc($send_data)."",__FILE__,__LINE__,$this);

        $xcpd_follower_function = $this->xcpd_follower_function;
        $xcpd_follower_function($send_data, $send_data['block_index']);

        return $send_data;
    }

    public function sendMockNativeTransaction($auction, $info=[]) {
        if (!isset($this->daemon)) { $this->setupDaemon(); }

            // a new transaction
            // txid: cc91db2f18b908903cb7c7a4474695016e12afd816f66a209e80b7511b29bba9
            // outputs:
            //     - amount: 100000
            //       address: "1aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

        $transaction = [
            'txid' => md5($this->mock_native_tx_index++),
            'outputs' => [
                [
                    'amount' => CurrencyUtil::numberToSatoshis(isset($info['amount']) ? $info['amount'] : 0.001),
                    'address' => $auction['auctionAddress'],
                ]
            ]
        ];

        $block_index = isset($info['blockId']) ? $info['blockId'] : 6000;

        $native_transaction_function = $this->native_transaction_function;
        $native_transaction_function($transaction, $block_index);

        return $transaction;
    }


    public function processNativeBlock($block_id) {
        if (!isset($this->daemon)) { $this->setupDaemon(); }

        $this->last_processed_native_block = $block_id;

        $native_handle_block_function = $this->native_handle_block_function;
        $native_handle_block_function($block_id);
    }

    public function orphanBlock($block_id) {
        if (!isset($this->daemon)) { $this->setupDaemon(); }

        $native_orphaned_block_function = $this->native_orphaned_block_function;
        $native_orphaned_block_function($block_id);
    }


    ////////////////////////////////////////////////////////////////////////

    protected function initXCPFollowerFunction() {
        $this->counterparty_follower->method('handleNewSend')->will($this->test_case->returnCallback(function($f)  {
#            Debug::trace("handleNewSend f=".Debug::desc($f)."",__FILE__,__LINE__,$this);
            $this->xcpd_follower_function = $f;
            return;
        }));
    }
    protected function initXCPOtherFunctions() {
        $this->counterparty_follower->method('getLastProcessedBlock')->will($this->test_case->returnCallback(function() {
            return $this->last_processed_native_block;
        }));
    }



    protected function initNativeHandleBlockFunction() {
        $this->native_follower->method('handleNewBlock')->will($this->test_case->returnCallback(function($f) {
            $this->native_handle_block_function = $f;
            return;
        }));
    }
    protected function initNativeOrphanBlockFunction() {
        $this->native_follower->method('handleOrphanedBlock')->will($this->test_case->returnCallback(function($f) {
            $this->native_orphaned_block_function = $f;
            return;
        }));
    }
    protected function initNativeTransactionFunction() {
            // $follower->handleNewTransaction(function($transaction, $block_id) { });
        $this->native_follower->method('handleNewTransaction')->will($this->test_case->returnCallback(function($f) {
            $this->native_transaction_function = $f;
            return;
        }));
    }



    protected function getAuctionPayer() {
        if (!isset($this->auction_payer)) {
            $builder = $this->test_case->getMockBuilder('\LTBAuctioneer\Auctioneer\Payer\AuctionPayer');
            $builder->setMethods(['doPayout']);
            $builder->setConstructorArgs([$this->app['auction.manager'], null, null, $this->app['bitcoin.addressGenerator'], $this->app['config']['xcp.payout'], $this->app['config']['payouts.debug']]);
            $payer = $builder->getMock();

            // doPayout()
            $payer->method('doPayout')->will($this->test_case->returnCallback(function($payout, $auction, $private_key) {
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

