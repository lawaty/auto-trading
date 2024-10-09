<?php

class BulkClearOrders extends Endpoint
{
  public function __construct()
  {
    $this->init([
      'OrderType' => [false, Regex::ANY],
      'Action' => [false, Regex::ANY]
    ], $_POST);
  }

  public function handle(): Response
  {
    $tradestation = new TradeStation('buy'); // Useless parameter in this case

    $cancelled = [];
    $today_orders = $tradestation->getTodayOrders($this->request);
    var_dump($today_orders);
    foreach ($today_orders as $order)
      if ($order['Status'] != 'REJ' && $order['Status'] != 'FLL' && $order['status'] != 'FPR') 
        $cancelled[] = $tradestation->cancel($order['OrderID']);

    return new Response("Cancelled " . json_encode($cancelled));
  }
}
