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
      $new_process = new Process("updateMarketTiming");
      $new_process->run(Process::BACKGROUND);
    }

    try {
      $opp = $this->request['process'] == 'Buy' ? 'Short' : 'Buy';
      $processes = [
        ...Process::getAllByName("apply" . $this->request['process'] . "Strategies"),
        ...Process::getAllByName($this->request['process'] . "Trade"),
        ...Process::getAllByName("limit$opp")
      ];

      foreach ($processes as $process)
        $process->shutdown();

      $json = json_decode(file_get_contents(JSONS_DIR . "/$opp.json"),true);
      $json['pid'] = "";
      file_put_contents(JSONS_DIR . "/$opp.json", $json);
      
      return new Response;
    } catch (Exception | Error $e) {
      return new Response('', 500);
    }
  }
}
