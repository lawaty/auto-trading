<?php

class Start extends Endpoint
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

    $log_file = APP_DIR . '/processes/logs/' . $this->request['process'] . '-' . (new Ndate)->format();
    $i = 1;
    while (file_exists($log_file . " ($i).log"))
      $i++;

    $process = new Process("apply" . $this->request['process'] . "Strategies", $log_file . " ($i).log");
    $response = $process->run(Process::BACKGROUND);
    $processes = Process::getAllByName("apply" . $this->request['process'] . "Strategies");
    foreach ($processes as &$process)
      $process = $process->getPID();

    return new Response($response);
  }
}
