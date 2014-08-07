<?php

use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class CurrencyTest extends \PHPUnit_Framework_TestCase
{

    public function testCurrencyConversions() {
        $amount = 1000;
        $satoshis = CurrencyUtil::numberToSatoshis($amount);
        PHPUnit::assertEquals(100000000000, $satoshis);
        PHPUnit::assertEquals('1,000', CurrencyUtil::satoshisToNumber($satoshis));

        
    } 

}
