<?php

namespace LTBAuctioneer\Test\XChain;

use Exception;
use LTBAuctioneer\Debug\Debug;
use Utipd\CurrencyLib\CurrencyUtil;

/*
* XChainUtil
*/
class XChainUtil
{

    ////////////////////////////////////////////////////////////////////////


    public static function installXChainMockClientIfNeeded($app, $test_case) {
        if (isset($app['xchain.client.isMock']) AND $app['xchain.client.isMock']) { return; }
        return self::installXChainMockClient($app, $test_case);
    }
    public static function installXChainMockClient($app, $test_case) {

        $xchain_recorder = new \stdClass();
        $xchain_recorder->calls = [];

        $xchain_client = $test_case->getMockBuilder('\Tokenly\XChainClient\Client')
            ->disableOriginalConstructor()
            ->setMethods(['newAPIRequest'])
            ->getMock();

        // override the newAPIRequest method
        $xchain_client->method('newAPIRequest')->will($test_case->returnCallback(function($method, $path, $data) use ($xchain_recorder) {
            // store the method for test verification
            $xchain_recorder->calls[] = [
                'method' => $method,
                'path'   => $path,
                'data'   => $data,
            ];

            // call a method that returns sample data
            $sample_method_name = 'sampleData_'.strtolower($method).'_'.preg_replace('![^a-z0-9]+!i', '_', trim($path, '/'));
            return call_user_func(['self', $sample_method_name], $data);
        }));

        // install the xchain client into the DI container
        $app['xchain.client'] = $xchain_client;
        $app['xchain.client.isMock'] = true;

        return $xchain_recorder;
    }

    ////////////////////////////////////////////////////////////////////////

    public static function sampleData_post_addresses($data) {
        return [
            "id"      => "xxxxxxxx-xxxx-4xxx-yxxx-111111111111",
            "address" => "1oLaf1CoYcVE3aH5n5XeCJcaKPPGTxnxW",
        ];
    }
    public static function sampleData_post_monitors($data) {
        return [
            "id"              => "xxxxxxxx-xxxx-4xxx-yxxx-222222222222",
            "active"          => true,
            "address"         => "1oLaf1CoYcVE3aH5n5XeCJcaKPPGTxnxW",
            "monitorType"     => "receive",
            "webhookEndpoint" => "http://mywebsite.co/notifyme"
        ];
    }
    public static function sampleData_post_sends_xxxxxxxx_xxxx_4xxx_yxxx_111111111111($data) {
        return [
            "id"          => "xxxxxxxx-xxxx-4xxx-yxxx-333333333333",
            "txid"        => "0000000000000000000000000000001111",
            "destination" => $data['destination'],
            "quantity"    => $data['quantity'],
            "quantitySat" => CurrencyUtil::numberToSatoshis($data['quantity']),
            "asset"       => $data['asset'],
            "is_sweep"    => !!$data['sweep'],
        ];
    }


}

