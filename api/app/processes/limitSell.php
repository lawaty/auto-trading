<?php

const CLOSING_TOLERANCE = 30; // mins

require_once __DIR__ . "../../../autoload.php";

class Sequence
{
  private array $stock;
  private Config $config;
  private TradeStation $trade_station;

  private Ndate $bell;
  private array $stages;
  private float $danger;

  private function parseArgs($argv)
  {
    $stock = [];
    for ($i = 0; $i < count($argv); $i += 2)
      $stock[$argv[$i]] = $argv[$i + 1];

    return $stock;
  }

  public function __construct(array $argv)
  {
    $this->stock = $this->parseArgs($argv);

    $this->config = new Config;
    $this->bell = new Ndate($this->config['globals']['until']);
    $this->trade_station = new TradeStation("buy");

    $this->danger = $this->config['buy']['stop_loss_percent'];
    $this->stages = $this->config['buy']['sequences'];
  }

  public function run(): void
  {
    //////////////////////////// Trading Sequence
    $is_filled = false;
    foreach ($this->stages as $i => $stage_config) {
      echo "\nStage $i: {$stage_config['percent']} limit\n";
      $this->stock['quantity'] = $this->trade_station->getExecQuantity($this->stock['limitbuy_order_id']);

      if (!isset($this->stock['stoploss_id'])) {
        [$this->stock['stoploss_id'], $this->stock['limitsell_id']] = $this->trade_station->placeOCO($this->stock, [[
          'percent' => $this->danger,
          'order_type' => 'StopMarket',
          'trade_action' => 'SELL'
        ], [
          'percent' => $stage_config['percent'],
          'order_type' => 'Limit',
          'trade_action' => 'SELL'
        ]]);
      } else if ($order_id = $this->trade_station->editOrder($this->stock, $this->stock['limitsell_id'], [
        'order_type' => 'Limit',
        'trade_action' => 'SELL',
        'percent' => $stage_config['percent']
      ]))
        $this->stock['limitsell_id'] = $order_id;

      // busy wait until limit is exceeded for all items or wait_time finishes
      echo "Waiting till limit filled or " . $stage_config['wait_time'] . " mins pass\n";
      $is_filled = $this->waitFilling($stage_config['wait_time'] * 60);

      if ($is_filled || $this->aboutToClose())
        break;
    }

    if (!$is_filled) {
      $this->stock['quantity'] = $this->trade_station->getExecQuantity($this->stock['limitbuy_order_id']);

      StockLogger::logStock(
        "Buy",
        "Cancel OCO",
        [
          ...$this->stock,
          'OrderID' => -1,
          'price' => '-'
        ]
      );

      $this->trade_station->cancel($this->stock['limitsell_id']);
      $this->trade_station->cancel($this->stock['stoploss_id']);
      $this->trade_station->placeOrder($this->stock, 'Market', 'SELL');
      echo "Failed to reach any of the limits, Selling anyways\n";
    } else {
      StockLogger::logStock(
        "Buy",
        "OCO Limit " . $is_filled[0],
        [
          ...$this->stock,
          'OrderID' => -1,
          'price' => $is_filled[2]
        ]
      );

      StockLogger::logStock(
        "Buy",
        "StopLoss " . $is_filled[1],
        [
          ...$this->stock,
          'OrderID' => -1,
          'price' => '-'
        ]
      );
    }

    echo "Quitting, Bye!";
  }

  private function aboutToClose()
  {
    return (new Ndate)->minutesUntil($this->bell) < CLOSING_TOLERANCE;
  }


  private function waitFilling(int $secs)
  {
    $start = time();
    while (time() - $start < $secs) {
      $limit_order = $this->trade_station->getOrder($this->stock['limitsell_id']);
      $limit_status = $limit_order['Status'];
      $stop_status = $this->trade_station->getOrderStatus($this->stock['stoploss_id']);

      echo "Limit Sell: $limit_status \n";
      echo "StopLoss: $stop_status \n";

      if ($limit_status == 'FLL' || $limit_status == 'REJ' || $limit_status == 'FPR' || $stop_status == 'FLL' || $stop_status == 'REJ')
        return [$limit_status, $stop_status, $limit_order['FilledPrice'] ?? -1];

      if ($this->aboutToClose())
        return false;

      sleep(60 * 2);
    }
    return false;
  }
}

$sequence = new Sequence(array_slice($argv, 1));
$sequence->run();