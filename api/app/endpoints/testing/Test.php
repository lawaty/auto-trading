<?php

require_once MODEL_DIR . "/mocks/DemoTradeStation.php";

class Test extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $stock_monitor = new StockMonitor;
    $stocks = $stock_monitor->getStocks(86299, 'short', null, null ,null, 'DESC');
    return new Response(array_column($stocks, 'symbol'));
  }
}
