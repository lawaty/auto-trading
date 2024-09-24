<?php

class Add extends Authenticated
{
    public function __construct() {
        $this->init([
            'symbol' => [true, Regex::generic(1,10)],
            'type' => [false, Regex::generic(1,100)]
            ],$_POST);
    }
    public function handle() : Response
    {
        $added_stock = UnwantedStockMapper::create($this->request);
        if($added_stock)
            return new Response($added_stock->get('id'));
        else
            return new Response("An error has occured");
    }
}