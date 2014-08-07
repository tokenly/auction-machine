<?php

namespace LTBAuctioneer\Util\Retry;

use LTBAuctioneer\Debug\Debug;
use Exception;

/*
* RetryController
*/
class RetryController
{

    public $total_attempts = 10;
    public $sleep_delay    = 1000000; // 1 sec
    public $backoff        =  250000; // 0.25 sec

    // $result = RetryController::retry(function($offset) {
    //    // do something
    //    return true;
    // });
    public static function retry($function, $exception_handler=null) {
        $retry = new self();
        return $retry->attempt($function, $exception_handler);
    } 


    public function __construct()
    {
    }

    // $retry = new RetryController();
    // $result = $retry->attempt(function($offset) {
    //    // do something
    //    return true;
    // });
    public function attempt($function, $exception_handler=null)
    {
        $errors = array();
        for ($i=0; $i < $this->total_attempts; $i++) { 
            try {
                return $function($i);
            } catch (Exception $e) {
                if ($exception_handler !== null) {
                    $exception_handler($e);
                } else {
                    $errors[] = $this->exceptionString($e);
                    Debug::errorTrace("ERROR: (attempt ".($i+1)." of {$this->total_attempts}) ".$e->getMessage()."\n".Debug::formatDebugBacktrace(debug_backtrace()),__FILE__,__LINE__,$this);
                }
            }

            usleep($this->sleep_delay + ($this->backoff * $i));
        }

        throw new Exception("Failed to execute function.".($errors ? "\nErrors:\n".implode("\n", $errors) : ''), 1);
        
    }

    protected function exceptionString($e) {
        return $e->getMessage().' ('.$e->getCode().') at '.basename($e->getFile()).', line '.$e->getLine();
    }

}

