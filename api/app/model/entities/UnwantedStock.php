<?php

class UnwantedStock extends Entity
{
    protected string $symbol;
    protected string $type;

    public static function updateJSON() {
        $all_excluded = UnwantedStockMapper::getAll();
        $excluded_symbols = [];
        foreach($all_excluded as $excluded){
            echo "{$excluded['symbol']} <br>";
            $excluded_symbols[] = $excluded['symbol'];
        }

        $config = new Config;
        $globals = $config['globals'];
        $globals['excluded'] = $excluded_symbols;
        $config['globals'] = $globals;
        $config->save();
    }
}
