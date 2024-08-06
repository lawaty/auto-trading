<?php

require_once __DIR__ . "../../../autoload.php";

class ShortStrategy
{
  private TradeStation $trade_station;
  private StockMonitor $stock_monitor;
  private array $stocks;

  private array $args;

  private Config $config;

  private function fetchStocks()
  {
    $stocks = null;
    do {
      try {
        $stocks = $this->stock_monitor->getStocks($this->args['sid'], StockMonitor::SELL);
      } catch (Exception | Error $e) {
        echo trace($e);
      }

      if (!$stocks)
        echo "No Stocks Returned. Retrying...\n";
    } while (!$stocks);

    foreach ($stocks as &$stock)
      $stock['price'] = (int)str_replace(',', '', $stock['price']);

    return array_slice($stocks, 0, (int) $this->args['number_of_trades']);
  }
  public function reset()
  {
    $this->config->refresh();
    $this->trade_station->refreshSettings();
  }

  public function __construct(array $run_args)
  {
    $this->args = $run_args;


    $this->trade_station = new TradeStation('short');
    $this->config = new Config;

    $this->stock_monitor = new StockMonitor($this->args['sid']);
    $this->reset();
  }

  private function processStocks()
  {
    $failed = [];
    $start = time();

    $log_dir = __DIR__ . '/logs/short/' . (new Ndate)->format();
    if (!is_dir($log_dir))
      mkdir($log_dir);

    foreach ($this->stocks as $j => $stock) {
      $i = 1;
      while (file_exists($log_dir . '/' . $stock['symbol'] . "-$i.log"))
        $i++;

      $this->stocks[$j]['log'] = $log_dir . '/' . $stock['symbol'] . "-$i.log";
    }

    do {
      foreach ($this->stocks as $i => $stock) {
        $order = $this->trade_station->getOrder($stock['sellshort_order_id']);
        $status = $order['Status'];
        echo "Market SELLSHORT {$stock['symbol']}: $status\n";

        if ($status == 'FLL' || $status == 'FPR') {
          $stock['price'] = round($order['FilledPrice'], 2);
          echo "Filled with total price of $" . $stock['price'] * $order['Legs'][0]['QuantityOrdered'] . "\n";
          StockLogger::logStock('Short', "Filled SellShort", $stock);

          $process = new Process("limitBuy", $stock['log']);
          $process->passArgs([
            ...$stock,
            ...$this->args
          ], true);
          if ($process->run(Process::BACKGROUND) === 1)
            echo 'Started Sequence For ' . $stock['symbol'] . "\n";
          unset($this->stocks[$i]);
        } else if ($status == 'ACK') {
          // silence
        } else if ($status == 'REJ') {
          echo "Retrying...\n";
          $this->stocks[$i]['price'] = $this->trade_station->getStockEstimatedPrice($stock['symbol'], 'Market', 'SELLSHORT');

          echo "{$stock['symbol']}'s price = {$this->stocks[$i]['price']}\n";
          try {
            $this->stocks[$i]['sellshort_order_id'] = $this->trade_station->placeOrder($stock, 'Market', 'SELLSHORT');
          } catch (InsufficientMoney $e) {
            StockLogger::logStock('buy', "Insufficient Money Market SELLSHORT", $stock);
          }
        } else {
          $failed[$stock['sellshort_order_id']] = $stock;
          unset($this->stocks[$i]);
        }
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

    $this->args['number_of_trades'] = count($this->stocks);
  }

  public function run()
  {
    echo "Trading After: {$this->args['trade_after']} mins \n";
    sleep(max($this->args['trade_after'] * 60, 1));
    $this->stocks = $this->fetchStocks();
    $this->removeStocks($this->config['globals']['excluded']);

    $stock_symbols = implode(', ', array_column($this->stocks, 'symbol'));
    echo "All stocks: {$stock_symbols}\n\n";

    //////////////////////////// Sell Short and market init
    print_r("\nStage 0: Market Sell Short all stocks\n");

    echo "Balance: " . $this->trade_station->getBalance() . "\nBuying Power: " . $this->trade_station->getBuyingPower() . "\n";
    $this->trade_station->setTrades($this->args['number_of_trades']);

    foreach ($this->stocks as $i => $stock) {
      $stock['price'] = $this->trade_station->getStockEstimatedPrice($stock['symbol'], 'Market', 'SELLSHORT');
      echo "{$stock['symbol']}'s price = {$stock['price']}\n";

      try {
        $this->stocks[$i]['sellshort_order_id'] = $this->trade_station->placeOrder($stock, 'Market', 'SELLSHORT');
      } catch (InsufficientMoney $e) {
        StockLogger::logStock('buy', "Insufficient Money Market SELLSHORT", $stock);
      }
    }

    echo "Confirming Sellshort...\n";

    $this->processStocks();
  }
}

$strategy = new ShortStrategy(json_decode($argv[1], true));
$strategy->run();
