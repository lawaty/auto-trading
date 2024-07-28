<?php

class Test extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
 }

  public function handle(): Response
  {
    $stock_monitor = new StockMonitor;
    $stock_monitor->initFilter(StockMonitor::BUY);
    $stocks = $stock_monitor->getStocks(StockMonitor::BUY);
    return new Response($stocks);
  }
}
