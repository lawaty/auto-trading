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
    $stocks = $stock_monitor->getStocks(84838, StockMonitor::BUY, 200, ['ALTM', 'HOLO', 'ALAB', 'NCLH']);
    return new Response($stocks);
  }
}
