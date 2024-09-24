<?php

class Start extends Authenticated
{
  public function __construct()
  {
    $this->init([
      'process' => [true, "/^(Buy|Short)$/"],
    ], $_POST);
  }

  public function handle(): Response
  {
    if (!count(Process::getAllByName('updateMarketTiming'))) {
      $new_process = new Process("updateMarketTiming", LOG_DIR . '/market-timing-' . (new Ndate)->format(Ndate::DATE) . '.log');
      $new_process->run(Process::BACKGROUND);
      sleep(10);
    }

    if ($day = DayMapper::get(['date' => (new Ndate)->format('d-m-Y')]))
      $day->delete();

    $processes = [
      ...Process::getAllByName("apply" . $this->request['process'] . "Strategies"),
      ...Process::getAllByName($this->request['process'] . "Trade")
    ];

    foreach ($processes as $process)
      $process->shutdown();

    $process = new Process("apply" . $this->request['process'] . "Strategies");
    $process->setLogger(APP_DIR . '/processes/logs/' . $this->request['process'] . '-' . (new Ndate)->format() . '.log');
    $response = $process->run(Process::BACKGROUND);
    $processes = Process::getAllByName("apply" . $this->request['process'] . "Strategies");
    foreach($processes as &$process)
      $process = $process->getPID();

    return new Response($response);
  }
}
