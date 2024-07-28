<?php

class GetFilters extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $stockmonitor = new StockMonitor();

    return new Response($stockmonitor->getSignals());
  }
}