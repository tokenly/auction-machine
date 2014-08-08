<?php

namespace LTBAuctioneer\Auctioneer\Payer;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\EventLog\EventLog;
/*
* AuctionPayer
*/
class AuctionPayer
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($auction_manager, $xcpd_client, $native_client, $address_generator, $payouts_config, $wallet_passphrase, $payout_debug) {
        $this->auction_manager = $auction_manager;
        $this->xcpd_client = $xcpd_client;
        $this->native_client = $native_client;
        $this->address_generator = $address_generator;
        $this->payouts_config = $payouts_config;
        $this->wallet_passphrase = $wallet_passphrase;
        $this->payout_debug = $payout_debug;
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
            // talk to the daemon, etc
            if ($payout['sweep']) {
                list($transaction_id, $amount_sent) = $this->sweepBTC($payout, $auction, $private_key);
                EventLog::logEvent('payout.btcSweep', ['auctionId' => $auction['id'], 'tx' => $transaction_id, 'amount' => $amount_sent]);
            } else {
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
        // make sure bitcoind has the address with the private key in the wallet
        if (!$this->payout_debug) {
            // this is no longer necessary - the private key is imported when the auction is created
            // $no_result = $this->native_client->importprivkey($private_key, $auction['auctionAddress'], false);
        }

        // create a send
        // create_send(source, destination, asset, quantity, encoding='multisig', pubkey=null)
        $send_vars = [
            'source'             => $auction['auctionAddress'],
            'destination'        => $payout['address'],
            'asset'              => $payout['token'],

            // quantity is different if divisible
            'quantity'           => intval($this->isDivisible($payout['token']) ? $payout['amount'] : CurrencyUtil::satoshisToNumber($payout['amount'])),

            // custom dust size and fees
            'multisig_dust_size' => CurrencyUtil::numberToSatoshis($this->payouts_config['multisig_dust_size']),
            'fee_per_kb'         => CurrencyUtil::numberToSatoshis($this->payouts_config['fee_per_kb']),
        ];
        if ($this->payouts_config['allow_unconfirmed_inputs']) { $send_vars['allow_unconfirmed_inputs'] = true; }
#        Debug::trace("\$send_vars=".json_encode($send_vars, 192),__FILE__,__LINE__,$this);
        if ($this->payout_debug) {
            EventLog::logEvent('DEBUG.payout.xcp', $send_vars);
            return 'DEBUG_'.md5(json_encode($send_vars));
        }
        $raw_tx = $this->xcpd_client->create_send($send_vars);

        // sign the transaction
        $signed_tx = $this->xcpd_client->sign_tx(["unsigned_tx_hex" => $raw_tx]);

        // broadcast the transaction
        $transaction_id = $this->xcpd_client->broadcast_tx(["signed_tx_hex" => $signed_tx]);
        return $transaction_id;
    }

    protected function sweepBTC($payout, $auction, $private_key) {
        if (!$this->payout_debug) {
            // this is no longer necessary - the private key is imported when the auction is created
            // $result = $this->native_client->importprivkey($private_key, $auction['auctionAddress'], false);
        }

        // unlock the wallet if needed
        if ($this->wallet_passphrase) {
            $result = $this->native_client->walletpassphrase($this->wallet_passphrase, 60);
        }

        $float_balance = $this->getTotalOfUnspentOutputs($auction['auctionAddress']);

        // this is a bit hack-y
        //   start with a $fee of 0.0001 and increase it by 0.0001 until bitcoind accepts it
        $fee = 0.0001;
        while ($fee <= 0.001) {
            try {
#                Debug::trace("fee: $fee",__FILE__,__LINE__,$this);
                $float_balance_sent = ($float_balance - $fee);
                if ($this->payout_debug) {
                    EventLog::logEvent('DEBUG.payout.btc', ['from' => $auction['auctionAddress'], 'to' => $auction['platformAddress'], 'amount' => $float_balance_sent]);
                    $result = 'DEBUG_'.md5(json_encode(['from' => $auction['auctionAddress'], 'to' => $auction['platformAddress'], 'amount' => $float_balance_sent]));
                } else {
                    $result = $this->native_client->sendfrom($auction['auctionAddress'], $auction['platformAddress'], $float_balance_sent);
                }
#                Debug::trace("\$result=".Debug::desc($result)."",__FILE__,__LINE__,$this);
                if ($result) { break; }
            } catch (Exception $e) {
                if ($e->getCode() == -4) {
                    // "This transaction requires a transaction fee of at least 0.0001 because of its amount, complexity, or use of recently received funds!""
                    // try increasing the fees and sending again
                    $fee += 0.0001;
                    continue;
                }

                throw $e;
            }

            if (!$result) { throw new Exception("Could not send with fee up to $fee", 1); }
        }

        return [$result, CurrencyUtil::numberToSatoshis($float_balance_sent)];
    }

    // returns a float
    protected function getTotalOfUnspentOutputs($address) {
        // get all funds (use blockr)
        // http://btc.blockr.io/api/v1/address/unspent/1EuJjmRA2kMFRhjAee8G6aqCoFpFnNTJh4
        $client = new GuzzleClient(['base_url' => 'http://btc.blockr.io',]);
        $response = $client->get('/api/v1/address/unspent/'.$address);
        $json_data = $response->json();

        $float = 0;
        foreach ($json_data['data']['unspent'] as $unspent) {
            $float += $unspent['amount'];
        }

        return $float;
    }

    protected function isDivisible($token) {
        $assets = $this->xcpd_client->get_asset_info(['assets' => [$token]]);
#        Debug::trace("\$assets=",$assets,__FILE__,__LINE__,$this);
        return $assets[0]['divisible'];
    }
}

