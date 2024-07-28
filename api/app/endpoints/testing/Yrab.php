<?php

class Yrab extends Endpoint
{
    public function __construct()
    {
        $this->init([], $_GET);
    }

    public function handle(): Response
    {
        (new Ndate)->minutesUntil(new Ndate("16:15"));

        $trade_station = new TradeStation("buy");
        $trade_station->editOrder(['sumbol' => 'BFMI'], 40000, [
            'percent' => 0.5,
            'order_type' => 'Limit',
            'trade_action' => 'SELL',
        ]);
        exit;
    }
}
