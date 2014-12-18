<?php

// namespace LTBAuctioneer\Test\AuctioneerDaemon;


// use Exception;
// use LTBAuctioneer\Auctioneer\AuctioneerDaemon;
// use Utipd\CurrencyLib\CurrencyUtil;
// use LTBAuctioneer\Debug\Debug;

// /*
// * AuctioneerDaemonHandler
// */
// class AuctioneerDaemonHandler
// {

//     protected $mock_tx_index = 90000;
//     protected $mock_native_tx_index = 1;
//     protected $last_processed_native_block = 6000;

//     protected $xcpd_handle_block_function = null;
//     protected $xcpd_handle_new_send_function = null;
//     protected $native_handle_block_function = null;
//     protected $native_orphaned_block_function = null;
//     protected $native_transaction_function = null;

//     public function __construct($test_case, $app) {
//         throw new Exception("AuctioneerDaemonHandler is deprecated", 1);
        
//         $this->app = $app;
//         $this->test_case = $test_case;
//     }

//     ////////////////////////////////////////////////////////////////////////

//     public function getNativeFollower() {
//         if (!isset($this->native_follower)) {
//             $this->native_follower = $this->test_case->getMockBuilder('\Utipd\NativeFollower\Follower')->disableOriginalConstructor()->getMock();
//             $this->initNativeHandleBlockFunction();
//             $this->initNativeTransactionFunction();
//             $this->initNativeOrphanBlockFunction();
//         }
//         return $this->native_follower;
//     }
//     public function getCounterpartyFollower() {
//         if (!isset($this->counterparty_follower)) {
//             $pdo = $this->app['mysql.client'];
//             $pdo->query("use `".$this->app['mysql.xcpd.databaseName']."`");

//             $this->counterparty_follower = $this->test_case->getMockBuilder('\Utipd\CounterpartyFollower\Follower')
//                 ->setConstructorArgs([$this->app['xcpd.client'], $pdo])
//                 ->setMethods(['handleNewSend','handleNewBlock','getLastProcessedBlock','processOneNewBlock',])
//                 ->getMock();
//             $this->initXCPFollowerFunctions();
//             $this->initXCPOtherFunctions();
//         }
//         return $this->counterparty_follower;
//     }

//     public function setupDaemon() {
//         if (!isset($this->daemon)) {
//             $app = $this->app;
//             $this->daemon = new AuctioneerDaemon($this->getCounterpartyFollower(), $this->getNativeFollower(), $app['simpleDaemon'], $app['auction.manager'], $app['auction.updater'], $this->getAuctionPayer(), $app['auction.publisher'], $app['directory']('BlockchainTransaction'));
//             $this->daemon->setup();
//         }
//         return $this->daemon;
//     }

//     public function sendMockCounterpartyTransaction($auction, $send_data_overrides=[]) {
//         if (!isset($this->daemon)) { $this->setupDaemon(); }

//         // insert a sample counterparty transaction
//         $send_data['tx_index']    = $this->mock_tx_index++;
//         $send_data['block_index'] = '6000';
//         $send_data['source']      = 'sender01';
//         $send_data['destination'] = $auction['auctionAddress'];
//         $send_data['asset']       = 'LTBCOIN';
//         $send_data['quantity']    = CurrencyUtil::numberToSatoshis(43);
//         $send_data['status']      = 'valid';
//         $send_data['tx_hash']     = md5('myhash'.$this->mock_tx_index);
//         $send_data['assetInfo']   = ['divisible' => true,];

//         $send_data = array_merge($send_data, $send_data_overrides);
// #        Debug::trace("\$send_data=".Debug::desc($send_data)."",__FILE__,__LINE__,$this);

//         $xcpd_handle_new_send_function = $this->xcpd_handle_new_send_function;

//         $xcpd_handle_new_send_function($send_data, $send_data['block_index'], $is_mempool=false);

//         return $send_data;
//     }

//     public function sendMockCounterpartyMempoolTransaction($auction, $send_data_overrides=[]) {
//         if (!isset($this->daemon)) { $this->setupDaemon(); }

//         // {
//         //     "source": "13UxmTs2Ad2CpMGvLJu3tSV2YVuiNcVkvn",
//         //     "destination": "1KbbyhT3dPAMEGfVx9siDtKATLpk9vjQkW",
//         //     "asset": "SLVAGOAAAAAAA",
//         //     "quantity": 10,
//         //     "tx_hash": "c324e62d0ba17f42a774b9b28114217c777914a4b6dd0d41811217cffb8c40a6"
//         // }

//         // insert a sample counterparty transaction
//         $send_data['source']      = 'msender01';
//         $send_data['destination'] = $auction['auctionAddress'];
//         $send_data['asset']       = 'LTBCOIN';
//         $send_data['quantity']    = CurrencyUtil::numberToSatoshis(43);
//         $send_data['tx_hash']     = 'mmyhash';
//         $send_data['assetInfo']   = ['divisible' => true,];

