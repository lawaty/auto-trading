<?php

class Test extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $tradestation = new TradeStation('buy');

    $tradestation->getOrder(856208644);

    return new Response();
  }
}
