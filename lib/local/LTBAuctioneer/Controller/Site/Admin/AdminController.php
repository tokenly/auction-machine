<?php

namespace LTBAuctioneer\Controller\Site\Admin;

use Exception;
use LTBAuctioneer\Controller\Site\Admin\Util\AdminUtil;
use LTBAuctioneer\Controller\Site\Base\BaseSiteController;
use LTBAuctioneer\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;

/*
* AdminController
*/
class AdminController extends BaseSiteController
{

    public function __construct($app, $log_entry_directory, $auction_directory) {
        parent::__construct($app);

        $this->log_entry_directory = $log_entry_directory;
        $this->auction_directory = $auction_directory;
    }



    ////////////////////////////////////////////////////////////////////////

    public function logsAction(Request $request) {
        $form_spec = AdminUtil::defaultFormSpec(['sort' => ['timestamp' => -1, 'id' => -1],]);
        $form_data = AdminUtil::getFormData($form_spec, $request);

        $entries = [];
        $results = AdminUtil::findWithFormData($this->log_entry_directory, $form_spec, $form_data);
        foreach ($results as $log_entry_model) {
            $entries[] = [
                'title'    => $log_entry_model['type'],
                // 'subtitle' => date("Y-m-d H:i:s T", $log_entry_model['timestamp']),
                'subtitle' => $log_entry_model['timestamp'],
                'data'     => $log_entry_model['data']
            ];
        }
#        Debug::trace("\$entries=".Debug::desc($entries)."",__FILE__,__LINE__,$this);

        return $this->renderTwig('admin/entries/entries.twig', [
            'title'     => 'Logs',
            'form'      => $form_spec,
            'form_data' => $form_data,
            'entries'   => $entries,
        ]);
    }

    public function auctionsAction(Request $request) {
        $form_spec = AdminUtil::defaultFormSpec(['sort' => ['startDate' => -1],]);
        $form_data = AdminUtil::getFormData($form_spec, $request);

        $entries = [];
        $results = AdminUtil::findWithFormData($this->auction_directory, $form_spec, $form_data);
        foreach ($results as $auction) {
            $entries[] = [
                'title'    => $auction['name'],
                // 'subtitle' => date("Y-m-d H:i:s T", $auction['startDate']),
                'subtitle' => $auction['startDate'],
                'data'     => $auction,
            ];
        }
#        Debug::trace("\$entries=".Debug::desc($entries)."",__FILE__,__LINE__,$this);

        return $this->renderTwig('admin/entries/entries.twig', [
            'title'     => 'Auctions',
            'form'      => $form_spec,
            'form_data' => $form_data,
            'entries'   => $entries,
        ]);
    }

    ////////////////////////////////////////////////////////////////////////

}

