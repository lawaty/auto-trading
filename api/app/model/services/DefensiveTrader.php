<?php

/**
 * Defensive Trader here is a basic implementation for a trader that tries to eliminate any rejections during the buy
 */

class DefensiveTrader
{
  const REDUCTION_FACTOR = 0.99;

  private TradeStation $trade_station;
  private array $stock;
  private string $order_type;
  private string $action_type;
  private float $budget;
  private ?int $max_quantity = null;
  private int $quantity = 0;
  private ?int $failed_quantity = null;
  private ?int $order_id = null;
  private int $sid;
  private float $filled_price = 0;

  public function __construct(TradeStation $trade_station, array $stock, string $order_type, string $action_type, int $sid)
  {
    $this->trade_station = $trade_station;
    $this->stock = $stock;
    $this->order_type = $order_type;
    $this->action_type = $action_type;
    $this->stock['price'] = $this->trade_station->getStockEstimatedPrice($stock['symbol'], $this->order_type, $this->action_type);
    $this->sid = $sid;
  }

  public function changeStock()
  {
    $stock_monitor = new StockMonitor;
    $symbol = $stock_monitor->getStocks($this->sid, $this->action_type == 'BUY' ? 'buy' : 'short', 1, [$this->stock['symbol']])[0]['symbol'];
    $this->stock = $this->trade_station->getStock($symbol);
    $this->stock['symbol'] = $symbol;
    $this->stock['price'] = $this->trade_station->getStockEstimatedPrice($symbol, $this->order_type, $this->action_type);

    echo "Switched buyer to stock {$this->stock['symbol']}\n";
  }

  public function setMaxQuantity(int $quantity)
  {
    $this->max_quantity = $quantity;
  }

  public function setBudget(float $budget)
  {
    $this->budget = $budget;
  }

  public function run(): void
  {
    $cache = ArteCache::getInst();
    $is_filled = false;
    do {
      $buying_power = $cache->get('buying_power', [], true);
      if ($buying_power < $this->budget)
        $this->budget = $buying_power;

      $budget = min($this->budget, $buying_power) * self::REDUCTION_FACTOR;
      echo "Buying Power: $buying_power, Budget: {$this->budget}\n";

      $this->quantity = floor($budget / $this->stock['price']);
      if ($this->max_quantity && $this->max_quantity < $this->quantity)
        $this->quantity = $this->max_quantity;

      if (isset($this->failed_quantity))
        $this->quantity = min($this->quantity, floor($this->failed_quantity * self::REDUCTION_FACTOR));

      if ($this->quantity < 1)
        throw new InsufficientMoney;

      echo "Spending $budget to {$this->action_type} {$this->quantity} stocks...\n";

      $this->order_id = $this->trade_station->placeOrder($this->stock, $this->order_type, $this->action_type, $this->quantity);

      if (!$this->order_id)
        throw new OrderFailed("{$this->order_type} {$this->action_type} Order Not Set");

      $this->stock['order_id'] = $this->order_id;

      $order_processed = false;
      while (!$order_processed) {
        $start = microtime(true);
        $order = $this->trade_station->getOrder($this->order_id);
        echo "{$this->action_type} Status: {$order['Status']}\n";

        if ($order['Status'] == 'FLL') {
          $is_filled = true;
          $this->stock['price'] = $order['FilledPrice'];
          $this->filled_price = $order['FilledPrice'] * $this->quantity;

          echo "{$this->action_type} Filled With Price: {$this->stock['price']} to spend a total of {$this->filled_price}\n";

          self::addtoCurrentlyTrading($this->stock['symbol']);

          $order_processed = true;
        } else if ($order['Status'] == 'REJ') {
          echo "{$this->action_type} Rejected because: {$order['RejectReason']}\n";
          preg_match('/current Buying Power values of \$([-\d,\.]+) for Day Trade and \$([-\d,\.]+) for Overnight Buying Power./', $order['RejectReason'], $buying_power_matches);

          if (isset($buying_power_matches[1])) {
            $this->failed_quantity = $this->quantity;
            $this->budget = (int) str_replace(',', '', $buying_power_matches[1]) * self::REDUCTION_FACTOR;
          } else
            throw new Rejected;

          $order_processed = true;
        }

        sleep(max(3 - (microtime(true) - $start), 0));
      }

      sleep(1);

      echo "\n";
    } while (!$is_filled);

    echo "\n";
  }

  public function getStock()
  {
    return $this->stock;
  }

  public function getQuantity(): int
  {
    return $this->quantity;
  }

  public function getFilledPrice(): float
  {
    return $this->filled_price;
  }

  public static function addtoCurrentlyTrading(string $symbol): void
  {
    // Mutex Lock
    while (file_exists(TMP_DIR . '/currently_trading.lock'))
      sleep(1);
    touch(TMP_DIR . '/currently_trading.lock');

    $currently_trading = json_decode(file_get_contents(JSONS_DIR . '/currently_trading.json'), true);
    $currently_trading[] = $symbol;
    file_put_contents(JSONS_DIR . '/currently_trading.json', json_encode($currently_trading));

    unlink(TMP_DIR . '/currently_trading.lock');
  }

  public static function removeFromCurrentlyTrading(string $symbol): void
  {
    // Mutex Lock
    while (file_exists(TMP_DIR . '/currently_trading.lock'))
      sleep(1);
    touch(TMP_DIR . '/currently_trading.lock');

    $currently_trading = json_decode(file_get_contents(JSONS_DIR . '/currently_trading.json'), true);
    @$currently_trading = array_values(array_diff($currently_trading, [$symbol]));
    file_put_contents(JSONS_DIR . '/currently_trading.json', json_encode($currently_trading));

    unlink(TMP_DIR . '/currently_trading.lock');
  }

  public static function emptyCurrentlyTrading(): void
  {
    file_put_contents(JSONS_DIR . '/currently_trading.json', '[]');
  }
}

class OrderFailed extends Exception {}

class Rejected extends Exception {}
