<?php

namespace LTBAuctioneer\EventLog;

use LTBAuctioneer\Debug\Debug;
use Exception;

/*
* EventLog
*/
class EventLog
{

  
    const DEBUG    = 'debug';
    const INFO     = 'info';
    const NOTICE   = 'notice';
    const WARNING  = 'warning';
    const ALERT    = 'alert';
    const ERROR    = 'error';
    const CRITICAL = 'critical';

    static $SHARED_INSTANCE;

    public static function init($shared_instance) {
        self::$SHARED_INSTANCE = $shared_instance;
    }



    public static function logDebug($name, $data_in) {
        return self::writeLog($name, EventLog::DEBUG, $data_in);
    }

    public static function logEvent($name, $data_in) {
        return self::writeLog($name, EventLog::INFO, $data_in);
    }

    public static function logError($name, $data_in) {
        return self::writeLog($name, EventLog::ERROR, $data_in);
    }

    public static function writeLog($name, $level, $data_in) {
        if (!isset(self::$SHARED_INSTANCE)) { return; }
        self::$SHARED_INSTANCE->_writeLog($name, $level, $data_in);
    } 


    public function __construct($log_entry_directory, $debug) {
        $this->log_entry_directory = $log_entry_directory;
        $this->debug = $debug;
    }

    ////////////////////////////////////////////////////////////////////////

    protected function _writeLog($name, $level, $data_in) {
        try {
                
            if ($data_in instanceof Exception) {
                $data = $this->exceptionData($data_in);
            } else if (is_object($data_in)) {
                $data = (array)$data_in;
            } else {
                $data = $data_in;
                if (isset($data['error']) AND $data['error'] instanceof Exception) {
                    $data['error'] = $this->exceptionData($data['error']);
                }
            }

            $create_vars = array(
                'level'     => $level,
                'type'      => $name,
                'timestamp' => time(),
                'data'      => $data,
            );
            $this->log_entry_directory->createAndSave($create_vars);

            if ($this->debug) {
                /* keep */ Debug::trace(json_encode($create_vars, 192),__FILE__,__LINE__,$this);
            }
        } catch (Exception $e) {
            Debug::errorTrace("ERROR: ".$e->getMessage(),__FILE__,__LINE__,$this);    
        }
        return;
    }

    protected function exceptionData($e) {
        return array(
            'message'  => $e->getMessage(),
            'code'     => $e->getCode(),
            'at'       => ''.basename($e->getFile()).', line '.$e->getLine(),
            'location' => $this->buildLocationFromTrace($e->getTrace()),
        );
    }

    protected function buildLocationFromTrace($trace_entries) {
        // last 4
        $entries = array_slice($trace_entries, 0, 8);

        $out = array();
        foreach($entries as $entry) {
            if (!$entry) { continue; }
            $class = ((isset($entry['class']) AND $entry['class'] != '') ? $entry['class'].'->' : '');
            $out[] = (isset($entry['file']) ? basename($entry['file']) : '[no file]').", ".(isset($entry['line']) ? $entry['line'] : '[no line]').": ".$class.(string)$entry['function'];
        }

        return $out;
    }

}