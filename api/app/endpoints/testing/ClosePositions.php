<?php

class ClosePositions extends Endpoint{
  public function __construct()
  {
    $this->init([
      'type' => [true, '/^(buy|short)$/'],
      'ids' => [true, Regex::separated(',')]
    ], $_GET);
  }

  public function handle(): Response
  {
    $trade_station = new TradeStation($this->request['type']);

    foreach(explode(',', $this->request['ids']) as $id) {
      $details = $trade_station->getOrder($id)['Legs'][0];
      $trade_station->closePosition([
        'symbol' => $details['Symbol'],
        'quantity' => $details['ExecQuantity'],
        'order_id' => $id
      ]);

      ob_flush();
      flush();
    }

    return new Response;
  }
}