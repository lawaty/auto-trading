<?php

class Processes extends Endpoint
{
  public function __construct() {
    $this->init([], $_GET);
  }

  public function handle(): Response
  {
    $processes = Process::getAllByName("BuyTrade");
    foreach($processes as $process)
      prettyPrint($process->getArgs());
    
    return new Response;
  }
}