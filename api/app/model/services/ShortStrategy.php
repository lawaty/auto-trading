<?php

class ShortStrategy
{
    private TradeStation $tradeStation;
    private $sell_filter_stocks;
    public function __construct($sell_filter_stocks, $tradeStation)
    {
        $this->tradeStation = $tradeStation;
        $this->sell_filter_stocks = $sell_filter_stocks;
    }

    public function stopLoss($sell_stock, $dec_pct)
    {
        $this->tradeStation->stopLoss([$sell_stock], $dec_pct, "StopMarket", "SELL");
        $index = array_search($sell_stock, $this->sell_filter_stocks);

        if ($index !== false) {
            array_splice($this->sell_filter_stocks, $index, 1);
        }
        var_dump("Buy stocks array after stopping loss");
        print_r($this->sell_filter_stocks);
    }
    public function sell($stock)
    {
        $stock_array = [$stock];
        $this->tradeStation->order($stock_array, "Limit", "SELL");
        $index = array_search($stock, $this->sell_filter_stocks);

        if ($index !== false) {
            array_splice($this->sell_filter_stocks, $index, 1);
        }
        var_dump("Buy stocks array after selling");
        print_r($this->sell_filter_stocks);
    }
    public function cleanseStocks()
    {
        $this->tradeStation->order($this->sell_filter_stocks, "Market", "SELL");
    }
    public function getDecreasePercentage($stocks, $callback)
    {
        $this->tradeStation->getChange($stocks, $callback);
    }
}
