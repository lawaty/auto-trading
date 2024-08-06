<?php

class Get extends Authenticated 
{
    public function __construct() {
        $this->init([
            's' => [false, Regex::generic(1,50)]
        ],$_POST);
    }
    public function handle() :Response
    {
        $ex_stocks = UnwantedStockMapper::getAll([]);
        return new Response($ex_stocks);
    }
}