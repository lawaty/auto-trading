<?php

/**
 * Offensive Trader here is a basic implementation for a trader that tries recursively to buy as much stocks as he can
 * @todo Need to find a way to replace order quantity without selling
 */

class OffensiveTrader
{
  private TradeStation $trade_station;
  private array $stock;
  private string $order_type;
  private string $action_type;
  private float $budget;
  private float $spent = 0;
  private int $quantity = 0;
  private bool $filled_before = false;

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
    $iterations = 0;
    while ($iterations++ < 3 || $this->quantity == 0 && $this->filled_before) {
      $buying_power = $this->trade_station->getBuyingPower();
      if ($buying_power < $this->budget)
        $this->budget = $buying_power;

      $budget = min($this->budget, $buying_power) * 0.97;

      echo "Buying Power: $buying_power, Budget: {$this->budget}\n";

      $more = floor($budget / $this->stock['price']);
      if ($budget <= 0.015 * $buying_power) {
        echo "Offensively bought as much stocks as it could\n";
        break;
      }

      echo "Spending $budget to {$this->action_type} {$this->quantity} stocks...\n";

      if (isset($this->order_id)) {
        // echo "Selling to repurshase\n";
        // $this->trade_station->placeOrder($this->stock, 'Market', 'SELL', $this->quantity);
        // sleep(3);
      }

      $this->order_id = $this->trade_station->placeOrder($this->stock, $this->order_type, $this->action_type, $this->quantity + $more);

      if (!$this->order_id)
        throw new OrderFailed;

      $this->stock['order_id'] = $this->order_id;

      do {
        $order = $this->trade_station->getOrder($this->order_id);
        echo "{$this->action_type} Status: {$order['Status']}\n";
        if ($order['Status'] == 'FLL') {
          $this->budget -= $order['FilledPrice'] * $more;
          $this->stock['price'] = $order['FilledPrice'];
          $this->quantity += $more;
          $this->spent = $this->stock['price'] * $this->quantity;
          echo "{$this->action_type} Filled With Price: {$this->stock['price']} to spend a total of {$this->spent}\n";
          sleep(5);
          $this->filled_before = true;
          break;
        } else if ($order['Status'] == 'REJ') {
          echo "{$this->action_type} Rejected because: {$order['RejectReason']}\n";
          $pattern = '/current Buying Power values of \$([-\d,\.]+) for Day Trade and \$([-\d,\.]+) for Overnight Buying Power./';
          preg_match($pattern, $order['RejectReason'], $matches);
          if (isset($matches[1]))
            $this->budget = (int) str_replace(',', '', $matches[1]) * 0.9;
          sleep(5);
          break;
        }
        sleep(5);
      } while (true);
    }

    if ($this->quantity == 0)
      throw new InsufficientMoney();

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
