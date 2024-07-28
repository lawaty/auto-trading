<?php

require_once __DIR__ . "../../../autoload.php";

class BuyStrategy
{
  private TradeStation $trade_station;
  private StockMonitor $stock_monitor;
  private array $stocks;

  private int $sid;
  private int $wait;

  private Config $config;

  private function fetchStocks()
  {
    do {
      $stocks = $this->stock_monitor->initFilter(StockMonitor::BUY);
      try {
        $stocks = $this->stock_monitor->getStocks(StockMonitor::BUY);
      } catch (Exception | Error $e) {
        $this->stock_monitor->initFilter(StockMonitor::BUY);
      }
    } while (!$stocks); 

    foreach ($stocks as &$stock)
      $stock['price'] = (int)str_replace(',', '', $stock['price']);

    return array_slice($stocks, 0, $this->config['buy']['number_of_trades']);
  }

  public function reset()
  {
    $this->config->refresh();
    $this->trade_station->refreshSettings();
  }

  public function __construct(array $run_args)
  {
    $this->sid = $run_args[0];
    $this->wait = $run_args[1];

    $this->trade_station = new TradeStation('buy');
    $this->config = new Config;
    
    $this->stock_monitor = new StockMonitor($this->sid);
    $this->reset();
  }

  private function processStocks()
  {
    $failed = [];
    $start = time();

    $log_dir = __DIR__ . '/logs/buy/' . (new Ndate)->format();
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
        $order = $this->trade_station->getOrder($stock['limitbuy_order_id']);
        $status = $order['Status'];

        if ($status == 'FLL' || $status == 'FPR') {
          $stock['price'] = round($order['FilledPrice'], 2);
          StockLogger::logStock('Buy', "Filled Market Buy", $stock);

          $args = [];
          foreach ($stock as $key => $value) {
            $args[] = $key;
            $args[] = $value;
          }


          $process = new Process("limitSell", $stock['log']);
          $process->passArgs([...$args, 'limitbuy_order_id', $stock['limitbuy_order_id']]);
          $process->run(Process::BACKGROUND);
          unset($this->stocks[$i]);
        } else if ($status == 'ACK') {
          // silence
        } else {
          $failed[$stock['limitbuy_order_id']] = $stock;
          unset($this->stocks[$i]);
        }

        echo "Limit BUY {$stock['symbol']}: $status\n";
      }
      echo "\n";

      sleep(5);
    } while (count($this->stocks) && time() - $start < 60 * 60);

    if (count($failed)) {
      foreach ($failed as $order_id => $order) {
        var_dump($this->trade_station->cancel($order_id));
        StockLogger::logStock('Buy', 'Cancelled Limit BUY', $order);
      }
    }
  }
  

  private function removeStocks($failures)
  {
    $this->stocks = array_filter($this->stocks, function ($stock) use ($failures) {
      return !in_array($stock['symbol'], $failures);
    });
  }

  public function run()
  {
    echo "Trading After: {$this->wait} mins \n";
    sleep(max($this->wait * 60, 1));
    $this->stocks = $this->fetchStocks();
    $this->removeStocks($this->config['globals']['excluded']);

    $stock_symbols = implode(', ', array_column($this->stocks, 'symbol'));
    echo "All stocks: {$stock_symbols}\n\n";

    //////////////////////////// Buying stocks and market init
    print_r("\nStage 0: Market Limit BUY all stocks\n");

    foreach ($this->stocks as $i => $stock) {
      $this->stocks[$i]['limitbuy_order_id'] = $this->trade_station->placeOrder($stock, 'Market', 'BUY');
    }

    echo "Confirming Limit BUY...\n";

    $this->processStocks();
  }
}

$strategy = new BuyStrategy($argv);
$strategy->run();