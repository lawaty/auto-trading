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
  private int $quantity = 0;
  private ?int $failed_quantity = null;
  private ?int $order_id = null;

  public function __construct(TradeStation $trade_station, string $symbol, string $order_type, string $action_type)
  {
    $this->trade_station = $trade_station;
    $this->stock = $this->trade_station->getStock($symbol);
    $this->order_type = $order_type;
    $this->action_type = $action_type;
    $this->stock['price'] = $this->trade_station->getStockEstimatedPrice($symbol, $this->order_type, $this->action_type);
    $this->stock['symbol'] = $symbol;
  }

  public function setBudget(float $budget)
  {
    $this->budget = $budget;
  }

  public function run(): void
  {
    $is_filled = false;
    do {
      $buying_power = $this->trade_station->getBuyingPower();
      if ($buying_power < $this->budget)
        $this->budget = $buying_power;

      $budget = min($this->budget, $buying_power) * self::REDUCTION_FACTOR;
      echo "Buying Power: $buying_power, Budget: {$this->budget}\n";

      $this->quantity = floor($budget / $this->stock['price']);
      if (isset($this->failed_quantity))
        $this->quantity = min($this->quantity, floor($this->failed_quantity * self::REDUCTION_FACTOR));

      if ($this->quantity < 1)
        throw new InsufficientMoney;

      echo "Spending $budget to {$this->action_type} {$this->quantity} stocks...\n";

      $this->order_id = $this->trade_station->placeOrder($this->stock, $this->order_type, $this->action_type, $this->quantity);

      if (!$this->order_id)
        throw new OrderFailed;

      $this->stock['order_id'] = $this->order_id;

      $order_processed = false;
      while (!$order_processed) {
        $order = $this->trade_station->getOrder($this->order_id);
        echo "{$this->action_type} Status: {$order['Status']}\n";

        if ($order['Status'] == 'FLL') {
          $is_filled = true;
          $this->stock['price'] = $order['FilledPrice'];
          $total = $order['FilledPrice'] * $this->quantity;

          echo "{$this->action_type} Filled With Price: {$this->stock['price']} to spend a total of {$total}\n";
          $order_processed = true;
        } else if ($order['Status'] == 'REJ') {
          $this->failed_quantity = $this->quantity;

          echo "{$this->action_type} Rejected because: {$order['RejectReason']}\n";
          $pattern = '/current Buying Power values of \$([-\d,\.]+) for Day Trade and \$([-\d,\.]+) for Overnight Buying Power./';
          preg_match($pattern, $order['RejectReason'], $matches);
          if (isset($matches[1]))
            $this->budget = (int) str_replace(',', '', $matches[1]) * self::REDUCTION_FACTOR;

          $order_processed = true;
        }
      }

      echo "\n";
    } while (!$is_filled);

    echo "\n";
  }

  public function getStock()
  {
    return $this->stock;
  }
}

class OrderFailed extends Exception
{
}
