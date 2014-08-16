<?php

namespace LTBAuctioneer\Managers;

use Exception;
use LTBAuctioneer\Auctioneer\AuctionState;
use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Util\Retry\RetryController;
use Parsedown;

/*
* AuctionManager
*/
class AuctionManager
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($auction_directory, $token_generator, $slugger, $address_generator, $native_client, $wallet_passphrase, $auction_defaults) {
        $this->auction_directory = $auction_directory;
        $this->token_generator = $token_generator;
        $this->slugger = $slugger;
        $this->address_generator = $address_generator;
        $this->native_client = $native_client;
        $this->wallet_passphrase = $wallet_passphrase;
        $this->auction_defaults = $auction_defaults;
    }

    public function newAuction($new_auction_vars) {
        // assign a ref id
        $new_auction_vars['refId'] = $this->token_generator->generateToken('AUCTION', 40);

        // assign an address offset and an address
        $new_auction_vars['keyToken'] = $this->token_generator->generateToken('ADDRESS', 42);

        // new auctionAddress
        $new_auction_vars['auctionAddress'] = $this->address_generator->publicAddress($new_auction_vars['keyToken']);

        // build a slug
        $new_auction_vars['slug'] = $this->createSlug($new_auction_vars);

        // created TS
        $new_auction_vars['create'] = time();

        // timePhase
        $new_auction_vars['timePhase'] = 'prebid';

        // payouts
        $new_auction_vars['paidOut'] = false;
        $new_auction_vars['payoutReceipts'] = [];
       
        // logs
        $new_auction_vars['logs'] = [];

        // defaults
        if (!isset($new_auction_vars['platformAddress'])) { $new_auction_vars['platformAddress'] = $this->auction_defaults['platformAddress']; }
        if (!isset($new_auction_vars['confirmationsRequired'])) { $new_auction_vars['confirmationsRequired'] = $this->auction_defaults['confirmationsRequired']; }
        if (!isset($new_auction_vars['minStartingBid'])) { $new_auction_vars['minStartingBid'] = CurrencyUtil::numberToSatoshis($this->auction_defaults['minStartingBid']); }
        if (!isset($new_auction_vars['minBidIncrement'])) { $new_auction_vars['minBidIncrement'] = CurrencyUtil::numberToSatoshis($this->auction_defaults['minBidIncrement']); }
        if (!isset($new_auction_vars['bountyPercent'])) { $new_auction_vars['bountyPercent'] = $this->auction_defaults['bountyPercent']; }
        if (!isset($new_auction_vars['btcFeeRequired'])) { $new_auction_vars['btcFeeRequired'] = CurrencyUtil::numberToSatoshis($this->auction_defaults['btcFeeRequired']); }
        if (!isset($new_auction_vars['bidTokenFeeRequired'])) { $new_auction_vars['bidTokenFeeRequired'] = CurrencyUtil::numberToSatoshis($this->auction_defaults['bidTokenFeeRequired']); }
        if (!isset($new_auction_vars['prizeTokensRequired'])) { $new_auction_vars['prizeTokensRequired'] = []; }
        if (!isset($new_auction_vars['btcFeeSatisfied'])) { $new_auction_vars['btcFeeSatisfied'] = false; }
        if (!isset($new_auction_vars['bidTokenFeeSatisfied'])) { $new_auction_vars['bidTokenFeeSatisfied'] = false; }
        if (!isset($new_auction_vars['prizeTokensSatisfied'])) { $new_auction_vars['prizeTokensSatisfied'] = false; }

        // default state
        $default_state_vars = AuctionState::serializedInitialState();
        if (!isset($new_auction_vars['state'])) { $new_auction_vars['state'] = $default_state_vars; }

        // description as HTML
        $Parsedown = new Parsedown();
        $new_auction_vars['descriptionHTML'] = $Parsedown->text($new_auction_vars['description']);

        // import the private key and address into the bitcoin wallet
        if ($this->native_client) {
            $private_key = $this->address_generator->WIFPrivateKey($new_auction_vars['keyToken']);

            // unlock the wallet if needed
            if ($this->wallet_passphrase) {
                $result = $this->native_client->walletpassphrase($this->wallet_passphrase, 60);
#                Debug::trace("called walletpassphrase result=".Debug::desc($result)."",__FILE__,__LINE__,$this);
            }

            RetryController::retry(function() use ($new_auction_vars, $private_key) {
                $result = $this->native_client->importprivkey($private_key, $new_auction_vars['auctionAddress'], false);
            });
        }
        

        $auction = $this->auction_directory->createAndSave($new_auction_vars);

        return $auction;
    }

    public function findByRefID($ref_id) {
        return $this->auction_directory->findByRefID($ref_id);
    }

    public function findBySlug($slug) {
        return $this->auction_directory->findBySlug($slug);
    }

    public function findById($id) {
        return $this->auction_directory->findById($id);
    }

    public function allAuctions() {
        return $this->auction_directory->find([]);
    }
    public function findAuctions($where, $sort=null, $limit=null) {
        if ($sort === null) { $sort = ['startDate' => 1]; }
        return $this->auction_directory->find($where, $sort, $limit);
    }

    public function update($auction, $update_vars) {
        if (isset($update_vars['description'])) {
            // description as HTML
            $Parsedown = new Parsedown();
            $update_vars['descriptionHTML'] = $Parsedown->text($update_vars['description']);
        }

        return $this->auction_directory->update($auction, $update_vars);
    }

    public function findAuctionsThatNeedTimePhaseUpdate() {
        $sql = "SELECT * FROM {$this->auction_directory->getTableName()} WHERE ".
            "(startDate <= ? AND timePhase = ?) OR (endDate <= ? AND timePhase = ?) ".
            "ORDER by startDate";
        $now = isset($GLOBALS['_TEST_NOW']) ? $GLOBALS['_TEST_NOW'] : time();
        return $this->auction_directory->findRaw($sql, [$now, 'prebid', $now, 'live']);
    }

    public function findAuctionsPendingPayout() {
        return $this->auction_directory->find(['timePhase' => 'ended', 'paidOut' => false], ['startDate' => 1]);
    }

    public function findAuctionsPendingPayoutConfirmation() {
        return $this->auction_directory->find(['timePhase' => 'ended', 'paidOut' => true, 'payoutsConfirmed' => false], ['startDate' => 1]);
    }


    ////////////////////////////////////////////////////////////////////////

    protected function createSlug($new_auction_vars) {
        return $this->slugger->buildUniqueSlug($new_auction_vars['name'], function($slug) {
            $other_auction = $this->auction_directory->findBySlug($slug);
            if ($other_auction) { return false; }
            return true;
        });
    }

}

