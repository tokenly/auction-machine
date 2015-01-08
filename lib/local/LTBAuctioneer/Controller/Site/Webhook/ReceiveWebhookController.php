<?php

namespace LTBAuctioneer\Controller\Site\Webhook;

use Exception;
use LTBAuctioneer\Controller\Site\Base\BaseSiteController;
use Symfony\Component\HttpFoundation\Request;
use LTBAuctioneer\Debug\Debug;

/*
* ReceiveWebhookController
*/
class ReceiveWebhookController extends BaseSiteController
{

    ////////////////////////////////////////////////////////////////////////

    public function __construct($app, $webhook_receiver) {
        parent::__construct($app);

        $this->webhook_receiver = $webhook_receiver;
    }


    public function receive(Request $request) {
        $valid = $this->webhook_receiver->validateWebhookNotificationFromRequest($request);
        if (!$valid) { throw new Exception("Invalid request", 1); }

        return 'processed';
    }

    ////////////////////////////////////////////////////////////////////////

}

