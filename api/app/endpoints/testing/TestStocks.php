<?php

class TestStocks extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $stock_monitor = new StockMonitor();
    $stock_monitor->initFilter(StockMonitor::BUY);
    return new Response($stock_monitor->getStocks(StockMonitor::BUY));
  }
}
