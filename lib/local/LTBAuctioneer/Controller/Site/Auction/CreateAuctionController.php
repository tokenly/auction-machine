<?php

namespace LTBAuctioneer\Controller\Site\Auction;

use Exception;
use InvalidArgumentException;
use LTBAuctioneer\Controller\Exception\WebsiteException;
use LTBAuctioneer\Controller\Site\Base\BaseSiteController;
use Utipd\CurrencyLib\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use LTBAuctioneer\EventLog\EventLog;
use LinusU\Bitcoin\AddressValidator;
use Respect\Validation\Validator as v;
use Symfony\Component\HttpFoundation\Request;
use Utipd\Form\Exception\FormException;
use Utipd\Form\Sanitizer;
use Utipd\Form\Validator;

/*
* CreateAuctionController
*/
class CreateAuctionController extends BaseSiteController
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($app, $auction_manager, $xcpd_follower) {
        parent::__construct($app);

        $this->auction_manager = $auction_manager;
        $this->xcpd_follower = $xcpd_follower;
    }


    public function newAuctionAction(Request $request) {


        $submitted_data = null;
        if ($request->isMethod('POST')) {
            $submitted_data = $request->request->all();
#            Debug::trace("\$submitted_data=",$submitted_data,__FILE__,__LINE__,$this);
            try {
                // validate the data
                $validator = new Validator($this->buildAuctionAdminSpecForSubmittedData($submitted_data));
                $sanitized_data = $validator->sanitizeSubmittedData($submitted_data);
#               Debug::trace("\$sanitized_data=".json_encode($sanitized_data, 192),__FILE__,__LINE__,$this);
                $sanitized_data = $validator->validateSubmittedData($sanitized_data);

                // check auction length
                if (($sanitized_data['endDate'] - $sanitized_data['startDate']) < 86400) {
                    throw new FormException("Please enter dates that are at least 24 hours apart.", 1);
                }

                // create a auction
                $new_auction_vars = $sanitized_data;
                // combine the winning tokens
                $new_auction_vars = $this->formatPrizeTokensForNewAuction($new_auction_vars);
                $auction = $this->auction_manager->newAuction($new_auction_vars);

                EventLog::logEvent('auction.created', ['auctionId' => $auction['id'], 
                            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null, 
                            'proxyIp' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null]
                        );

                // go to the home
                return $this->app->redirect($this->app->url('create-auction-confirm', ['auctionRefId' => $auction['refId']]), 303);

            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (FormException $e) {
                $error = $e->getDisplayErrorsAsHTML();
            }
        } else {
            $validator = new Validator($this->buildAuctionAdminSpec());
        }

        return $this->renderTwig('auction/create/new-auction.twig', [
            'submittedData' => isset($submitted_data) ? $submitted_data : $validator->getDefaultValues(),
            'error'         => isset($error) ? $error : null,
        ]);

    }

    public function confirmAuctionAction(Request $request, $auctionRefId) {
        $auction = $this->auction_manager->findByRefID($auctionRefId);
        if (!$auction) { throw new WebsiteException("This auction was not found", 1, "auction not found: ".Debug::desc($auctionRefId).""); }

        $meta = [
            'lastBlockSeen' => $this->xcpd_follower->getLastProcessedBlock(),
        ];

        return $this->renderTwig('auction/create/new-confirm.twig', [
            'auctionRefId' => $auctionRefId,
            'auction'      => $auction,
            'meta'         => $meta,
            'error'        => isset($error) ? $error : null,
        ]);

    }


    ////////////////////////////////////////////////////////////////////////


    protected function buildAuctionAdminSpec() {
        $spec = [
            'name' => [
                'name'      => 'name',
                'label'     => 'Auction Name',
                'default'   => '',
                'validation' => v::string()->length(1,255,true),
                'sanitizer' => function($v) { return trim($v); },
                'error'     => 'Please enter an auction name.',
            ],
            'username' => [
                'name'      => 'username',
                'label'     => 'Your LTB username',
                'default'   => '',
                'validation' => v::string()->length(1,127,true),
                'sanitizer' => function($v) { return trim($v); },
                'error'     => 'Please enter your username.',
            ],
            'description' => [
                'name'      => 'description',
                'label'     => 'Description',
                'default'   => '',
                'validation' => v::string()->length(1,4000,true),
                'sanitizer' => function($v) { return trim($v); },
                'error'     => 'Please enter an auction description up to 4,000 characters long.',
            ],
            'startDate' => [
                'name'      => 'startDate',
                'label'     => 'Auction Start',
                'default'   => date("m.d.Y g:00 a", time()+3600+900),
                'validation' => v::int()->min(time() - 60,true),
                'sanitizer' => function($v, $d) { return ($_d = \DateTime::createFromFormat('m.d.Y g:i a', $v, new \DateTimeZone($this->calulcateTimezone($d['timezone'])))) ? $_d->getTimestamp() : 0; },
                'error'     => 'Please enter a start date between today and 30 days from now.',
            ],
            'endDate' => [
                'name'      => 'endDate',
                'label'     => 'Auction End',
                'default'   => date("m.d.Y g:00 a", time()+3600+900 + 86400*7),
                'validation' => v::int()->min(strtotime('+1 day -1 minute'), true)->max(strtotime('+30 days'), true),
                'sanitizer' => function($v, $d) { return ($_d = \DateTime::createFromFormat('m.d.Y g:i a', $v, new \DateTimeZone($this->calulcateTimezone($d['timezone'])))) ? $_d->getTimestamp() : 0; },
                'error'     => 'Please enter an end date between 24 hours and 30 days from now.',
            ],

            'minStartingBid' => [
                'name'      => 'minStartingBid',
                'label'     => 'Minimum Starting Bid',
                'default'   => '',
                'validation' => v::int()->min(CurrencyUtil::numberToSatoshis(1000), true),
                'sanitizer' => function($v) { return CurrencyUtil::numberToSatoshis($v); },
                'error'     => 'Please enter a minimum starting bid of at least 1000.',
            ],

            'bidTokenType' => [
                'name'       => 'bidTokenType',
                'label'      => 'Accepting Bids Token',
                'default'   => '',
                'validation' => v::alpha()->length(4,14,true),
                'sanitizer'  => Sanitizer::trim()->Uppercase(),
                'error'      => 'Bid token name was not valid.',
            ],



            'winningTokenType_0' => [
                'name'       => 'winningTokenType_0',
                'label'      => 'Accepting Bids Token',
                'default'   => '',
                'validation' => v::alpha()->length(4,14,true),
                'sanitizer'  => Sanitizer::trim()->Uppercase(),
                'error'      => 'Winning token name was not valid for token 1.',
            ],
            'winningTokenQuantity_0' => [
                'name'      => 'winningTokenQuantity_0',
                'label'     => 'Winning Token Quantity',
                'default'   => '',
                'validation' => v::int()->min(CurrencyUtil::numberToSatoshis(1), true), // must be at least 1.0 of something
                'sanitizer' => function($v) { return CurrencyUtil::numberToSatoshis($v); },
                'error'     => 'Please enter a winning token quantity for token 1 of at least 1.',
            ],
        ];

        // clone the winning tokens
        for ($i=1; $i <= 9; $i++) { 
            $number = $i + 1;
            $spec['winningTokenType_'.$i.''] = $spec['winningTokenType_0'];
            $spec['winningTokenType_'.$i.'']['name'] = 'winningTokenType_'.$i.'';
            $spec['winningTokenType_'.$i.'']['error'] = str_replace('token 1', 'token '.$number, $spec['winningTokenType_'.$i.'']['error']);

            $spec['winningTokenQuantity_'.$i.''] = $spec['winningTokenQuantity_0'];
            $spec['winningTokenQuantity_'.$i.'']['name'] = 'winningTokenQuantity_'.$i.'';
            $spec['winningTokenQuantity_'.$i.'']['error'] = str_replace('token 1', 'token '.$number, $spec['winningTokenQuantity_'.$i.'']['error']);
        }

        // add the last fields

        $spec = array_merge($spec, [
            'sellerAddress' => [
                'name'      => 'sellerAddress',
                'label'     => 'Seller Address',
                'default'   => '',
                'validation' => function($value) { return (strlen($value) AND AddressValidator::isValid($value)); },
                'sanitizer'  => Sanitizer::trim(),
                'error'      => 'Seller Address must be a valid Bitcoin address.',
            ],

            'timezone' => [
                'name'      => 'timezone',
                'label'     => 'Timezone',
                'default'   => '',
                'validation' => v::string(),
                'sanitizer'  => Sanitizer::trim(),
                'error'      => 'timezone was invalid.',
            ],
        ]);

        // Debug::trace("\$spec=\n".json_encode($spec, 192),__FILE__,__LINE__,$this);

        return $spec;
    }

    protected function buildAuctionAdminSpecForSubmittedData($submitted_data) {
        $spec = $this->buildAuctionAdminSpec();
        for ($i=1; $i <= 9; $i++) { 
            $should_validate = (
                (isset($submitted_data['winningTokenType_'.$i.'']) AND strlen(trim($submitted_data['winningTokenType_'.$i.''])))
                OR
                (isset($submitted_data['winningTokenQuantity_'.$i.'']) AND strlen(trim($submitted_data['winningTokenQuantity_'.$i.''])))
            );
            if (!$should_validate) {
                unset($spec['winningTokenType_'.$i.'']);
                unset($spec['winningTokenQuantity_'.$i.'']);
            }
        } 
        return $spec;
    }

    protected function formatPrizeTokensForNewAuction($new_auction_vars) {
#        Debug::trace("\$new_auction_vars=",$new_auction_vars,__FILE__,__LINE__,$this);
        // prizeTokensRequired:
        //   - token: SPONSOR
        //     amount: 1

        $prize_tokens = [];
        for ($i=0; $i <= 9; $i++) { 
            $should_add = (isset($new_auction_vars['winningTokenType_'.$i.'']) AND strlen(trim($new_auction_vars['winningTokenType_'.$i.''])));
            if ($should_add) {
                $entry = [
                    'amount' => $new_auction_vars['winningTokenQuantity_'.$i.''],
                    'token'  => $new_auction_vars['winningTokenType_'.$i.''],
                ];
                $prize_tokens[] = $entry;
            }
            unset($new_auction_vars['winningTokenType_'.$i.'']);
            unset($new_auction_vars['winningTokenQuantity_'.$i.'']);
        }
#       Debug::trace("\$prize_tokens=",$prize_tokens,__FILE__,__LINE__,$this);

        $new_auction_vars['prizeTokensRequired'] = $prize_tokens;
        return $new_auction_vars;
    }

    protected function calulcateTimezone($timezone_in) {
        $timezone = preg_replace('/[^0-9-]/', '', $timezone_in) * 36;
        return timezone_name_from_abbr(null, $timezone, date('I', time()));

    }


}

