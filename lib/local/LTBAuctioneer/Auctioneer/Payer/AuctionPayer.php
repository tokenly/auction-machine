<?php

namespace LTBAuctioneer\Auctioneer\Payer;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\EventLog\EventLog;
use Utipd\BitcoinAddressLib\BitcoinKeyUtils;
use Utipd\BitcoinPayer\BitcoinPayer;
/*
* AuctionPayer
*/
class AuctionPayer
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($auction_manager, $xchain_client, $payout_debug) {
        $this->auction_manager = $auction_manager;
        $this->xchain_client   = $xchain_client;
        $this->payout_debug    = $payout_debug;
    }

    public function payoutAuction($auction, $limited_manual_payouts_to_trigger=null) {
        try {
            $state = $auction['state'];
            foreach ($state['payouts'] as $payout) {
                $payout_hash = $this->hashPayout($payout);

                // skip payouts if there are manual payouts
                if ($limited_manual_payouts_to_trigger AND !isset($limited_manual_payouts_to_trigger[$payout_hash])) {
                    EventLog::logEvent('payout.skippingDueToManualPayoutsSet', ['auctionId' => $auction['id'], 'payoutHash' => $payout_hash]);
                    continue;
                }

                // skip payout if already sent
                if (isset($auction['payoutReceipts']) AND isset($auction['payoutReceipts'][$payout_hash])) {
                    $existing_receipt = $auction['payoutReceipts'][$payout_hash];
                    EventLog::logEvent('payout.alreadyProcessed', ['auctionId' => $auction['id'], 'payoutHash' => $payout_hash, 'receipt' => $existing_receipt]);
                    continue;
                }

                // do payout
                $payout_receipt = $this->doPayout($payout, $auction);

                // save the receipt
                $auction = $this->savePayoutReceipt($payout, $payout_receipt, $auction);

                EventLog::logEvent('payout.success', ['auctionId' => $auction['id'], 'receipt' => $payout_receipt, 'payoutHash' => $payout_hash]);
            }

            // mark as paidOut
            if (!$limited_manual_payouts_to_trigger) {
                $this->auction_manager->update($auction, [
                    'paidOut' => true,
                ]);
            }

            // done
            EventLog::logEvent('payout.complete', ['auctionId' => $auction['id']]);
            return $auction;

        } catch (Exception $e) {
            EventLog::logError('payout.failed', ['auctionId' => $auction['id']]);
            throw $e;
            
        }
//         try {
//             $private_key = $this->address_generator->WIFPrivateKey($auction['keyToken']);
//             $public_key = $this->address_generator->publicAddress($auction['keyToken']);
// #            Debug::trace("\$auction['auctionAddress']=".Debug::desc($auction['auctionAddress'])." \$public_key=".Debug::desc($public_key)." \$private_key=".Debug::desc($private_key)."",__FILE__,__LINE__,$this);
                
//             $state = $auction['state'];
//             foreach ($state['payouts'] as $payout) {
//                 $payout_hash = $this->hashPayout($payout);

//                 // skip payouts if there are manual payouts
//                 if ($limited_manual_payouts_to_trigger AND !isset($limited_manual_payouts_to_trigger[$payout_hash])) {
//                     EventLog::logEvent('payout.skippingDueToManualPayoutsSet', ['auctionId' => $auction['id'], 'payoutHash' => $payout_hash]);
//                     continue;
//                 }

// #                Debug::trace("\$payout=".json_encode($payout, 192)." hash: ".$this->hashPayout($payout)."\n isset: ".Debug::desc(isset($auction['payoutReceipts'][$this->hashPayout($payout)]))."",__FILE__,__LINE__,$this);
//                 if (isset($auction['payoutReceipts']) AND isset($auction['payoutReceipts'][$payout_hash])) {
//                     // this payout was already sent
//                     $existing_receipt = $auction['payoutReceipts'][$payout_hash];
//                     EventLog::logEvent('payout.alreadyProcessed', ['auctionId' => $auction['id'], 'payoutHash' => $payout_hash, 'receipt' => $existing_receipt]);
//                     continue;
//                 }

//                 // do payout
//                 $payout_receipt = $this->doPayout($payout, $auction, $private_key);

//                 // save the receipt
//                 $auction = $this->savePayoutReceipt($payout, $payout_receipt, $auction);

//                 EventLog::logEvent('payout.success', ['auctionId' => $auction['id'], 'receipt' => $payout_receipt, 'payoutHash' => $payout_hash]);
//             }

//             // mark as paidOut
//             if (!$limited_manual_payouts_to_trigger) {
//                 $this->auction_manager->update($auction, [
//                     'paidOut' => true,
//                 ]);
//             }

//             // done
//             EventLog::logEvent('payout.complete', ['auctionId' => $auction['id']]);
//             return $auction;

//         } catch (Exception $e) {
//             EventLog::logError('payout.failed', ['auctionId' => $auction['id']]);
//             throw $e;
            
//         }
    }

    ////////////////////////////////////////////////////////////////////////

    protected function doPayout($payout, $auction) {

#        Debug::trace("doPayout called",__FILE__,__LINE__,$this);
        try {
            if ($payout['sweep']) {
                // sweep the remaining BTC
                list($transaction_id, $amount_sent) = $this->sweepBTC($payout, $auction, $auction['auctionAddressUuid']);
                EventLog::logEvent('payout.btcSweep', ['auctionId' => $auction['id'], 'tx' => $transaction_id, 'amount' => $amount_sent]);
            } else {
                // send a counterparty token
                $transaction_id = $this->sendXCPToken($payout, $auction, $auction['auctionAddressUuid']);
                $amount_sent = $payout['amount'];
                EventLog::logEvent('payout.sendToken', ['auctionId' => $auction['id'], 'tx' => $transaction_id, 'amount' => $payout['amount'], 'token' => $payout['token']]);
            }

            // build the receipt
            return [
                'transactionId' => $transaction_id,
                'timestamp'     => time(),
                'payout'        => (array)$payout,
                'amountSent'    => $amount_sent,
            ];
        } catch (Exception $e) {
            EventLog::logError('payout.error', ['payout' => $payout, 'auctionId' => $auction['id'], 'error' => $e]);
            throw $e;
        }
    }

    protected function savePayoutReceipt($payout, $payout_receipt, $auction) {
        $payout_receipts = isset($auction['payoutReceipts']) ? $auction['payoutReceipts'] : [];
        $payout_receipts[$this->hashPayout($payout)] = $payout_receipt;

        $this->auction_manager->update($auction, [
            'payoutReceipts' => $payout_receipts,
        ]);

        return $auction->reload();
    }

    protected function hashPayout($payout) {
        return md5(json_encode((array)$payout));
    }

    protected function sendXCPToken($payout, $auction, $payment_address_id) {
        // use xchain to send and sign the raw transaction
        $sweep = false;
        $quantity = CurrencyUtil::satoshisToNumber($payout['amount']);
        $details = $this->xchain_client->send($payment_address_id, $payout['address'], $quantity, $payout['token'], $sweep);
        return $details['txid'];
    }

    protected function sweepBTC($payout, $auction, $payment_address_id) {
        $sweep = true;
        $details = $this->xchain_client->send($payment_address_id, $auction['platformAddress'], null, 'BTC', $sweep);
        return [$details['txid'], $details['quantity']];
    }


//     protected function buildPayoutQuantity($payout) {
//         return intval($this->isDivisible($payout['token']) ? $payout['amount'] : CurrencyUtil::satoshisToNumber($payout['amount']));
//     }

//     protected function isDivisible($token) {
//         $assets = $this->xcpd_client->get_asset_info(['assets' => [$token]]);
// #        Debug::trace("\$assets=",$assets,__FILE__,__LINE__,$this);
//         return $assets[0]['divisible'];
//     }



}

