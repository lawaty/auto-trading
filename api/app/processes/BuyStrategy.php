<?php

require_once __DIR__ . "../../../autoload.php";

class BuyStrategy
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
        $stocks = $this->stock_monitor->getStocks($this->args['sid'], StockMonitor::BUY);
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

    $this->trade_station = new TradeStation('buy');
    $this->config = new Config;

    $this->stock_monitor = new StockMonitor($this->args['sid']);
    $this->reset();
  }

  private function processStocks()
  {
    $failed = [];
    $start = time();

    $log_dir = __DIR__ . '/logs/buy/' . (new Ndate)->format();
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
        $order = $this->trade_station->getOrder($stock['limitbuy_order_id']);
        $status = $order['Status'];
        echo "Market BUY {$stock['symbol']}: $status\n";

        if ($status == 'FLL') {
          $stock['price'] = round($order['FilledPrice'], 2);
          echo "Filled with total price of $" . $stock['price'] * $order['Legs'][0]['QuantityOrdered'] . "\n";
          StockLogger::logStock('Buy', "Filled Market Buy", $stock);

          $process = new Process("limitSell", $stock['log']);
          $process->passArgs([
            ...$stock,
            ...$this->args
          ], true);
          if ($process->run(Process::BACKGROUND) === 1)
            echo 'Started Sequence For ' . $stock['symbol'] . "\n";
          unset($this->stocks[$i]);
        } else if ($status == 'ACK' || $status == 'FPR') {
          // silence
        } else if ($status == 'REJ') {
          echo $order['RejectReason'] . "\n";
          $this->stocks[$i]['price'] = $this->trade_station->getStockEstimatedPrice($stock['symbol'], 'Market', 'BUY');

          echo "{$stock['symbol']}'s price = {$this->stocks[$i]['price']}\n";
          try {
            $this->stocks[$i]['limitbuy_order_id'] = $this->trade_station->placeOrder($stock, 'Market', 'BUY');
          } catch (InsufficientMoney $e) {
            StockLogger::logStock('buy', "Insufficient Money Limit BUY", $stock);
          }
        } else {
          $failed[$stock['limitbuy_order_id']] = $stock;
          unset($this->stocks[$i]);
        }
      }
      echo "\n";

      sleep(5);
    } while (count($this->stocks) && time() - $start < 60 * 60);

    if (count($failed)) {
      foreach ($failed as $order_id => $order) {
        // var_dump($this->trade_station->cancel($order_id));
        StockLogger::logStock('Buy', 'Cancelled Limit BUY', $order);
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

    echo "Fetching Stocks...";
    $this->stocks = $this->fetchStocks();
    $this->removeStocks($this->config['globals']['excluded']);

    $stock_symbols = implode(', ', array_column($this->stocks, 'symbol'));
    echo "All stocks: {$stock_symbols}\n\n";

    //////////////////////////// Buying stocks and market init
    print_r("\nStage 0: Market Limit BUY all stocks\n");

    // Offensive Buy
    $buyers = [];
    foreach ($this->stocks as $i => $stock) {
      $stock['price'] = $this->trade_station->getStockEstimatedPrice($stock['symbol'], 'Market', 'BUY');

      $buyer = new Trade($this->trade_station, $stock['symbol'], "Market", "BUY");
      $buyer->setBudget($budget_per_trade);
    }

    $this->stocks = [];
    // Keep buying till you no longer have money to buy more
    while (count($buyers)) {
      foreach ($buyers as $i => $buyer) {
        if (!$buyer->buy()) { // Couldn't buy means full budget has been spent
          $this->stocks[] = $buyer->getStock();
          unset($buyers[$i]);
        }
      }

      $filled_count = 0;
      while($filled_count != count($buyers)) {
        foreach($buyers as $buyer) {
          if($buyer->isFilled())
            $filled_count += 1;
        }
      }
    }

    echo "Remaining Buying Power: " . $this->trade_station->getBuyingPower() . "\n";

    echo "Confirming Limit BUY...\n";

    $this->processStocks();
  }
}

class OrderDoesNotExist extends Exception
{}

$strategy = new BuyStrategy(json_decode($argv[1], true));
$strategy->run();
