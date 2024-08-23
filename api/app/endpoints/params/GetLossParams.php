<?php

class GetLossParams extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $params = new Config;
    return new Response([
      'buy' => $params['buy-loss'],
      'short' => $params['short-loss']
    ]);
  }
}
