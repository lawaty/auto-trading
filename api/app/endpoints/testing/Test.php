<?php

class Test extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    return new Response((new TradeStation('buy'))->getEquity());
  }
}
