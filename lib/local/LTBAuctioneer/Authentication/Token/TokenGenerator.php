<?php

namespace LTBAuctioneer\Authentication\Token;

use Exception;
use LTBAuctioneer\Debug\Debug;

/*
* TokenGenerator
*/
class TokenGenerator
{

    const AUCTION_PREFIX = 'A';
    const ADDRESS_PREFIX = 'B';

    ////////////////////////////////////////////////////////////////////////



    public function generateToken($prefix_type, $length=30, $chars=null) {
        $token = "";

        if ($chars === null) {
            $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
            $number_of_chars = 62;
        } else {
            $number_of_chars = strlen($chars);
        }

        for($i=0; $i < $length; $i++){
            $token .= $chars[self::random(0, $number_of_chars)];
        }

        if (strlen($prefix_type)) {
            $prefix = constant('LTBAuctioneer\Authentication\Token\TokenGenerator::'.strtoupper($prefix_type).'_PREFIX');
        } else {
            $prefix = '';
        }

        return $prefix.$token;
    }

    public function generateHexString($length=64) {
        return $this->generateToken($length, 'abcdef0123456789');
    } 

    public function randomNumber() {
        return hexdec(bin2hex(openssl_random_pseudo_bytes(PHP_INT_SIZE - 1)));
    } 

    ////////////////////////////////////////////////////////////////////////

    protected function random($min, $max) {
        $range = $max - $min;

        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);

        return $min + $rnd;
    }


}

