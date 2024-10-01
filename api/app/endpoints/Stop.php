<?php

class Stop extends Authenticated
{
  public function __construct()
  {
    $this->init([
      'process' => [true, Regex::ANY],
    ], $_POST);
  }

  public function handle(): Response
  {
    if (!count(Process::getAllByName('updateMarketTiming'))) {
      $new_process = new Process("updateMarketTiming", LOG_DIR . '/market-timing-' . (new Ndate)->format(Ndate::DATE) . '.log');
      $new_process->run(Process::BACKGROUND);
      sleep(10);
    }


    try {
      $processes = [
        ...Process::getAllByName("apply" . $this->request['process'] . "Strategies"),
        ...Process::getAllByName($this->request['process'] . "Trade")
      ];

      foreach ($processes as $process)
        $process->shutdown();
      
      DefensiveTrader::emptyCurrentlyTrading();

      return new Response;
    } catch (Exception | Error $e) {
      return new Response('', 500);
    }
  }
}
