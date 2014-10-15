<?php

namespace LTBAuctioneer\Test\Auction;

use Exception;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Test\Util\RequestUtil;

/*
* AuctionUtil
*/
class AuctionUtil
{

    static $ADDRESS_COUNTER;

    ////////////////////////////////////////////////////////////////////////

    public static function newAuctionVars() {
        return [
            'name'                    => 'Auction One',
            'username'                => 'excelsior',
            'description'             => 'Best auction ever',
            'startDate'               => date('m.d.Y g:i a'),
            'endDate'                 => date('m.d.Y g:i a', strtotime('+1 day')),
            'timezone'                => '-05:00',
            'longTimezone'            => 'America/Chicago',
            'minStartingBid'          => 1000,
            'bidTokenType'            => 'LTBCOIN',

            'winningTokenQuantity_0'  => 1,
            'winningTokenType_0'      => 'SPONSOR',

            // 'winningTokenQuantity' => 1,
            // 'winningTokenType'     => 'SPONSOR',
            // 'prizeTokensRequired'  => [
            //     ['token'           => 'SPONSOR', 'amount' => 1,],
            // ],

            'sellerAddress'      => '1Fox7M6ytjD4fQij8wxF19GeNvh43sp3fn',
        ];
    }

    public static function createNewAuction($app, $submission_vars=[]) {
        $submission_vars = array_merge(self::newAuctionVars(), $submission_vars);
        $response = RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 303);

        // load the new auction
        $target_url = $response->getTargetUrl();
        preg_match('!.*/(.*?)$!', $target_url, $matches);
        $ref_id = $matches[1];
#        Debug::trace("\$ref_id=".Debug::desc($ref_id)."",__FILE__,__LINE__);
        
        $auction = $app['directory']('Auction')->findByRefID($ref_id);
        return $auction;

    }

    public static function nextTestAddress() {
        $addresses = [
            '1oLaf1CoYcVE3aH5n5XeCJcaKPPGTxnxW',
            '1FrostCshXgkPFEsQUebWBzzErNpY69wyT',
            '1MorphGNEXJScA8frgEBzUyZ4CX3nCHams',
            '1GeekSSss9YrUMPSsroKTroi4fbKDotyuJ',
            '1MinioH7WV7LYUm7wNK2uZCK9RmC1JjUJZ',
            '1Fr34k5FBvqMAiEPirarD2JfZiwyC39cp7',
            '1btcUBJQkqeCQ667XpimMCSRjynekH2PF',
            'BiticiuML4PP5iBTVReJbTeAwN9Tvhuiq',
            'ALLEN94MhRmYWBVNZWwpZo4tCXDb79hhF',
        ];

        return $addresses[self::$ADDRESS_COUNTER++];
    }


    ////////////////////////////////////////////////////////////////////////

}

