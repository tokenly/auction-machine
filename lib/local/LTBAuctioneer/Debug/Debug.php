<?php

namespace LTBAuctioneer\Debug;

/* 
* Debug
* __description__
*/
class Debug {

    const DEBUG    = 100;
    const INFO     = 200;
    const WARNING  = 300;
    const ERROR    = 400;
    const CRITICAL = 500;
    const ALERT    = 550;

    static $MONOLOG;

    //////////////////////////////////////////////////////////////////////////////////////
    // Class Methods
    //////////////////////////////////////////////////////////////////////////////////////

    public static function initMonolog($monolog) {
        self::$MONOLOG = $monolog;
    }


    /**
    * displays trace output to the screen.
    *
    * @param string $text the trace text
    * @param string $var an optional array for a var dump
    * @param string $file the calling file
    * @param string $line the calling line number
    * @param object $calling_object the class calling trace
    * @return void nothing
    */
    static public function trace() {
        // write with monolog
        if (self::$MONOLOG) { return self::logToMonolog(self::DEBUG, func_get_args()); }

        $msg = self::buildDebugMessage(func_get_args());

        $fd = fopen(BASE_PATH.'/var/log/trace.log', 'a');
        fwrite($fd, $msg);
        fclose($fd);
    }
    
    static public function desc($object) {
        if (is_object($object)) {
                if ($object instanceof \DateTime) {
                        return "{DateTime ".$object->format("Y-m-d H:i:s")."}";
                } else if (method_exists($object, 'desc')) {
                        return $object->desc();
                } else {
                        return '{'.get_class($object).'}';
                }
        } else if (is_string($object)) {
                return "[string ".strlen($object)." \"".self::showInvisibles(self::debugTruncate($object,130))."\"]";
        } else if (is_bool($object)) {
                return ($object ? 'TRUE' : 'FALSE');
        } else if (is_array($object)) {
                $array_count = count($object);
                $array_preview = ($array_count ? ' '.self::showInvisibles(self::debugTruncate(self::_arrayDumpOneLineBody($object),90)) : '');
                return "[array($array_count)$array_preview]";
        } else if (is_integer($object)) {
                return "[int $object]";
        } else if (is_float($object)) {
                return "[float ".sprintf('%1.3f',$object)."]";
        } else {
                return ($object ? "" : "empty ").gettype($object);
        }
    }


    public static function showInvisibles($text) {
        $out = '';
        $length = strlen($text);
        for($i=0;$i<$length;++$i) {
            $char = $text[$i];
            $ord = ord($char);
            if ($ord < 32 OR $ord > 126) {
                $out .= "[$ord]";
            } else {
                $out .= $char;
            }
        }
        return $out;
    }

    public static function formatDebugBacktrace($debug_backtrace) {
        $out = '';
        foreach ($debug_backtrace as $debug_entry) {
                $out .= "\n";
                $out .= basename(@$debug_entry['file']).", ".@$debug_entry['line'].": ".@$debug_entry['function'];
        }

        return $out."\n";
    }
    

    public static function debug() { return self::logToMonolog(self::DEBUG, func_get_args()); }
    public static function info() { return self::logToMonolog(self::INFO, func_get_args()); }
    public static function warn() { return self::logToMonolog(self::WARNING, func_get_args()); }
    public static function err() { return self::logToMonolog(self::ERROR, func_get_args()); }
    public static function errorTrace() { return self::logToMonolog(self::ERROR, func_get_args()); }
    public static function crit() { return self::logToMonolog(self::CRITICAL, func_get_args()); }
    public static function alert() { return self::logToMonolog(self::ALERT, func_get_args()); }



    //////////////////////////////////////////////////////////////////////////////////////
    // Public Methods
    //////////////////////////////////////////////////////////////////////////////////////


    

    //////////////////////////////////////////////////////////////////////////////////////
    // Private/Protected Methods
    //////////////////////////////////////////////////////////////////////////////////////

    // for debugging
    static protected function debugTruncate($text, $final_length = "45") {
        $show_end_amount = 16;
        $elipsis = '...';
        $strlen = strlen($text); // mb_strlen($text);
        if ($strlen > $final_length - 3) {
                $target_length = $final_length - 3;
                $show_end_amount = min($show_end_amount, floor($target_length/2));
                if ($show_end_amount) {
                        $output = substr($text, 0, $target_length - $show_end_amount) . $elipsis . substr($text, 0 - $show_end_amount); // mb_substr
                } else {
                        $output = substr($text, 0, $target_length - $show_end_amount) . $elipsis; // mb_substr
                }
        } else {
                $output = $text;
        }

        return $output;
    }

    static protected function arrayDumpOneLine($array) {
        if (!is_array($array)) {
                if (is_string($array)) return "\"".$array."\"";
                return self::desc($array);
        }

        return "[".self::_arrayDumpOneLineBody($array)."]";
    }


    static protected function _arrayDumpOneLineBody($array) {
        $out = '';
        foreach ($array as $key => $val) {
                if (is_array($val)) $val = self::arrayDumpOneLine($val);
                else if (is_object($val)) $val = self::desc($val);
                        else $val = "'$val'";
                $out .= ($out ? ", " : "")."'$key' => $val";
        }

        return $out;
    }
    
    static protected function buildDebugMessage($args) {
        $var = null;
        $text = $args[0];
        $count = count($args);
        if ($count == 3) {
                $file = $args[1];
                $line = $args[2];
                $calling_object = false;
        } else if ($count == 4) {
                if (is_array($args[1]) OR is_object($args[1])) {
                        $var = $args[1];
                        $file = $args[2];
                        $line = $args[3];
                        $calling_object = false;
                } else {
                        $file = $args[1];
                        $line = $args[2];
                        $calling_object = $args[3];
                }
        } else {
                $var = $args[1];
                $file = $args[2];
                $line = $args[3];
                $calling_object = $args[4];
        }

        $add_date = true;

        $out = '';
        $out .= "[".getmypid()."]";
        if (is_object($calling_object)) $out .= "[".get_class($calling_object)."]";
         else if (strlen($calling_object)) $out .= "[".$calling_object."]";
         else $out .= "[main]";
        if ($file AND is_string($file)) $out .= "[".basename($file)."]";
         else $out .= "[]";
        $out .= "[$line]";
        // $out .= "[".date("y-m-d H:i:s")."]";
        $out .= "\n";

        $out .= "  ".str_replace("\n","\n  ",$text)."\n";

        if ($var) {
                $out .= "\n".print_r($var, true);
        }

        return $out;
    }

    protected static function logLevelToText($level) {
        $map = array(
                100 => 'DEBUG',
                200 => 'INFO',
                300 => 'WARNING',
                400 => 'ERROR',
                500 => 'CRITICAL',
                550 => 'ALERT',
        );
        return $map[$level];
    }

    protected static function logToMonolog($level, $args) {
        // write with monolog
        if (isset(self::$MONOLOG)) {
                return self::$MONOLOG->addRecord($level, self::buildDebugMessage($args));
        }

        // call trace
        $args[0] = self::logLevelToText($level).': '.$args[0];
        return call_user_func_array(array('self','trace'), $args);
    }

}
