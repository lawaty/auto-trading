<?php

class Start extends Authenticated
{
  public function __construct()
  {
    $this->init([
      'process' => [true, Regex::generic(1, 100)],
    ], $_POST);
  }

  public function handle(): Response
  {
    $global_params = json_decode(file_get_contents(JSONS_DIR . "/params.json"), true);

    if(!count(Process::getAllByName('updateMarketTiming')))
    {
      $new_process = new Process("updateMarketTiming");
      $new_process->run(Process::BACKGROUND);
    }

    if ($day = DayMapper::get(['date' => (new Ndate)->format('d-m-Y')]))
      $day->delete();

    $opp = $this->request['process'] == 'Buy' ? 'Sell' : 'Buy';
    $processes = [
      ...Process::getAllByName("apply" . $this->request['process'] . "Strategies"),
      ...Process::getAllByName("limit$opp")
    ];

    foreach($processes as $process)
      $process->shutdown();

    $process = new Process("apply" . $this->request['process'] . "Strategies");
    $process->setLogger(APP_DIR . '/processes/logs/' . $this->request['process'] . '-' . (new Ndate)->format() . '.log');
    $response = $process->run(Process::BACKGROUND);
    if (!$json['pid'] = $process->getPID())
      return new Response('Process Creation Failed', 500);

    file_put_contents(JSONS_DIR . "/" . $this->request['process'] . ".json", json_encode($json));

    return new Response($response);
  }
}
