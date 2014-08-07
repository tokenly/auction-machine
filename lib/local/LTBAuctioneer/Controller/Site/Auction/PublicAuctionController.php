<?php

namespace LTBAuctioneer\Controller\Site\Auction;

use Exception;
use InvalidArgumentException;
use LTBAuctioneer\Controller\Exception\WebsiteException;
use LTBAuctioneer\Controller\Site\Base\BaseSiteController;
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


    ////////////////////////////////////////////////////////////////////////


}

