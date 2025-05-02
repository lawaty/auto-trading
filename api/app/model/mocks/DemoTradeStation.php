<?php

const BUYING_TOLERANCE = 0.98;

class DemoTradeStation
{
  public string $access_token;
  public string $account_id = "12345678";
  public string $api_base = "https://mock-api.tradestation.com/v3";
  private array $params;
  private string $which;
  private Ndate $refreshed_at;

  public function __construct($which)
  {
    $this->which = $which;
    $this->loadSettings();
  }

  public function getTodayOrders(array $filters = [])
  {
    sleep(1);
    // Mock response for orders
    $orders = [
      [
        'Legs' => [['BuyOrSell' => 'BUY']],
        'OpenedDateTime' => (new Ndate)->format(),
        'OrderID' => 123,
        'Symbol' => 'AAPL'
      ],
      [
        'Legs' => [['BuyOrSell' => 'SELL']],
        'OpenedDateTime' => (new Ndate)->format(),
        'OrderID' => 456,
        'Symbol' => 'TSLA'
      ]
    ];

    $res = [];
    foreach ($orders as $order) {
      $desired = true;
      foreach ($filters as $key => $value) {
        if (
          $key == 'Action' && $order['Legs'][0]['BuyOrSell'] != $value ||
          $key != 'Action' && $order[$key] != $value
        ) {
          $desired = false;
          break;
        }
      }
      if (!$desired)
        continue;

      if ((new Ndate($order['OpenedDateTime']))->format() == (new Ndate)->format())
        $res[] = $order;
    }

    return $res;
  }

  public function cancel(int $order_id)
  {
    // Mock cancellation response
    return ["success" => true, "message" => "Order $order_id cancelled"];
  }

  public function curl(string $url, string $type, array $data = [], array $headers = [], $logging = false): mixed
  {
    // Mock curl response
    return [
      'Orders' => [
        ['OrderID' => 789, 'Symbol' => 'GOOGL', 'Quantity' => 10]
      ]
    ];
  }

  public function loadSettings()
  {
    // Mock settings
    $this->params = [
      'globals' => [
        'account_id' => '12345678',
        'is_live' => "0"
      ]
    ];
    $this->account_id = $this->params['globals']['account_id'];
  }

  public function getBalance(): ?float
  {
    sleep(1);
    // Mock balance response
    return 10000.00;
  }

  public function getBuyingPower()
  {
    sleep(1);
    // Mock buying power response
    return 5000.00;
  }

  public function getStock(string $symbol): array
  {
    sleep(1);
    // Mock stock response
    return [
      'symbol' => $symbol,
      'price' => 150.00,
      'quantity' => 100
    ];
  }

  public function getStockEstimatedPrice($stock_symbol, $order_type, $trade_action)
  {
    sleep(1);
    // Mock estimated price response
    return 149.50;
  }

  public function placeOrder($stock, $order_type, $trade_action, $quantity = null, $percent = 0): ?int
  {
    sleep(1);
    // Mock placing an order
    return 12345; // Mock order ID
  }

  public function placeOCO($stock, array $operations)
  {
    // Mock OCO order response
    return [789, 790]; // Mock Order IDs
  }

  public function editOrder(array $stock, int $order_id, array $data): ?int
  {
    // Mock editing an order
    return $order_id; // Return the same order ID after edit
  }
}
