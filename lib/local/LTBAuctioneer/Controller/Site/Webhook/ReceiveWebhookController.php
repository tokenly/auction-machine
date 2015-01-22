<?php

namespace LTBAuctioneer\Controller\Site\Webhook;

use Exception;
use LTBAuctioneer\Controller\Site\Base\BaseSiteController;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\EventLog\EventLog;
use Symfony\Component\HttpFoundation\Request;
use Tokenly\XChainClient\Exception\AuthorizationException;

/*
* ReceiveWebhookController
*/
class ReceiveWebhookController extends BaseSiteController
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($app, $webhook_receiver, $auctioneer_daemon) {
        parent::__construct($app);

        $this->webhook_receiver  = $webhook_receiver;
        $this->auctioneer_daemon = $auctioneer_daemon;
    }


    public function receive(Request $request) {
        try {
            $data = $this->webhook_receiver->validateAndParseWebhookNotificationFromRequest($request);
            $payload = $data['payload'];
            Debug::trace("\$payload['event']=".$payload['event'],__FILE__,__LINE__,$this);
            // Debug::trace("\$payload=".json_encode($payload, 192),__FILE__,__LINE__,$this);
            switch ($payload['event']) {
                case 'block':
                    // new block event
                    $this->auctioneer_daemon->handleNewBlock($payload['height'], $payload['hash'], strtotime($payload['time']));
                    break;

                case 'receive':
                    // new receive event
                    $is_mempool = !$payload['confirmed'];
                    $confirmed_block_hash = $is_mempool ? null : $payload['bitcoinTx']['blockhash'];
                    if ($payload['network'] == 'counterparty') {
                        $this->auctioneer_daemon->handleNewXCPSend($this->adaptXCPTransactionForAuctioneerDaemon($payload), $is_mempool, $confirmed_block_hash);
                    } else {
                        $block_seq = $payload['blockSeq'];
                        $block_height = $payload['confirmed'] ? $payload['bitcoinTx']['blockheight'] : 0;

                        $timestamp = strlen($payload['confirmationTime']) ? strtotime($payload['confirmationTime']) : time();
                        $this->auctioneer_daemon->handleNewBTCTransaction($this->adaptBTCTransactionForAuctioneerDaemon($payload), $is_mempool, $confirmed_block_hash, $block_seq, $block_height, $timestamp);
                    }
                    break;

                default:
                    throw new Exception("Unknown event type: {$payload['event']}", 1);
            }
                
        } catch (AuthorizationException $e) {
            EventLog::logError('webhook.error.auth', $e);
            throw $e;
            
        } catch (Exception $e) {
            EventLog::logError('webhook.error', $e);
            throw $e;

        }

        // handle block event
            // 'event'             => 'block',
            // 'notificationId'    => null,

            // 'hash'              => $block_event['hash'],
            // 'height'            => $block_event['height'],
            // 'previousblockhash' => $block_event['previousblockhash'],
            // 'time'              => $this->getISO8601Timestamp($block_event['time']),

        return 'processed';
    }

    ////////////////////////////////////////////////////////////////////////


    protected function adaptBTCTransactionForAuctioneerDaemon($payload) {
        $out = [
            'txid' => $payload['txid'],
            'outputs' => [
                [
                    'amount'  => $payload['quantitySat'],
                    'address' => $payload['destinations'][0],
                ]
            ],
        ];
        return $out;
    }

    protected function adaptXCPTransactionForAuctioneerDaemon($payload) {
        $send_data = [];
        $send_data['block_index'] = $payload['confirmed'] ? $payload['bitcoinTx']['blockheight'] : 0;
        $send_data['source']      = $payload['sources'][0];
        $send_data['destination'] = $payload['destinations'][0];
        $send_data['asset']       = $payload['asset'];
        $send_data['quantity']    = $payload['quantitySat'];
        $send_data['tx_hash']     = $payload['txid'];
        $send_data['tx_index']    = $payload['txid'];
        $send_data['blockSeq']    = $payload['blockSeq'];
        $send_data['timestamp']   = strlen($payload['confirmationTime']) ? strtotime($payload['confirmationTime']) : time();

        return $send_data;
    }
}

