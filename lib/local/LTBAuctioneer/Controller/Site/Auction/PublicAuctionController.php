<?php

namespace LTBAuctioneer\Controller\Site\Auction;

use Exception;
use InvalidArgumentException;
use LTBAuctioneer\Controller\Exception\WebsiteException;
use LTBAuctioneer\Controller\Site\Base\BaseSiteController;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;

/*
* PublicAuctionController
*/
class PublicAuctionController extends BaseSiteController
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($app, $auction_manager, $block_directory) {
        parent::__construct($app);

        $this->auction_manager = $auction_manager;
        $this->block_directory = $block_directory;
        $this->pusher_url      = $app['config']['pusher.clientUrl'];
    }


    public function homeAction(Request $request) {
        $auctions = $this->auction_manager->findAuctions([], ['endDate' => 1]);

        $recently_ended_auctions = [];
        $counter = 0;
        $NUMBER_OF_RECENT_AUCTIONS = 5;
        foreach ($this->auction_manager->findAuctions(['timePhase' => 'ended'], ['endDate' => -1], 20) as $auction) {
            if ($auction['state']['active'] AND (!isset($auction['hidden']) OR !$auction['hidden'])) {
                $recently_ended_auctions[] = $auction;
                ++$counter;
                if ($counter >= $NUMBER_OF_RECENT_AUCTIONS) { break; }
            }
        }

        return $this->renderTwig('home/home.twig', ['auctions' => iterator_to_array($auctions), 'recentAuctions' => $recently_ended_auctions]);
    }

    public function viewHistoryAction(Request $request) {
        $auctions = [];
        foreach ($this->auction_manager->findAuctions(['timePhase' => 'ended'], ['endDate' => -1]) as $auction) {
            if ($auction['state']['active'] AND (!isset($auction['hidden']) OR !$auction['hidden'])) {
                $auctions[] = $auction;
            }
        }

        return $this->renderTwig('history/history.twig', ['auctions' => $auctions]);
    }

    public function viewAuctionAction(Request $request, $slug) {
        $auction = $this->auction_manager->findBySlug($slug);
        if (!$auction) { throw new WebsiteException("Could not find this auction", 1); }
        
        if (!$auction['state']['active']) {
            return $this->renderTwig('auction/public/view-auction-not-ready.twig', [
                'error'   => null,
                'auction' => $auction,
            ]);
        }

        $meta = [
            'lastBlockSeen' => $this->block_directory->getBestBlockHeight(),
        ];

        return $this->renderTwig('auction/public/view-auction.twig', [
            'error'     => null,
            'auction'   => $auction,
            'meta'      => $meta,
            'pusherUrl' => $this->pusher_url,
        ]);

    }

    public function auctionsData(Request $request) {
        $auctions_by_type = [];
        $auctions_by_type['liveAuctions'] = [];
        foreach ($this->auction_manager->findAuctions([], ['endDate' => 1]) as $auction) {
            if ($auction['state']['active'] and $auction['state']['timePhase'] != 'ended') {
                $auctions_by_type['liveAuctions'][] = $auction;
            }
        }

        $auctions_by_type['endedAuctions'] = [];
        foreach ($this->auction_manager->findAuctions(['timePhase' => 'ended'], ['endDate' => -1], 5) as $auction) {
            if ($auction['state']['active']) {
                $auctions_by_type['endedAuctions'][] = $auction;
            }
        }

        // last block
        $last_block_seen = $this->block_directory->getBestBlockHeight();

        $data = ['returned' => date('r'), 'liveAuctions' => [], 'endedAuctions' => []];
        foreach($auctions_by_type as $type => $auctions) {
            foreach($auctions as $auction) {
                $state = $auction['state'];
                $high_bid = isset($state['bidsAndAccounts'][0]) ? $state['bidsAndAccounts'][0] : [];
                if ($high_bid) {
                    $next_min_bid = ($high_bid ? $high_bid['amount'] : 0) + $auction['minBidIncrement'];
                } else {
                    $next_min_bid = $auction['minStartingBid'];
                }
                $next_payment = $next_min_bid + $state['bounty'];

                $bids_and_accounts = [];
                foreach ($state['bidsAndAccounts'] as $bid_entry) {
                    $bid_entry['amount'] = CurrencyUtil::satoshisToUnFormattedNumber($bid_entry['amount']);
                    $account = []; 
                    foreach ($bid_entry['account'] as $type => $satoshis) {
                        $account[$type] = CurrencyUtil::satoshisToUnFormattedNumber($satoshis);
                    }
                    $bid_entry['account'] = $account;
                    // $bid_entry['amount'] = CurrencyUtil::satoshisToUnFormattedNumber($bid_entry['amount']);
                    $bids_and_accounts[] = $bid_entry;
                }

                $entry = [
                    'name'              => $auction['name'],
                    'address'           => $auction['auctionAddress'],
                    'slug'              => $auction['slug'],
                    'url'               => $this->app->url('public-auction', ['slug' => $auction['slug']]),
                    'status'            => $auction->publicStatus(),
                    'start'             => date('r', $auction['startDate']),
                    // 'startTimestamp' => $auction['startDate'],
                    'end'               => date('r', $auction['endDate']),
                    // 'endTimestamp'   => $auction['endDate'],
                    'owner'             => $auction['username'],
                    'lastAction'        => $state['blockId'],
                    'lastBlock'         => $last_block_seen,
                    'highBidAmount'     => $high_bid ? CurrencyUtil::satoshisToUnFormattedNumber($high_bid['amount']) : null,
                    'highBidAsset'      => $high_bid ? $high_bid['token'] : null,

                    'startingBid'       => CurrencyUtil::satoshisToUnFormattedNumber($auction['minStartingBid']),
                    'currentBids'       => $bids_and_accounts,
                    'bounty'            => CurrencyUtil::satoshisToUnFormattedNumber($state['bounty']),
                    'nextMinBid'        => CurrencyUtil::satoshisToUnFormattedNumber($next_payment),
                ];

                $data[$type][] = $entry;
            }
        }

        $response = $this->app->json($data);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }



    ////////////////////////////////////////////////////////////////////////


}

