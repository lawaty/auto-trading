<?php
class TradingLogger
{

    public static function preProcessStatic()
    {
        $log_file = fopen(LOG_DIR . '/' . "processes/" . ((new Ndate)->format(Ndate::DATE)) . ".txt", 'a');
        return $log_file;
    }
    public static function getCurrentLogFile()
    {
        return LOG_DIR . '/' . "processes/" . ((new Ndate)->format(Ndate::DATE)) . ".txt";
    }
    public static function log($details)
    {
        $log_file = static::preProcessStatic('');
        $details .= ' at ' . ((new Ndate)->format(Ndate::DATE_TIME));
        fwrite($log_file, "$details" . "\n");
    }
}
