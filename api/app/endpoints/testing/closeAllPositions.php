<?php

class closeAllPositions extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    // Get all positions
    $trade_station = new TradeStation("buy");

    $positions = $trade_station->curl("brokerage/accounts/{$trade_station->account_id}/positions", "GET")['Positions'] ?? [];

    foreach ($positions as $position) {
      $symbol = $position['Symbol'];
      $quantity = $position['Quantity'];

      // Check if it's a short position or a long position
      $trade_action = $position['LongShort'] === 'Short' ? 'BuyToCover' : 'Sell';

      // Close the position by placing a market order
      $trade_station->placeOrder(['symbol' => $symbol, 'quantity' => $quantity], 'Market', $trade_action);
    }
    
    return new Response($positions);
  }
}
