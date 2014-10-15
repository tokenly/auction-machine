<?php

use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\Init\Environment;
use LTBAuctioneer\Test\Auction\AuctionUtil;
use LTBAuctioneer\Test\TestCase\SiteTestCase;
use LTBAuctioneer\Test\Util\RequestUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class CreateAuctionTest extends SiteTestCase
{

    public function testNewAuctionErrors() {
        $app = Environment::initEnvironment('test');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['name' => '']);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Please enter an auction name.');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['username' => '']);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Please enter your username.');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['description' => '']);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Please enter an auction description');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['startDate' => '']);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Please enter a start date between today');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['endDate' => '']);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Please enter an end date between');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['endDate' => date('m.d.Y g:i a', time() + 86400*40)]);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Please enter an end date between');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['endDate' => date('m.d.Y g:i a', time() + 300)]);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, '24 hours');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['minStartingBid' => 999]);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'minimum starting bid');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['bidTokenType' => 'bad']);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Bid token name was not valid');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['winningTokenType_0' => 'bad']);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Winning token name was not valid');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['winningTokenQuantity_0' => 0]);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Please enter a winning token quantity');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['winningTokenQuantity_1' => 0]);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Please enter a winning token quantity for token 2');

        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), ['sellerAddress' => 'BADBADADDRESS']);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 200, 'Seller Address must be a valid Bitcoin address');


        // all ok
        $submission_vars = array_merge(AuctionUtil::newAuctionVars(), []);
        RequestUtil::assertResponseWithStatusCode($app, 'POST', '/create/auction/new', $submission_vars, 303);
    } 

    public function testNewAuctionCreated() {
        $app = Environment::initEnvironment('test');
        $auction = AuctionUtil::createNewAuction($app);

        PHPUnit::assertNotNull($auction);
        PHPUnit::assertGreaterThan(40, strlen($auction['refId']));
    }

    public function testNewAuctionTimezone() {
        $app = Environment::initEnvironment('test');
        $start_date_string = date('m.d.Y g:i a', strtotime('+5 hours'));
        $end_date_string = date('m.d.Y g:i a', strtotime('+3 days'));
#        Debug::trace("\$start_date_string=$start_date_string \$end_date_string=$end_date_string",__FILE__,__LINE__,$this);
        $auction = AuctionUtil::createNewAuction($app, ['startDate' => $start_date_string, 'endDate' => $end_date_string, 'timezone' => '-04:00', 'longTimezone' => 'America/New_York']);

        PHPUnit::assertNotNull($auction);
        $expected_dt = DateTime::createFromFormat('m.d.Y g:i a', $start_date_string, new \DateTimeZone('-04:00'));
        PHPUnit::assertEquals($expected_dt->getTimestamp(), $auction['startDate']);
    }

    public function testNewAuctionLongTimezone() {
        $app = Environment::initEnvironment('test');
        $start_date_string = date('m.d.Y g:i a', strtotime('+5 hours'));
        $end_date_string = date('m.d.Y g:i a', strtotime('+3 days'));
#        Debug::trace("\$start_date_string=$start_date_string \$end_date_string=$end_date_string",__FILE__,__LINE__,$this);
        $auction = AuctionUtil::createNewAuction($app, ['startDate' => $start_date_string, 'endDate' => $end_date_string, 'timezone' => '01:00', 'longTimezone' => 'America/Chicago']);

        PHPUnit::assertNotNull($auction);
        $expected_dt = DateTime::createFromFormat('m.d.Y g:i a', $start_date_string, new \DateTimeZone('-05:00'));
        PHPUnit::assertEquals(date("Y-m-d H:i:s", $expected_dt->getTimestamp()), date("Y-m-d H:i:s", $auction['startDate']));
    }

    public function testNewAuctionConfirmation() {
        $app = Environment::initEnvironment('test');
        $auction = AuctionUtil::createNewAuction($app);

        $response = RequestUtil::assertResponseWithStatusCode($app, 'GET', '/create/auction/'.$auction['refId'], [], 200);
        $response_content = $response->getContent();

        $created_vars = AuctionUtil::newAuctionVars();
        PHPUnit::assertContains($created_vars['name'], $response_content);
        PHPUnit::assertContains(number_format($created_vars['minStartingBid']), $response_content);
        PHPUnit::assertContains($created_vars['sellerAddress'], $response_content);
        PHPUnit::assertContains('you must send', $response_content);

    }

    public function testUniqueAuctionSlugs() {
        $app = Environment::initEnvironment('test');
        $auction = AuctionUtil::createNewAuction($app);
        PHPUnit::assertEquals('auction-one', $auction['slug']);
        $auction = AuctionUtil::createNewAuction($app);
        PHPUnit::assertEquals('auction-one-2', $auction['slug']);

        PHPUnit::assertNotNull($auction);
        PHPUnit::assertGreaterThan(40, strlen($auction['refId']));
    }

}

