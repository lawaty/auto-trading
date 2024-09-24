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
    $cache = ArteCache::getInst();

    $cache->get('stockmonitor_cookie', [], true);
    $cache->get('conds', [84838], true);
    $cache->get('runId', [84838], true);

    $start = microtime(true);
    $cache->get('stocks', [84838, StockMonitor::BUY]);
    echo microtime(true) - $start;

    prettyPrint($cache->logs);
    return new Response();
  }
}
