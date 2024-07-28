<?php

class BuyStrategy
{
    private TradeStation $tradeStation;
    private $buy_filter_stocks;
    public function __construct($buy_filter_stocks, $tradeStation)
    {
        $this->tradeStation = $tradeStation;
        $this->buy_filter_stocks = $buy_filter_stocks;
    }
    public function buy()
    {
        $res = $this->tradeStation->order($this->buy_filter_stocks, "Limit", "BUY");

        StockLogger::logStock('Buy', "Limit BUY", ...$this->buy_filter_stocks);

        return $res;
    }
    public function stopLoss($buy_stock, $dec_pct)
    {
        $this->tradeStation->stopLoss([$buy_stock], $dec_pct, "StopMarket", "SELL");
        StockLogger::logStock('Buy', "StopMarket SELL", $buy_stock);

        $index = array_search($buy_stock, $this->buy_filter_stocks);

        if ($index !== false) {
            array_splice($this->buy_filter_stocks, $index, 1);
        }
        var_dump("Buy stocks array after stopping loss");
        // print_r($this->buy_filter_stocks);
    }
    public function sell($stock)
    {
        $stock_array = [$stock];
        $this->tradeStation->order($stock_array, "Limit", "SELL");
        StockLogger::logStock('Buy', "Limit SELL", $stock);

        $index = array_search($stock, $this->buy_filter_stocks);

        if ($index !== false) {
            array_splice($this->buy_filter_stocks, $index, 1);
        }
        var_dump("Buy stocks array after selling");
        // print_r($this->buy_filter_stocks);
    }
    public function cleanseStocks(array $stocks)
    {
        $this->tradeStation->order($stocks, "Market", "SELL");
        StockLogger::logStock('Buy', "Market SELL", ...$stocks);

    }
    public function getIncreasePercentage($stocks, $callback)
    {
        $this->tradeStation->getChange($stocks, $callback);
    }
}
