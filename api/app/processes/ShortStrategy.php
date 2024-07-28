<?php

require_once __DIR__ . "../../../autoload.php";

class ShortStrategy
{
  private TradeStation $trade_station;
  private StockMonitor $stock_monitor;
  private array $stocks;

  private Config $config;

  private function fetchStocks()
  {
    do {
      $stocks = $this->stock_monitor->initFilter(StockMonitor::SELL);
      try {
        $stocks = $this->stock_monitor->getStocks(StockMonitor::SELL);
      } catch (Exception | Error $e) {
        $this->stock_monitor->initFilter(StockMonitor::SELL);
      }
    } while (!$stocks);

    foreach ($stocks as &$stock)
      $stock['price'] = (int)str_replace(',', '', $stock['price']);

    return array_slice($stocks, 0, $this->config['short']['number_of_trades']);
  }

  public function reset()
  {
    $this->config->refresh();
    $this->trade_station->refreshSettings();
  }

  public function __construct()
  {
    $this->trade_station = new TradeStation('short');
    $this->stock_monitor = new StockMonitor;
    $this->config = new Config;
    $this->reset();
  }

  private function processStocks()
  {
    $failed = [];

    $start = time();

    $log_dir = __DIR__ . '/logs/short/' . (new Ndate)->format();
    if(!is_dir($log_dir))
      mkdir($log_dir);

    foreach($this->stocks as $j => $stock) {
      $i = 1;
      while(file_exists($log_dir . '/' . $stock['symbol'] . "-$i.log"))
        $i++;

      $this->stocks[$j]['log'] = $log_dir . '/' . $stock['symbol'] . "-$i.log";
    }

    do {
      foreach ($this->stocks as $i => $stock) {
        $order = $this->trade_station->getOrder($stock['sellshort_order_id']);
        $status = $order['Status'];

        if ($status == 'FLL' || $status == 'FPR') {
          $stock['price'] = round($order['FilledPrice'], 2);
          StockLogger::logStock('Short', "Filled SellShort", $stock);

          $args = [];
          foreach ($stock as $key => $value) {
            $args[] = $key;
            $args[] = $value;
          }

          $process = new Process("limitBuy", $stock['log']);
          $process->passArgs([...$args, 'sellshort_order_id', $stock['sellshort_order_id']]);
          $process->run(Process::BACKGROUND);
          unset($this->stocks[$i]);
        } else if ($status == 'ACK') {
          // silence
        } else {
          $failed[$stock['sellshort_order_id']] = $stock;
          unset($this->stocks[$i]);
        }

        echo "Sell Short {$stock['symbol']}: $status\n";
      }
      echo "\n";

      sleep(5);
    } while (count($this->stocks) && time() - $start < 60 * 60);

    if (count($failed)) {
      foreach ($failed as $order_id => $order) {
        var_dump($this->trade_station->cancel($order_id));
        StockLogger::logStock('Short', 'Cancelled Sell Short', $order);
      }

    }
  }

  private function removeStocks($failures)
  {
    $this->stocks = array_filter($this->stocks, function ($stock) use ($failures) {
      return !in_array($stock['symbol'], $failures);
    });
  }

  public function run(int $wait)
  {
    echo "Trading After: {$wait} mins \n";
    sleep(max($wait * 60, 1));
    $this->stocks = $this->fetchStocks();
    $this->removeStocks($this->config['globals']['excluded']);

    $stock_symbols = implode(', ', array_column($this->stocks, 'symbol'));
    echo "All stocks: {$stock_symbols}\n\n";

    //////////////////////////// Sell Short and market init
    print_r("\nStage 0: Market Sell Short all stocks\n");

    foreach ($this->stocks as $i => $stock) {
      $this->stocks[$i]['sellshort_order_id'] = $this->trade_station->placeOrder($stock, 'Market', 'SELLSHORT');
    }

    echo "Confirming Sellshort...\n";

    $this->processStocks();
  }
}

$strategy = new ShortStrategy;
$strategy->run($argv[1]);