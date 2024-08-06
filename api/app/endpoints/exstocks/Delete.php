<?php

class Delete extends Authenticated
{
    public function __construct() {
        $this->init([
            'stock_id' => [true,Regex::ID]
        ],$_POST);
    }
    public function handle() : Response
    {
        $ex_stock = new UnwantedStock($this->request['stock_id']);
        if($ex_stock->delete())
            return new Response("Deleted");
        else 
            return new Response("An error has occured");
    }
}