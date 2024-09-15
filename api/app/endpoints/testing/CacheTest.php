<?php

require_once MODEL_DIR . "/mocks/DemoTradeStation.php";

class CacheTest extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $trade_station = new DemoTradeStation('buy');

    $cache = ArteCache::getInst();
    $cache->install($trade_station, [
      'today_orders' => ['getTodayOrders', 300],
      'balance' => ['getBalance', 300],
      'buying_power' => 'getBuyingPower',
      'stock' => 'getStock',
      'stock_price' => 'getStockEstimatedPrice',
      'order' => 'getOrder',
      'order_quantity' => 'getExecQuantity'
    ]);

    $start = time();
    $cache->get('today_orders');
    $cache->get('balance');
    $cache->get('buying_power');
    $cache->get('stock', ['RUN']);
    $cache->get('stock_price', ['RUN', 'Market', 'BUY']);
    $taken_time = time() - $start;
    echo "Time Taken without cache is $taken_time<br>";


    $start = time();
    $cache->get('today_orders', [], true);
    $cache->get('balance');
    $cache->get('buying_power');
    $cache->get('stock', ['RUN']);
    $cache->get('stock_price', ['RUN', 'Market', 'BUY']);
    $taken_time = time() - $start;

    echo "Time Taken with cache is $taken_time<br>";

    return new Response;
  }
}
