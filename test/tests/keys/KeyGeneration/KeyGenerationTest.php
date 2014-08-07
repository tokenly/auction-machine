<?php

use BitWasp\BitcoinLib\BIP32;
use LTBAuctioneer\Authentication\Token\TokenGenerator;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionUtil;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class KeyGenerationTest extends SiteTestCase
{

    public function testNewKeys() {
        $app = Environment::initEnvironment('test');

        $gen = $app['bitcoin.addressGenerator'];
        $public = $gen->publicAddress('one');

        $private_key = $gen->privateKey('one');
        $address = BIP32::key_to_address($private_key);

        PHPUnit::assertEquals($address, $public);

        $token_generator = new TokenGenerator();
        PHPUnit::assertNotEquals(
            $gen->privateKey('one'), 
            $gen->privateKey('two')
        );

    } 

    public function testNewAuctionKeys() {
        $app = Environment::initEnvironment('test');
        $auction_1 = AuctionUtil::createNewAuction($app);
        PHPUnit::assertNotEmpty($auction_1['auctionAddress']);
        $gen = $app['bitcoin.addressGenerator'];
        $private_key = $gen->privateKey($auction_1['keyToken']);
        $address = BIP32::key_to_address($private_key);
        PHPUnit::assertEquals($address, $auction_1['auctionAddress']);

        $auction_2 = AuctionUtil::createNewAuction($app);
        $gen = $app['bitcoin.addressGenerator'];
        $private_key = $gen->privateKey($auction_2['keyToken']);
        $address = BIP32::key_to_address($private_key);
        PHPUnit::assertEquals($address, $auction_2['auctionAddress']);

        PHPUnit::assertNotEquals($auction_1['auctionAddress'], $auction_2['auctionAddress']);

    }
}