//         $send_data = array_merge($send_data, $send_data_overrides);

//         $xcpd_handle_new_send_function = $this->xcpd_handle_new_send_function;

//         $xcpd_handle_new_send_function($send_data, null, $is_mempool=true);

//         return $send_data;
//     }

//     public function processNewCounterpartyBlock($block_id) {
//         $xcpd_handle_block_function = $this->xcpd_handle_block_function;
//         $xcpd_handle_block_function($block_id);
//     }


//     public function sendMockNativeTransaction($auction, $info=[]) {
//         if (!isset($this->daemon)) { $this->setupDaemon(); }

//         $is_mempool = isset($info['is_mempool']) ? $info['is_mempool'] : false;

//             // a new transaction
//             // txid: cc91db2f18b908903cb7c7a4474695016e12afd816f66a209e80b7511b29bba9
//             // outputs:
//             //     - amount: 100000
//             //       address: "1aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

//         $transaction = [
//             'txid' => isset($info['txid']) ? $info['txid'] : md5($this->mock_native_tx_index++),
//             'outputs' => [
//                 [
//                     'amount' => CurrencyUtil::numberToSatoshis(isset($info['amount']) ? $info['amount'] : 0.001),
//                     'address' => $auction['auctionAddress'],
//                 ]
//             ]
//         ];

//         if ($is_mempool) {
//             $block_index = null;
//         } else {
//             $block_index = isset($info['blockId']) ? $info['blockId'] : 6000;
//         }

//         $native_transaction_function = $this->native_transaction_function;
//         $native_transaction_function($transaction, $block_index, $is_mempool);

//         return $transaction;
//     }


//     public function processNativeBlock($block_id) {
//         if (!isset($this->daemon)) { $this->setupDaemon(); }

//         $this->last_processed_native_block = $block_id;

//         $native_handle_block_function = $this->native_handle_block_function;
//         $native_handle_block_function($block_id);
//     }

//     public function orphanBlock($block_id) {
//         if (!isset($this->daemon)) { $this->setupDaemon(); }

//         $native_orphaned_block_function = $this->native_orphaned_block_function;
//         $native_orphaned_block_function($block_id);
//     }


//     ////////////////////////////////////////////////////////////////////////

//     protected function initXCPFollowerFunctions() {
//         $this->counterparty_follower->method('handleNewSend')->will($this->test_case->returnCallback(function($f)  {
// #            Debug::trace("handleNewSend f=".Debug::desc($f)."",__FILE__,__LINE__,$this);
//             $this->xcpd_handle_new_send_function = $f;
//             return;
//         }));
//         $this->counterparty_follower->method('handleNewBlock')->will($this->test_case->returnCallback(function($f)  {
// #            Debug::trace("handleNewSend f=".Debug::desc($f)."",__FILE__,__LINE__,$this);
//             $this->xcpd_handle_block_function = $f;
//             return;
//         }));
//     }
//     protected function initXCPOtherFunctions() {
//         $this->counterparty_follower->method('getLastProcessedBlock')->will($this->test_case->returnCallback(function() {
//             return $this->last_processed_native_block;
//         }));
//     }



//     protected function initNativeHandleBlockFunction() {
//         $this->native_follower->method('handleNewBlock')->will($this->test_case->returnCallback(function($f) {
//             $this->native_handle_block_function = $f;
//             return;
//         }));
//     }
//     protected function initNativeOrphanBlockFunction() {
//         $this->native_follower->method('handleOrphanedBlock')->will($this->test_case->returnCallback(function($f) {
//             $this->native_orphaned_block_function = $f;
//             return;
//         }));
//     }
//     protected function initNativeTransactionFunction() {
//             // $follower->handleNewTransaction(function($transaction, $block_id) { });
//         $this->native_follower->method('handleNewTransaction')->will($this->test_case->returnCallback(function($f) {
//             $this->native_transaction_function = $f;
//             return;
//         }));
//     }



//     protected function getAuctionPayer() {
//         if (!isset($this->auction_payer)) {
//             $builder = $this->test_case->getMockBuilder('\LTBAuctioneer\Auctioneer\Payer\AuctionPayer');
//             $builder->setMethods(['doPayout']);
//             $builder->setConstructorArgs([$this->app['auction.manager'], null, null, null, $this->app['bitcoin.addressGenerator'], $this->app['config']['xcp.payout'], null, $this->app['config']['payouts.debug']]);
//             $payer = $builder->getMock();

//             // doPayout()
//             $payer->method('doPayout')->will($this->test_case->returnCallback(function($payout, $auction, $private_key) {
//                 return [
//                     'transactionId' => 'fooid',
//                     'timestamp'     => time(),
//                     'payout'        => (array)$payout,
//                 ];
//             }));
//             $this->auction_payer = $payer;
//         }

//         return $this->auction_payer;
//     }
// }

