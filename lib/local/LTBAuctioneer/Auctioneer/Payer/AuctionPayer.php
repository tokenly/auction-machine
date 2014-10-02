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

    var $USE_LOCAL_SENDER = true;

    ////////////////////////////////////////////////////////////////////////

    public function __construct($auction_manager, $xcpd_sender, $xcpd_client, $native_client, $address_generator, $payouts_config, $wallet_passphrase, $payout_debug) {
        $this->auction_manager   = $auction_manager;
        $this->xcpd_sender       = $xcpd_sender;
        $this->xcpd_client       = $xcpd_client;
        $this->native_client     = $native_client;
        $this->address_generator = $address_generator;
        $this->payouts_config    = $payouts_config;
        $this->wallet_passphrase = $wallet_passphrase;
        $this->payout_debug      = $payout_debug;
    }

    public function payoutAuction($auction) {
        try {
            $private_key = $this->address_generator->WIFPrivateKey($auction['keyToken']);
            $public_key = $this->address_generator->publicAddress($auction['keyToken']);
#            Debug::trace("\$auction['auctionAddress']=".Debug::desc($auction['auctionAddress'])." \$public_key=".Debug::desc($public_key)." \$private_key=".Debug::desc($private_key)."",__FILE__,__LINE__,$this);
                
            $state = $auction['state'];
            foreach ($state['payouts'] as $payout) {
#                Debug::trace("\$payout=".json_encode($payout, 192)." hash: ".$this->hashPayout($payout)."\n isset: ".Debug::desc(isset($auction['payoutReceipts'][$this->hashPayout($payout)]))."",__FILE__,__LINE__,$this);
                if (isset($auction['payoutReceipts']) AND isset($auction['payoutReceipts'][$this->hashPayout($payout)])) {
                    // this payout was already sent
                    $existing_receipt = $auction['payoutReceipts'][$this->hashPayout($payout)];
                    EventLog::logEvent('payout.alreadyProcessed', ['auctionId' => $auction['id'], 'receipt' => $existing_receipt]);
                    continue;
                }

                // do payout
                $payout_receipt = $this->doPayout($payout, $auction, $private_key);

                // save the receipt
                $auction = $this->savePayoutReceipt($payout, $payout_receipt, $auction);

                EventLog::logEvent('payout.success', ['auctionId' => $auction['id'], 'receipt' => $payout_receipt]);
            }

            // mark as paidOut
            $this->auction_manager->update($auction, [
                'paidOut' => true,
            ]);

            // done
            EventLog::logEvent('payout.complete', ['auctionId' => $auction['id']]);
            return $auction;

        } catch (Exception $e) {
            EventLog::logError('payout.failed', ['auctionId' => $auction['id']]);
            throw $e;
            
        }
    }

    ////////////////////////////////////////////////////////////////////////

    protected function doPayout($payout, $auction, $private_key) {
#        Debug::trace("doPayout called",__FILE__,__LINE__,$this);
        try {
            if ($payout['sweep']) {
                // sweep the remaining BTC
                list($transaction_id, $amount_sent) = $this->sweepBTC($payout, $auction, $private_key);
                EventLog::logEvent('payout.btcSweep', ['auctionId' => $auction['id'], 'tx' => $transaction_id, 'amount' => $amount_sent]);
            } else {
                // send a counterparty token
                $transaction_id = $this->sendXCPToken($payout, $auction, $private_key);
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

    protected function sendXCPToken($payout, $auction, $private_key) {
        if ($this->USE_LOCAL_SENDER AND !$this->payout_debug) {
            // use the CounterpartySender to send and sign the raw transaction
            //   This doesn't use the bitcoin wallet to pay the BTC fees, but generates them using the private key for the address
            $transaction_id = $this->sendXCPTokenWithSender($payout, $auction, $private_key);
        } else {
            // unlock the wallet with the passphrase
            $this->unlockWallet();
            $transaction_id = $this->sendXCPTokenWithBitcoinWallet($payout, $auction, $private_key);
        }

        return $transaction_id;
    }

    protected function sweepBTC($payout, $auction, $private_key) {
        $sweeper = new BitcoinPayer($this->native_client);
        $fee = $this->payouts_config['fee_per_kb'];
        list($transaction_id, $float_balance_sent) = $sweeper->sweepBTC($auction['auctionAddress'], $auction['platformAddress'], $private_key, $fee);
        return [$transaction_id, CurrencyUtil::numberToSatoshis($float_balance_sent)];
    }


    protected function isDivisible($token) {
        $assets = $this->xcpd_client->get_asset_info(['assets' => [$token]]);
#        Debug::trace("\$assets=",$assets,__FILE__,__LINE__,$this);
        return $assets[0]['divisible'];
    }

    protected function unlockWallet() {
        if ($this->wallet_passphrase) {
            $result = $this->native_client->walletpassphrase($this->wallet_passphrase, 60);
        }

    }

    protected function sendXCPTokenWithSender($payout, $auction, $private_key) {
        $public_key = BitcoinKeyUtils::publicKeyFromWIF($private_key, $auction['auctionAddress']);

        $quantity = $this->buildPayoutQuantity($payout);

        $other_counterparty_vars = [
            'multisig_dust_size' => CurrencyUtil::numberToSatoshis($this->payouts_config['multisig_dust_size']),
            'fee_per_kb'         => CurrencyUtil::numberToSatoshis($this->payouts_config['fee_per_kb']),
        ];
        if ($this->payouts_config['allow_unconfirmed_inputs']) {
            $other_counterparty_vars['allow_unconfirmed_inputs'] = true;
        }

        // unlock the wallet with the passphrase
        //   I don't think this is necessary with the sender
        // $this->unlockWallet();

        $transaction_id = $this->xcpd_sender->send($public_key, $private_key, $auction['auctionAddress'], $payout['address'], $quantity, $payout['token'], $other_counterparty_vars);

        return $transaction_id;
    }

    protected function sendXCPTokenWithBitcoinWallet($payout, $auction, $private_key) {
        // create a send
        // create_send(source, destination, asset, quantity, encoding='multisig', pubkey=null)
        $send_vars = [
            'source'             => $auction['auctionAddress'],
            'destination'        => $payout['address'],
            'asset'              => $payout['token'],

            // quantity is different if divisible
            'quantity'           => $this->buildPayoutQuantity($payout),

            // custom dust size and fees
            'multisig_dust_size' => CurrencyUtil::numberToSatoshis($this->payouts_config['multisig_dust_size']),
            'fee_per_kb'         => CurrencyUtil::numberToSatoshis($this->payouts_config['fee_per_kb']),
        ];
        if ($this->payouts_config['allow_unconfirmed_inputs']) { $send_vars['allow_unconfirmed_inputs'] = true; }

        if ($this->payout_debug) {
            EventLog::logEvent('DEBUG.payout.xcp', $send_vars);
            return 'DEBUG_'.md5(json_encode($send_vars));
        }

        // create a send
        $raw_tx = $this->xcpd_client->create_send($send_vars);

        // sign the transaction
        $signed_tx = $this->xcpd_client->sign_tx(["unsigned_tx_hex" => $raw_tx]);

        // broadcast the transaction
        $transaction_id = $this->xcpd_client->broadcast_tx(["signed_tx_hex" => $signed_tx]);

        return $transaction_id;
    }

    protected function buildPayoutQuantity($payout) {
        return intval($this->isDivisible($payout['token']) ? $payout['amount'] : CurrencyUtil::satoshisToNumber($payout['amount']));
    }

}

