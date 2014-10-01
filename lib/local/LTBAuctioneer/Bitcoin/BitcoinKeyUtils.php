<?php

namespace LTBAuctioneer\Bitcoin;

use EmailMe\Debug\Debug;
use BitWasp\BitcoinLib\BitcoinLib;
use Exception;

/*
* BitcoinKeyUtils
*/
class BitcoinKeyUtils
{

    ////////////////////////////////////////////////////////////////////////

    public static function publicKeyFromWIF($wif, $verify_address=null) {
        $is_valid = BitcoinLib::validate_WIF($wif);
        if (!$is_valid) { throw new Exception("Invalid WIF", 1); }

        $private_key_details = BitcoinLib::WIF_to_private_key($wif);
        $private_key = $private_key_details['key'];

        $address_version = '00';
        $trial_wif = BitcoinLib::private_key_to_WIF($private_key, true, $address_version);
        if ($trial_wif !== $wif) { throw new Exception("WIF re-encoding failed", 1); }

        $compressed_public_key = BitcoinLib::private_key_to_public_key($private_key, true);

        if ($verify_address !== null) {
            $address = BitcoinLib::public_key_to_address($compressed_public_key, $address_version);
            if ($verify_address !== $address) { throw new Exception("Address verification failed.  Found address $address.  Expected address $verify_address", 1); }
        }

        return $compressed_public_key;
    }

    ////////////////////////////////////////////////////////////////////////

}

