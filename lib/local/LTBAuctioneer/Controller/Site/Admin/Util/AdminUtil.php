<?php

namespace LTBAuctioneer\Controller\Site\Admin\Util;

use Exception;
use LTBAuctioneer\Currency\CurrencyUtil;
use LTBAuctioneer\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;

/*
* AdminUtil
*/
class AdminUtil
{

    ////////////////////////////////////////////////////////////////////////

    public static function getFormData($form_spec, Request $request) {
        $data = [];
        foreach($form_spec['fields'] as $entry) {
            if (!isset($entry['function']) OR !$entry['function']) { continue; }

            $name = $entry['name'];
            $value = $request->query->get($name);
            if ($value === null) {
                if (isset($entry['default']) AND $entry['default']) {
                    $value = $entry['default'];
                }
            }
            $data[$name] = $value;
        }

        return $data;
    }

    public static function findWithFormData($directory, $form_spec, $form_data) {
        $query = [];
        $sort = $form_spec['sort'];
        $limit = null;

        if (isset($form_data['limit'])) { $limit = $form_data['limit']; }

        foreach($form_spec['fields'] as $entry) {
            if (!isset($entry['function']) OR !$entry['function']) { continue; }
            $name = $entry['name'];
            $value = isset($form_data[$name]) ? $form_data[$name] : null;
            if ($entry['function'] == 'query') {
                // $use_regex = (isset($entry['regex']) ? $entry['regex'] : false);
                // if ($use_regex) {
                //     $value = new \MongoRegex("/".preg_quote($value)."/i"); 
                // }
                $query[$name] = $value;
            }
        }

#        Debug::trace("\$query=".Debug::desc($query)." \$limit=".Debug::desc($limit)."",__FILE__,__LINE__,$this);
        return self::iterateWithFormatting($directory->find($query, $sort, $limit));
    }

    public static function defaultFormSpec($overrides=[]) {
        return array_merge([
            'fields' => [
                'intro_spacer' => [
                    'type' => 'spacer',
                    'size' => '8',
                ],
                'limit' => [
                    'type'     => 'text',
                    'function' => 'limit',
                    'name'     => 'limit',
                    'label'    => 'Limit',
                    'size'     => '3',
                    'default'  => 25,
                ],
            ],
            'sort' => ['timestamp' => -1],
        ], $overrides);
    }

    public static function iterateWithFormatting($result_set) {
        foreach($result_set as $raw_model) {
#            Debug::trace("\$raw_model=".Debug::desc($raw_model)."",__FILE__,__LINE__,$this);
            $model = self::formatModel($raw_model);

            yield $model;
        }
    }

    public static function formatModel($model, $parent=null) {
        $out = array();

        foreach($model as $k => $v) {
            $is_date_field = false;
            if ($k === 'timestamp') { $is_date_field = true; }
            if (substr($k, -4) === 'Date') { $is_date_field = true; }
            if ($is_date_field AND is_numeric($v)) {
#                Debug::trace("k=$k v=".Debug::desc($v)."",__FILE__,__LINE__,$this);
                $v = date("Y-m-d H:i:s T", $v)." ($v)";
            }

            $is_amount_field = false;
            if ($k === 'amount') { $is_amount_field = true; }
            if (in_array($k, ['prebid','live','late',])) { $is_amount_field = true; }
            if ($is_amount_field AND is_numeric($v)) {
                $v = CurrencyUtil::satoshisToNumber($v);
            }

            // recurse
            if (is_array($v) OR is_object($v)) {
                $v = self::formatModel($v, $k);
            }

            // if ($v instanceof \MongoDate) {
            //     $v = date("Y-m-d H:i:s T", $v->sec);
            //     $model[$k] = $v;
            // }
            // if ($v instanceof \MongoId) {
            //     $v = (string)$v;
            //     $model[$k] = $v;
            // }

            $out[$k] = $v;
        }

        if (empty($out)) { $out = new \stdClass(); }
        return $out;
    }
}

