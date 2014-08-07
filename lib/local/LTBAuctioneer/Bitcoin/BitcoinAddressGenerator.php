<?php

namespace LTBAuctioneer\Bitcoin;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BitcoinLib;
use LTBAuctioneer\Debug\Debug;
use Exception;

/*
* BitcoinAddressGenerator
*/
class BitcoinAddressGenerator
{

    protected $master_key = null;

    ////////////////////////////////////////////////////////////////////////

    public function __construct($platform_master_seed) {
        if (!strlen($platform_master_seed)) { throw new Exception("No platform master seed provided", 1); }
        $this->platform_master_seed = $platform_master_seed;
    }

    public function publicAddress($token, $identifier=0) {
#        Debug::trace("publicAddress token=".Debug::desc($token)."",__FILE__,__LINE__,$this);
        $private_key = $this->privateKey($token, $identifier);
#        Debug::trace("publicAddress private_key=".Debug::desc($private_key)."",__FILE__,__LINE__,$this);
        return BIP32::key_to_address($private_key);
    }

    public function privateKey($token, $identifier=0) {
        $master_key = $this->newMasterKey($this->deriveMasterSeed($token));
        $key = BIP32::build_key($master_key[0], $identifier);
        return $key[0];
    }

    public function WIFPrivateKey($token, $identifier=0) {
        $master_key = $this->newMasterKey($this->deriveMasterSeed($token));
        $key = BIP32::build_key($master_key[0], $identifier);
        $key_details = BIP32::import($key[0]);
        $wif = BitcoinLib::private_key_to_WIF($key_details['key'], true, $key_details['version']);
        if (!BitcoinLib::validate_WIF($wif)) { throw new Exception("Failed to validate WIF", 1); }
        return $wif;
    }

    public function newMasterKey($seed) {
        return BIP32::master_key($seed);
    }

    ////////////////////////////////////////////////////////////////////////

    protected function deriveMasterSeed($token) {
        return hash('sha512', $token.$this->platform_master_seed);
    }
}

