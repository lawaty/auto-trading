<?php

class Test extends Endpoint
{
  public function __construct()
  {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    UnwantedStock::updateJSON();

    return new Response();
  }
}
