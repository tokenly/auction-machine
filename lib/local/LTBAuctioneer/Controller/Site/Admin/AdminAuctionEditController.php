<?php

namespace LTBAuctioneer\Controller\Site\Admin;

use Exception;
use LTBAuctioneer\Controller\Site\Admin\Util\AdminUtil;
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
* AdminAuctionEditController
*/
class AdminAuctionEditController extends BaseSiteController
{

    public function __construct($app, $auction_manager) {
        parent::__construct($app);

        $this->auction_manager = $auction_manager;
    }



    ////////////////////////////////////////////////////////////////////////

    public function editAuctionAction(Request $request, $auctionId) {
        $auction = $this->auction_manager->findById(intval($auctionId));
        if (!$auction) { throw new Exception("Auction not found for id $auctionId", 1); }

        $submitted_data = null;
        if ($request->isMethod('POST')) {
            $submitted_data = $request->request->all();
            try {
                // validate the data
                $validator = new Validator($this->buildAuctionAdminSpecForSubmittedData($submitted_data));
                $sanitized_data = $validator->sanitizeSubmittedData($submitted_data);
                $sanitized_data = $validator->validateSubmittedData($sanitized_data);

                // edit the auction
                $auction_edit_vars = $sanitized_data;
                $auction_edit_vars = $this->formatPrizeTokensForNewAuction($auction_edit_vars);
                list($previous_vars, $update_vars) = $this->calculatePreviousAndUpdateVars($auction, $auction_edit_vars);
                if (!$update_vars) { throw new FormException("No changes found", 1); }
#               Debug::trace("\$previous_vars=\n".json_encode($previous_vars, 192),__FILE__,__LINE__,$this);
#               Debug::trace("\$update_vars=\n".json_encode($update_vars, 192),__FILE__,__LINE__,$this);

                // update
                $this->auction_manager->update($auction, $update_vars);
                EventLog::logEvent('admin.auction.updated', [
                    'auctionId'     => $auction['id'],
                    'previous_vars' => $previous_vars,
                    'update_vars'   => $update_vars,
                ]);

                // go to the home
                return $this->app->redirect($this->app->url('admin-edit-auction-confirm', ['auctionId' => $auctionId]), 303);

            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (FormException $e) {
                $error = $e->getDisplayErrorsAsHTML();
            }
        } else {
            // load default data from existing auction
            $submitted_data = $this->formatAuctionAsDefaultData($auction);

            $validator = new Validator($this->buildAuctionAdminSpec());
        }

        return $this->renderTwig('admin/auction/edit-auction.twig', [
            'auctionId'     => $auction['id'],
            'submittedData' => isset($submitted_data) ? array_merge($validator->getDefaultValues(), $submitted_data) : $validator->getDefaultValues(),
            'error'         => isset($error) ? $error : null,
        ]);
    }

    public function editAuctionConfirmAction(Request $request, $auctionId) {
        $auction = $this->auction_manager->findById(intval($auctionId));
        if (!$auction) { throw new Exception("Auction not found for id $auctionId", 1); }

        return $this->renderTwig('admin/auction/edit-auction-confirm.twig', [
            'auctionId'     => $auction['id'],
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
                // 'validation' => v::string()->length(1,4000,true),
                'sanitizer' => function($v) { return trim($v); },
                'error'     => 'Please enter an auction description up to 4,000 characters long.',
            ],
            'startDate' => [
                'name'      => 'startDate',
                'label'     => 'Auction Start',
                'default'   => date("m.d.Y g:00 a", time()+3600+900),
                // 'validation' => v::int()->min(time() - 60,true),
                'sanitizer' => function($v, $d) { return ($_d = \DateTime::createFromFormat('m.d.Y g:i a', $v, new \DateTimeZone($this->calulcateTimezone($d['timezone'])))) ? $_d->getTimestamp() : 0; },
                'error'     => 'Please enter a start date between today and 30 days from now.',
            ],
            'endDate' => [
                'name'      => 'endDate',
                'label'     => 'Auction End',
                'default'   => date("m.d.Y g:00 a", time()+3600+900 + 86400*7),
                // 'validation' => v::int()->min(strtotime('+1 day -1 minute'), true)->max(strtotime('+30 days'), true),
                'sanitizer' => function($v, $d) { return ($_d = \DateTime::createFromFormat('m.d.Y g:i a', $v, new \DateTimeZone($this->calulcateTimezone($d['timezone'])))) ? $_d->getTimestamp() : 0; },
                'error'     => 'Please enter an end date between 24 hours and 30 days from now.',
            ],

            'minStartingBid' => [
                'name'      => 'minStartingBid',
                'label'     => 'Minimum Starting Bid',
                'default'   => '',
                // 'validation' => v::int()->min(CurrencyUtil::numberToSatoshis(1000), true),
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

            'longTimezone' => [
                'name'      => 'longTimezone',
                'label'     => 'Long Timezone',
                'default'   => '',
                'validation' => v::string(),
                'sanitizer'  => Sanitizer::trim(),
                'error'      => 'timezone (long) was invalid.',
            ],

        ]);

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

    protected function formatAuctionAsDefaultData($auction) {
        $out = [];
        $vars = (array)$auction;
        $out = $vars;

        for ($i=0; $i <= 9; $i++) { 
            $should_add = isset($vars['prizeTokensRequired'][$i]);
            if ($should_add) {
                $token = $vars['prizeTokensRequired'][$i];
                $out['winningTokenType_'.$i.''] = $token['token'];
                $out['winningTokenQuantity_'.$i.''] =  CurrencyUtil::satoshisToUnFormattedNumber($token['amount']);
            }
        }
        unset($out['prizeTokensRequired']);
#        Debug::trace("\$vars=\n".json_encode($vars, 192),__FILE__,__LINE__,$this);
#        Debug::trace("\$out=\n".json_encode($out, 192),__FILE__,__LINE__,$this);

        // fix dates
        $out['startDate'] = $this->formatDateForOutput($out['startDate'], $vars['timezone']);
        $out['endDate'] = $this->formatDateForOutput($out['endDate'], $vars['timezone']);

        // fix currencies
        $out['minStartingBid'] = CurrencyUtil::satoshisToUnFormattedNumber($vars['minStartingBid']);

        return $out;

    }

    protected function formatDateForOutput($ts, $timezone) {
        $d = new \DateTime();
        $d->setTimestamp($ts);
        $d->setTimezone(new \DateTimeZone($this->calulcateTimezone($timezone)));
        return $d->format('m.d.Y g:i a');
    }

    protected function calulcateTimezone($timezone_in) {
        $timezone = preg_replace('/[^0-9-]/', '', $timezone_in) * 36;
        return timezone_name_from_abbr(null, $timezone, date('I', time()));

    }


    protected function formatPrizeTokensForNewAuction($auction_edit_vars) {
        $prize_tokens = [];
        for ($i=0; $i <= 9; $i++) { 
            $should_add = (isset($auction_edit_vars['winningTokenType_'.$i.'']) AND strlen(trim($auction_edit_vars['winningTokenType_'.$i.''])));
            if ($should_add) {
                $entry = [
                    'amount' => $auction_edit_vars['winningTokenQuantity_'.$i.''],
                    'token'  => $auction_edit_vars['winningTokenType_'.$i.''],
                ];
                $prize_tokens[] = $entry;
            }
            unset($auction_edit_vars['winningTokenType_'.$i.'']);
            unset($auction_edit_vars['winningTokenQuantity_'.$i.'']);
        }
        $auction_edit_vars['prizeTokensRequired'] = $prize_tokens;
        return $auction_edit_vars;
    }

    protected function calculatePreviousAndUpdateVars($auction, $auction_edit_vars) {
        $old_vars = [];
        $update_vars = [];
        foreach($auction_edit_vars as $new_var => $new_value) {
            $old_value = $auction[$new_var];
            $changed = false;
            if (is_numeric($new_value) AND is_numeric($old_value)) {
                $changed = ($new_value != $old_value);
            } else {
                $changed = ($new_value !== $old_value);
            }
            if ($changed) {
                $old_vars[$new_var] = $old_value;
                $update_vars[$new_var] = $new_value;
            }
        }
        return [$old_vars, $update_vars];
    }

}

