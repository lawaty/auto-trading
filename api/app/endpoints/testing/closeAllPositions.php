<?php

class closeAllPositions extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $trade_station = new TradeStation('buy');

    // Fetch all open orders and cancel them
    $open_orders = $trade_station->getTodayOrders([
      'OpenOrClose' => 'Open',
      'Status' => ['ACK', '']
    ]);

    return new Response($open_orders);
    // foreach ($open_orders as $order)
    //   $trade_station->cancel($order['OrderID']);

    // // Fetch all positions for the account
    // $positions = $trade_station->curl("brokerage/accounts/{$trade_station->account_id}/positions", "GET", [], [], true)['Positions'] ?? [];

    // if (empty($positions)) {
    //   return new Response(['message' => 'No open positions found']);
    // }

    // // Close all positions
    // foreach ($positions as $position) {
    //   $symbol = $position['Symbol'];
    //   $quantity = $position['Quantity'];

    //   $trade_action = $position['LongShort'] === 'Short' ? 'BuyToCover' : 'Sell';

    //   $trade_station->placeOrder([
    //     'symbol' => $symbol,
    //     'quantity' => $quantity
    //   ], 'Market', $trade_action);
    // }

    // return new Response(['message' => 'All positions closed successfully', 'positions' => $positions]);
  }
}
