<?php

namespace LTBAuctioneer\Controller\Site\Auction;

use Exception;
use InvalidArgumentException;
use LTBAuctioneer\Controller\Exception\WebsiteException;
use LTBAuctioneer\Controller\Site\Base\BaseSiteController;
use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;

/*
* PublicAuctionController
*/
class PublicAuctionController extends BaseSiteController
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($app, $auction_manager, $xcpd_follower) {
        parent::__construct($app);

        $this->auction_manager = $auction_manager;
        $this->xcpd_follower = $xcpd_follower;
    }


    public function homeAction(Request $request) {
        $auctions = $this->auction_manager->findAuctions([], ['endDate' => 1]);

        $recently_ended_auctions = [];
        foreach ($this->auction_manager->findAuctions(['timePhase' => 'ended'], ['endDate' => -1], 5) as $auction) {
            if ($auction['state']['active']) { $recently_ended_auctions[] = $auction; }
        }

        return $this->renderTwig('home/home.twig', ['auctions' => iterator_to_array($auctions), 'recentAuctions' => $recently_ended_auctions]);
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
            'lastBlockSeen' => $this->xcpd_follower->getLastProcessedBlock(),
        ];

        return $this->renderTwig('auction/public/view-auction.twig', [
            'error'   => null,
            'auction' => $auction,
            'meta'    => $meta,
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
        $last_block_seen = $this->xcpd_follower->getLastProcessedBlock();

        $data = ['returned' => date('r'), 'liveAuctions' => [], 'endedAuctions' => []];
        foreach($auctions_by_type as $type => $auctions) {
            foreach($auctions as $auction) {
                $state = $auction['state'];
                $high_bid = isset($state['bidsAndAccounts'][0]) ? $state['bidsAndAccounts'][0] : [];

                $entry = [
                    'name'           => $auction['name'],
                    'address'        => $auction['auctionAddress'],
                    'slug'           => $auction['slug'],
                    'url'            => $this->app->url('public-auction', ['slug' => $auction['slug']]),
                    'status'         => $auction->publicStatus(),
                    'start'          => date('r', $auction['startDate']),
                    // 'startTimestamp' => $auction['startDate'],
                    'end'            => date('r', $auction['endDate']),
                    // 'endTimestamp'   => $auction['endDate'],
                    'owner'          => $auction['username'],
                    'lastAction'     => $state['blockId'],
                    'lastBlock'      => $last_block_seen,
                    'highBidAmount'  => $high_bid ? CurrencyUtil::satoshisToNumber($high_bid['amount']) : null,
                    'highBidAsset'   => $high_bid ? $high_bid['token'] : null,
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

