<?php

class Stop extends Authenticated
{
  public function __construct()
  {
    $this->init([
      'process' => [true, "/^(Buy|Short)$/"],
      'graceful' => [true, Regex::ZERO_ONE]
    ], $_POST);
  }

  public function handle(): Response
  {
    if (!count(Process::getAllByName('updateMarketTiming'))) {
      $new_process = new Process("updateMarketTiming", LOG_DIR . '/market-timing-' . (new Ndate)->format(Ndate::DATE) . '.log');
      $new_process->run(Process::BACKGROUND);
      sleep(10);
    }

    $monitor = new TradeMonitor;
    $tradestation = new TradeStation(strtolower($this->request['process']));

    $processes = [
      ...Process::getAllByName("apply" . $this->request['process'] . "Strategies"),
      ...Process::getAllByName($this->request['process'] . "Trade")
    ];

    /**
     * @var Process
     */
    foreach ($processes as $process) {
      $process->shutdown();
      if ($this->request['graceful']) {
        $trades = $monitor->getAllByPID($process->getPID());
        foreach ($trades as $symbol => $trade) {
          $tradestation->closePosition($trade);
          $monitor->remove($symbol);
        }
      }
    }

    return new Response;
  }
}
