<?php
if (!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');

class VinttroLogger 
{
    /**
     * Log a fatal message with automated class/method tracing
     */
    public static function fatal($message) 
    {
        self::log('fatal', $message);
    }

    /**
     * Log an info message with automated class/method tracing
     */
    public static function info($message) 
    {
        self::log('info', $message);
    }

    /**
     * Core logging engine utilizing PHP backtrace to identify the caller
     */
    private static function log($level, $message) 
    {
        // Grab only the immediate execution stack frames to keep it ultra-fast
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        
        // $backtrace[0] is this log() method. $backtrace[1] is the method that called us.
        $caller = isset($backtrace[1]) ? $backtrace[1] : null;

        $location = 'Global';
        if ($caller) {
            $class = isset($caller['class']) ? $caller['class'] : '';
            $function = isset($caller['function']) ? $caller['function'] : '';
            $location = $class ? "{$class}::{$function}" : "{$function}";
        }

        // Format the message with your unified VINTTRO tag and the dynamic path
        $formattedMessage = "VINTTRO [{$location}] - {$message}";

        // Pass it to SuiteCRM's native logger engine
        $GLOBALS['log']->$level($formattedMessage);
    }
}