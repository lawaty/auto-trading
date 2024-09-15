<?php

class GetAll extends Endpoint
{
  public function __construct()
  {
    $this->init([
      'which' => [false, "/^(buy|short)$/"]
    ], $_GET);
  }

  public function handle(): Response
  {
    $all_params = json_decode(file_get_contents(JSONS_DIR . '/params.json'), true);
    $tradestation = new TradeStation('buy');

    if (!isset($this->request['which'])) {
      try {
        $balance = $tradestation->getBalance();
      } catch (InvalidAccountID $e) {
        $balance = null;
      }
      return new Response([...$all_params, 'balance' => $balance]);
    } else {
      $process = ucfirst($this->request['which']);
      /**
       * @var array<Process>
       */ 
      $processes = Process::getAllByName("apply{$process}Strategies");
      $pid = count($processes) ? $processes[0]->getPID() : '';
      return new Response([
        'runs' => $all_params[$this->request['which']],
        'pid' => $pid
      ]);
    }
  }
}
